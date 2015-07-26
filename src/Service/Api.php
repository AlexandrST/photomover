<?php

namespace Photomover\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Psr\Http\Message\ResponseInterface;

class Api
{
    const API_URI = 'https://api.vk.com/method';

    const SLEEP_TIME = 200;

    const ERROR_RPS = 6;

    private $defaults;
    private $apiRpsLimit;

    private $client;

    public function __construct($userId, $accessToken, $apiVer, $apiRpsLimit)
    {
        $this->defaults = [
            'owner_id' => $userId,
            'access_token' => $accessToken,
            'v' => $apiVer,
        ];

        $this->apiRpsLimit = $apiRpsLimit;

        $this->client = new Client([
            'connect_timeout' => 3,
        ]);
    }


    public function getAlbums()
    {
        $query = [
            'need_system' => 1,
        ];

        $result = $this->call('photos.getAlbums', $query);

        return $result['items'];
    }


    public function getPhotos($albumId, $offset)
    {
        $query = [
            'album_id' => $albumId,
            'offset' => $offset,
            'count' => 1000,
        ];

        $result = $this->call('photos.get', $query);

        return $result['items'];
    }


    public function movePhotos(array $photos, $targetId, \Closure $callback)
    {
        $attempts = [];
        $promises = [];

        while (isset($photos[0])) {
            $photoId = array_pop($photos)['id'];

            $args = [
                'photo_id' => $photoId,
                'target_album_id' => $targetId,
            ];

            $attempts[$photoId] = 1;

            $promise = $this->asyncCall('photos.move', $args);
            $promises = [];

            $promise->then(function ($response) use (
                $photoId,
                &$attempts,
                &$photos,
                $callback
            ) {
                $body = $this->getBody($response, false);
                $data = json_decode($body, true);

                if (isset($data['error'])) {
                    if ($data['error']['error_code'] === self::ERROR_RPS) {
                        if ($attempts[$photoId] > 3) {
                            throw new \RuntimeException('Max retry attempts reached');
                        }

                        usleep(self::SLEEP_TIME);

                        ++$attempts[$photoId];
                        $photos[] = ['id' => $photoId];
                    }

                    if (empty($data['response'])) {
                        throw new \RuntimeException(
                            sprintf('Bad response: %s', $body)
                        );
                    } elseif ($data['response'] === 1) {
                        $callback();
                    }
                }
            });
        }

        Promise\unwrap($promises);
    }


    private function call($method, $args, $attempt = 1)
    {
        $uri = $this->getUri($method, $args);
        $response = $this->client->get($uri);

        $body = $this->getBody($response, false);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            if ($data['error']['error_code'] === self::ERROR_RPS) {
                if ($attempt > 3) {
                    throw new \RuntimeException('Max retry attempts reached');
                }

                usleep(self::SLEEP_TIME);

                return $this->call($method, $args, ++$attempt);
            } else {
                throw new \RuntimeException(
                    sprintf('API error: %s', $body)
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

    private function asyncCall($method, $args, $attempt = 1)
    {
        $uri = $this->getUri($method, $args);

        return $this->client->getAsync($uri);
    }

    private function getUri($method, $args)
    {
        $uri = sprintf(
            '%s/%s?%s',
            self::API_URI,
            $method,
            http_build_query($this->defaults + $args)
        );

        return $uri;
    }

    private function getBody(ResponseInterface $response, $decode = true)
    {
        $body = $response->getBody()->getContents();

        if ($decode) {
            $body = json_decode($body, true);
        }

        return $body;
    }


}
