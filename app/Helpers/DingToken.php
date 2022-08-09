<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DingToken
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $url = 'https://oapi.dingtalk.com';

    /**
     * @var string
     */
    private $appKey = 'dingd0gyohzen76jdtvn';

    /**
     * @var string
     */
    private $appSecret = '0JemPsflN0m6a1RbW7HI5IjeG9wzl_yhcDBOAw374ao_zsvN1fWUVyA8Tl9qd3yw';

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var string
     */
    private $cacheKey;

    public function __construct()
    {
        $this->client = new Client(['base_uri' => $this->url, 'verify' => false]);
        $this->cacheKey = 'product_process_access_token_'.date('Ymd');
    }

    /**
     * 获取 access_token.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return string
     */
    public function getAccessToken()
    {
        if (Cache::has($this->cacheKey)) {
            return Cache::get($this->cacheKey);
        }

        if ($this->generate()) {
            // 缓存1小时
            Cache::set($this->cacheKey, $this->accessToken, 3600);
        }

        return $this->accessToken;
    }

    /**
     * 生成 access_token.
     *
     * @return bool
     */
    private function generate()
    {
        try {
            $response = $this->client->request('GET', 'gettoken', [
                RequestOptions::QUERY => [
                    'appkey' => $this->appKey, 'appsecret' => $this->appSecret,
                ],
            ]);
            if (200 == $response->getStatusCode()) {
                $json = json_decode($response->getBody()->getContents());
                if (0 === $json->errcode) {
                    $this->accessToken = $json->access_token;

                    return true;
                }
            }
        } catch (Throwable $exception) {
        }

        return false;
    }
}
