<?php

namespace Photomover\Service;

use GuzzleHttp\Client;

class Api
{
    const SLEEP_FOR = 250;

    const API_URl = 'https://api.vk.com/method';

    const ERROR_TOO_MANY_RPS = 6;

    private $data;
    private $apiVer;

    private $client;

    public function __construct(array $data, $apiVer)
    {
        $this->data = $data;
        $this->apiVer = $apiVer;

        $this->client = new Client();
    }

    public function getAlbums()
    {
        $args = [
            'need_system' => 1,
            'owner_id' => $this->data['user_id'],
            'access_token' => $this->data['access_token'],
        ];

        $result = $this->call('photos.getAlbums', $args);

        return $result;
    }

    public function getPhotos($aid, $offset)
    {
        $args = [
            'owner_id' => $this->data['user_id'],
            'album_id' => $aid,
            'offset' => $offset,
            'count' => 1000,
            'access_token' => $this->data['access_token'],
            'v' => $this->apiVer,
        ];

        $result = $this->call('photos.get', $args);

        return $result;
    }

    public function movePhoto($id, $albumId)
    {
        $args = [
            'owner_id' => $this->data['user_id'],
            'target_album_id' => $albumId,
            'photo_id' => $id,
            'access_token' => $this->data['access_token'],
            'v' => $this->apiVer,
        ];

        $result = $this->call('photos.move', $args);

        return $result;
    }

    private function call($method, array $args, $attempt = 1)
    {
        $uri = sprintf(
            '%s/%s?%s',
            self::API_URl,
            $method,
            http_build_query($args)
        );

        $response = $this->client->get($uri);
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            switch ($data['error']['error_code']) {
                case self::ERROR_TOO_MANY_RPS:
                    usleep(self::SLEEP_FOR);

                    return $this->call($method, $args, ++$attempt);
                default:
                    throw new \RuntimeException(
                        sprintf('API Error: %s', $body)
                    );
            }
        }

        if (empty($data['response'])) {
            throw new \RuntimeException(
                sprintf('Bad response: %s', $body)
            );
        }

        return $data['response'];
    }
}
