<?php

namespace Photomover\Service;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\DomCrawler\Crawler;

class Authorizer
{
    const AUTH_URI = 'https://oauth.vk.com/authorize';
    const BLANK_URI = 'https://oauth.vk.com/blank.html';
    const TOKEN_URI = 'https://oauth.vk.com/access_token';

    private $clientId;
    private $clientSecret;
    private $apiVer;
    private $login;
    private $password;

    private $client;

    public function __construct($clientId, $clientSecret, $apiVer, $login, $password)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->apiVer = $apiVer;
        $this->login = $login;
        $this->password = $password;

        $this->client = new Client([
            'cookies' => true,
            'connect_timeout' => 3,
        ]);
    }


    public function attempt()
    {
        $parameters = $this->getParameters($this->signIn($this->authorize()));

        return $parameters;
    }


    private function authorize()
    {
        $query = [
            'client_id' => $this->clientId,
            'scope' => 'photos',
            'redirect_uri' => self::BLANK_URI,
            'response_type' => 'code',
            'v' => $this->apiVer,
        ];

        $response = $this->client->get(self::AUTH_URI, compact('query'));

        return $response;
    }


    private function signIn(ResponseInterface $response)
    {
        list($uri, $payload) = $this->getSignInPayload($response);

        $code = null;

        $onRedirect = function() use (&$code) {
            /** @var UriInterface $uri */
            $uri = func_get_arg(2);

            parse_str($uri->getFragment(), $query);

            if (!empty($query['code'])) {
                $code = $query['code'];
            }
        };

        $redirectOpts = [
            'allow_redirects' => [
                'on_redirect' => $onRedirect,
            ],
        ];

        $options = [
            'form_params' => $payload,
        ];

        $response = $this->client->post($uri, $redirectOpts + $options);

        $grantUri = $this->ensureAccessGranted($response);

        if ($grantUri) {
            $this->client->post($grantUri, $redirectOpts);
        }

        return $code;
    }

    private function getSignInPayload(ResponseInterface $response)
    {
        $form = [];

        $formNode = $this->extractForm($response);
        $uri = $formNode->getAttribute('action');

        /** @var \DOMElement[] $inputs */
        $inputs = (new Crawler($formNode))->filter('input');
        $inputTypes = ['text', 'hidden', 'password'];

        foreach ($inputs as $input) {
            $name = $input->getAttribute('name');
            $value = $input->getAttribute('value');
            $type = $input->getAttribute('type');

            if (in_array($type, $inputTypes) && $name) {
                $form[$name] = $value;
            }
        }

        $form['email'] = $this->login;
        $form['pass'] = $this->password;

        return [$uri, $form];
    }

    private function ensureAccessGranted(ResponseInterface $response)
    {
        $formNode = $this->extractForm($response);
        $uri = null;

        if ($formNode !== null) {
            $uri = $formNode->getAttribute('action');
        }

        return $uri;
    }

    private function extractForm(ResponseInterface $response)
    {
        $html = $response->getBody()->getContents();

        $crawler = new Crawler();
        $crawler->addContent($html, 'text/html');

        $form = $crawler->filter('form');

        if ($form->count() > 0) {
            return $form->getNode(0);
        } else {
            return null;
        }
    }


    private function getParameters($code)
    {
        $query = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => self::BLANK_URI,
            'code' => $code,
        ];

        $response = $this->client->get(self::TOKEN_URI, compact('query'));
        $body = $response->getBody()->getContents();

        $parameters = json_decode($body, true);
        unset($parameters['expires_in']);

        return $parameters;
    }
}
