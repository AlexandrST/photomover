<?php

namespace Photomover\Command;

use Photomover\ContainerAwareCommand;
use Photomover\Service\Api;
use Photomover\Service\Authorizer;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class PhotoMoveCommand extends ContainerAwareCommand
{
    /**
     * @var Api
     */
    private $api;

    /**
     * @inheritdoc
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->authorize();
        $this->movePhotos($output, $this->askAlbums($input, $output));
    }


    private function authorize()
    {
        /** @var Authorizer $authorizer */
        $authorizer = $this->container->get('authorizer');
        $parameters = $authorizer->attempt();

        foreach ($parameters as $k => $v) {
            $this->container->setParameter($k, $v);
        }

        $this->api = $this->container->get('api');
    }


    private function askAlbums(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $albums = $this->api->getAlbums();
        $indexMap = array_flip(array_column($albums, 'id'));

        $q = new ChoiceQuestion('Album from:', $this->getFromAlbums($albums));
        $fromId = $helper->ask($input, $output, $q);

        $q = new ChoiceQuestion('Album to:', $this->getToAlbums($albums, $fromId));
        $toId = $helper->ask($input, $output, $q);

        $from = $albums[$indexMap[$fromId]];
        $to = $albums[$indexMap[$toId]];

        return compact('from', 'to');
    }

    private function getFromAlbums(array $rawAlbums)
    {
        $albums = [];

        foreach ($rawAlbums as $album) {
            if ($album['size'] > 0) {
                $albums[$album['title']] = $album['id'];
            }
        }

        return $albums;
    }

    private function getToAlbums(array $rawAlbums, $fromId)
    {
        $albums = [];

        foreach ($rawAlbums as $album) {
            if ($album['id'] > 0 && $album['id'] !== $fromId && $album['size'] < 10000) {
                $albums[$album['title']] = $album['id'];
            }
        }

        return $albums;
    }


    private function movePhotos(OutputInterface $output, array $albums)
    {
        $from = $albums['from'];
        $to = $albums['to'];

        //----------------------------------------------------------------------
        // AVAILABLE COUNT
        //----------------------------------------------------------------------

        $inputCount = $from['size'];
        $leftCount = 10000 - $to['size'];

        $count = min($inputCount, $leftCount);

        //----------------------------------------------------------------------
        // SETUP PARAMS
        //----------------------------------------------------------------------

        $albumsMap = [
            'Фотографии с моей страницы' => 'profile',
            'Фотографии на моей стене' => 'wall',
            'Сохранённые фотографии' => 'saved',
        ];

        $fromTitle = $from['title'];
        $albumId = $from['id'];
        $targetId = $to['id'];

        if (isset($albumsMap[$fromTitle])) {
            $albumId = $albumsMap[$fromTitle];
        }

        //----------------------------------------------------------------------
        // MOVE
        //----------------------------------------------------------------------

        $progress = new ProgressBar($output, $count);
        $progress->start();

        $offset = 0;

        $callback = function () use ($progress, &$offset, &$count) {
            $progress->advance();
            ++$offset;
            --$count;
        };

        while ($count > 0) {
            $photos = $this->api->getPhotos($albumId, $offset);

            if (empty($photos)) {
                if ($count > 0) {
                    $output->writeln('<error>Unexpected end of photos</error>');
                }

                break;
            }

            $this->api->movePhotos($photos, $targetId, $callback);
        }

        $progress->finish();
    }
}
