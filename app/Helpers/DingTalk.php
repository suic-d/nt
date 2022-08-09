<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class DingTalk
{
    /**
     * @var string
     */
    protected $url = 'https://gosvc.nterp.nantang-tech.com/gopush/dingtalk/asyncPush';

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * DingTalk constructor.
     */
    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * @param string $title
     * @param string $text
     * @param string $userId
     *
     * @return bool
     */
    public function push($title, $text, $userId)
    {
        $success = false;

        $params = ['title' => $title, 'text' => $text, 'userId' => $userId];

        try {
            $response = $this->client->request('POST', $this->url, [RequestOptions::FORM_PARAMS => $params]);
            if (200 == $response->getStatusCode()) {
                $json = json_decode($response->getBody()->getContents());
                if (isset($json->code) && '0' == $json->code) {
                    $success = true;
                }
            }
        } catch (GuzzleException $exception) {
        }

        return $success;
    }
}
