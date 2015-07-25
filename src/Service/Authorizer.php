<?php

namespace Photomover\Service;

use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\DomCrawler\Crawler;

class Authorizer
{
    const AUTH_URI = 'https://oauth.vk.com/authorize';
    const TOKEN_URI = 'https://oauth.vk.com/access_token';
    const BLANK_URI = 'https://oauth.vk.com/blank.html';

    private $clientId;
    private $clientSecret;
    private $apiVer;

    private $authenticated = false;
    private $data = [];

    /**
     * @var Client
     */
    private $client;

    public function __construct($clientId, $clientSecret, $apiVer)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->apiVer = $apiVer;

        $this->client = new Client([
            'cookies' => true,
        ]);
    }

    public function getData()
    {
        return $this->data;
    }

    public function attempt($login, $password)
    {
        $this->getToken(
            $this->signIn(
                $this->authorize(),
                $login,
                $password
            )
        );

        return $this->authenticated;
    }

    //----------------------------------------------------------------------
    // AUTHORIZE
    //----------------------------------------------------------------------

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

    //----------------------------------------------------------------------
    // SIGN IN
    //----------------------------------------------------------------------

    private function signIn(ResponseInterface $response, $login, $password)
    {
        list($uri, $form) = $this->extractLoginForm($response);
        $form['email'] = $login;
        $form['pass'] = $password;

        $code = null;

        $onRedirect = function (
            RequestInterface $request,
            ResponseInterface $response,
            UriInterface $uri
        ) use (&$code) {
            parse_str($uri->getFragment(), $query);

            if (!empty($query['code'])) {
                $code = $query['code'];
            }
        };

        $options = [
            'form_params' => $form,
            'allow_redirects' => [
                'on_redirect' => $onRedirect
            ],
        ];

        $this->client->post($uri, $options);

        return $code;
    }

    private function extractLoginForm(ResponseInterface $response)
    {
        $form = [];

        $html = $response->getBody()->getContents();

        $crawler = new Crawler();
        $crawler->addContent($html, 'text/html');

        $formNode = $crawler->filter('form')->getNode(0);
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

        return [$uri, $form];
    }

    //----------------------------------------------------------------------
    // GET TOKEN
    //----------------------------------------------------------------------

    private function getToken($code)
    {
        $query = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => self::BLANK_URI,
            'code' => $code,
        ];

        $options = [
            'allow_redirects' => false,
            'query' => $query,
        ];

        $response = $this->client->get(self::TOKEN_URI, $options);
        $this->data = json_decode($response->getBody()->getContents(), true);

        $this->authenticated = isset(
            $this->data['access_token'],
            $this->data['user_id']
        );
    }
}
