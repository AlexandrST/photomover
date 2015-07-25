<?php

namespace Photomover\Command;

use Photomover\Service\Api;
use Photomover\Service\Authorizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class PhotoMoverCommand extends Command
{
    /**
     * @inheritdoc
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        //----------------------------------------------------------------------
        // AUTH DATA
        //----------------------------------------------------------------------

        $authorizer = new Authorizer(
            $_ENV['CLIENT_ID'],
            $_ENV['CLIENT_SECRET'],
            $_ENV['API_VER']
        );

        if ($authorizer->attempt($_ENV['VK_LOGIN'], $_ENV['VK_PASS'])) {
            $api = new Api(
                $authorizer->getData(),
                $_ENV['API_VER']
            );
        } else {
            throw new \InvalidArgumentException(
                'Unable to authenticate with provided credentials'
            );
        }

        //----------------------------------------------------------------------
        // ALBUMS IDS
        //----------------------------------------------------------------------

        $albums = $api->getAlbums();
        $fromAlbums = [];
        $toAlbums = [];


        foreach ($albums as $album) {
            if ($album['size'] > 0) {
                $fromAlbums[$album['title']] = $album['aid'];
            }
        }
        unset($album);

        $q = new ChoiceQuestion('Move photos from: ', $fromAlbums);
        $fromId = $helper->ask($input, $output, $q);
        unset($q);


        foreach ($albums as $album) {
            if ($album['aid'] > 0 && $album['aid'] !== $fromId) {
                $toAlbums[$album['title']] = $album['aid'];
            }
        }
        unset($album);

        if (count($toAlbums) === 0) {
            throw new \LogicException('No albums left');
        }

        $q = new ChoiceQuestion('Move photos to: ', $toAlbums);
        $toId = $helper->ask($input, $output, $q);
        unset($q);

        //----------------------------------------------------------------------
        // PREPARE
        //----------------------------------------------------------------------

        $map = [
            'Фотографии с моей страницы' => 'profile',
            'Фотографии на моей стене' => 'wall',
            'Сохранённые фотографии' => 'saved',
        ];

        $fromName = array_search($fromId, $fromAlbums);

        if (isset($map[$fromName])) {
            $aid = $map[$fromName];
        } else {
            $aid = $fromId;
        }


        $count = 0;

        foreach ($albums as $album) {
            if ($album['aid'] === $fromId) {
                $count = $album['size'];
                break;
            }
        }

        unset($albums, $album, $fromAlbums, $toAlbums);

        //----------------------------------------------------------------------
        // PROCESS
        //----------------------------------------------------------------------

        $progress = new ProgressBar($output, $count);
        $progress->start();

        $offset = 0;

        while ($count > 0) {
            $photos = $api->getPhotos($aid, $offset);

            if (empty($photos['items'])) {
                $output->writeln('No more photos');
                break;
            }

            foreach ($photos['items'] as $photo) {
                if ($api->movePhoto($photo['id'], $toId)) {
                    $progress->advance();
                }

                ++$offset;
                --$count;
            }
        }

        $progress->finish();
    }
}
