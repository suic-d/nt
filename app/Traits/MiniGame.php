<?php

namespace App\Traits;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Monolog\Logger;

trait MiniGame
{
    /**
     * @var string
     */
    protected $openId;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var array
     */
    protected $userInfo;

    /**
     * @var array
     */
    protected $fmList;

    /**
     * @var string
     */
    protected $gameType;

    /**
     * @return array
     */
    public function getUserInfo(): array
    {
        if (empty($this->userInfo)) {
            try {
                $response = $this->client->request('GET', 'miniGame/getUserInfo', [
                    RequestOptions::QUERY => ['openid' => $this->openId],
                ]);
                $this->userInfo = json_decode($response->getBody()->getContents(), true)['data'] ?? [];
            } catch (GuzzleException $exception) {
                $this->logger->error(__METHOD__.' '.$exception->getMessage());
            }
        }

        return $this->userInfo;
    }

    /**
     * 穿戴装备.
     *
     * @param string $zbId
     */
    public function levelCount(string $zbId)
    {
        try {
            $this->client->request('GET', 'miniGame/levelCount', [RequestOptions::QUERY => [
                'openid' => $this->openId,
                'zbId' => $zbId,
            ]]);
        } catch (GuzzleException $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }
    }

    /**
     * 清空背包.
     */
    public function clearBag()
    {
        try {
            $this->client->request('GET', 'miniGame/clearBag', [RequestOptions::QUERY => ['openid' => $this->openId]]);
        } catch (GuzzleException $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }
    }

    /**
     * 开始副本.
     *
     * @param string $raidId
     * @param string $bossId
     */
    public function doRaid(string $raidId, string $bossId)
    {
        try {
            $response = $this->client->request('GET', 'miniGame/doRaid', [RequestOptions::QUERY => [
                'openid' => $this->openId,
                'raidId' => $raidId,
                'bossId' => $bossId,
            ]]);
            $this->logger->info(__METHOD__.' '.$response->getBody()->getContents());
        } catch (GuzzleException $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }
    }

    /**
     * 获取副本列表.
     *
     * @param string $gameType
     *
     * @return array
     */
    public function getRaidList(string $gameType): array
    {
        try {
            $response = $this->client->request('GET', 'miniGame/getRaidList', [
                RequestOptions::QUERY => ['gameType' => $gameType],
            ]);

            return json_decode($response->getBody()->getContents(), true)['data'] ?? [];
        } catch (GuzzleException $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }

        return [];
    }

    /**
     * 获取附魔列表.
     *
     * @return array
     */
    public function getFMList(): array
    {
        if (empty($this->fmList)) {
            $this->fmList = $this->getBuffList(json_encode([299]));
        }

        return $this->fmList;
    }

    /**
     * @param int $level
     */
    public function buyFM(int $level)
    {
        $map = array_column($this->getFMList(), null, 'level');
        if (isset($map[$level])) {
            $this->buyZhuangBei(json_encode($map[$level], JSON_UNESCAPED_UNICODE), 'fm');
        }
    }

    /**
     * @param string $buffId
     *
     * @return array
     */
    public function getBuffList(string $buffId): array
    {
        try {
            $response = $this->client->request('GET', 'miniGame/getBuffList', [
                RequestOptions::QUERY => ['buffId' => $buffId],
            ]);

            return json_decode($response->getBody()->getContents(), true)['data'] ?? [];
        } catch (GuzzleException $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }

        return [];
    }

    /**
     * @param string $detail
     * @param string $shopType
     */
    public function buyZhuangBei(string $detail, string $shopType)
    {
        try {
            $response = $this->client->request('GET', 'miniGame/buyZhuangbei', [RequestOptions::QUERY => [
                'openid' => $this->openId,
                'detail' => $detail,
                'shopType' => $shopType,
            ]]);
            $this->logger->info($response->getBody()->getContents());
        } catch (GuzzleException | Exception $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }
    }

    /**
     * @param string $gameType
     *
     * @return array
     */
    public function getShoppingList(string $gameType): array
    {
        try {
            $response = $this->client->request('GET', 'miniGame/getShoppingList', [
                RequestOptions::QUERY => ['gameType' => $gameType],
            ]);

            return json_decode($response->getBody()->getContents(), true)['data'] ?? [];
        } catch (GuzzleException $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }

        return [];
    }

    /**
     * @return bool
     */
    public function curRaid(): bool
    {
        $userInfo = $this->getUserInfo();

        return 1 == $userInfo['curRaid'];
    }

    /**
     * 附魔.
     *
     * @param int $bossLevel
     */
    public function fm(int $bossLevel)
    {
        $userInfo = $this->getUserInfo();
        $level = $userInfo['level'] + $userInfo['buff'];
        // 高于25，无需fm
        if ($level - $bossLevel > 25) {
            return;
        }

        $diff = $bossLevel + 25 - $level;
        // 无需fm
        if ($diff <= 0 || $diff > 250) {
            return;
        }
        if ($diff <= 20) {
            $this->buyFM(20);
        } elseif ($diff <= 30) {
            $this->buyFM(30);
        } elseif ($diff <= 40) {
            $this->buyFM(40);
        } elseif ($diff <= 50) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(30);
        } elseif ($diff <= 60) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(40);
        } elseif ($diff <= 70) {
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(40);
        } elseif ($diff <= 80) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(60);
        } elseif ($diff <= 90) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(40);
        } elseif ($diff <= 100) {
            $this->buyFM(100);
        } elseif ($diff <= 110) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(60);
        } elseif ($diff <= 120) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 130) {
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 140) {
            $this->buyFM(40);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 150) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 160) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(40);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 170) {
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(40);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 180) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(60);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 190) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(40);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 200) {
            $this->buyFM(40);
            sleep(3);
            $this->buyFM(60);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 210) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(60);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 220) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(40);
            sleep(3);
            $this->buyFM(60);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 230) {
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(40);
            sleep(3);
            $this->buyFM(60);
            sleep(3);
            $this->buyFM(100);
        } elseif ($diff <= 250) {
            $this->buyFM(20);
            sleep(3);
            $this->buyFM(30);
            sleep(3);
            $this->buyFM(40);
            sleep(3);
            $this->buyFM(60);
            sleep(3);
            $this->buyFM(100);
        }
    }
}
