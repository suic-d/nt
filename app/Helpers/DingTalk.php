<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Env;

class DingTalk
{
    /**
     * @var string
     */
    protected $url;

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
        $this->url = env('BASE_URL_GOSVC');
        $this->client = new Client(['base_uri' => $this->url, 'verify' => false]);
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
            $response = $this->client->request('POST', 'gopush/dingtalk/asyncPush', [
                RequestOptions::FORM_PARAMS => $params,
            ]);
            if (200 == $response->getStatusCode()) {
                $json = json_decode($response->getBody()->getContents(), true);
                if (isset($json['code']) && '0' == $json['code']) {
                    $success = true;
                }
            }
        } catch (GuzzleException $exception) {
        }

        return $success;
    }
}
