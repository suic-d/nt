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
    public function getUserInfo()
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
    public function levelCount($zbId)
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
    public function doRaid($raidId, $bossId)
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
    public function getRaidList($gameType)
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
    public function getFMList()
    {
        if (empty($this->fmList)) {
            $this->fmList = $this->getBuffList(json_encode([299]));
        }

        return $this->fmList;
    }

    /**
     * @param int $level
     */
    public function buyFM($level)
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
    public function getBuffList($buffId)
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
     *
     * @return bool
     */
    public function buyZhuangBei($detail, $shopType)
    {
        try {
            $response = $this->client->request('GET', 'miniGame/buyZhuangbei', [RequestOptions::QUERY => [
                'openid' => $this->openId,
                'detail' => $detail,
                'shopType' => $shopType,
            ]]);
            $this->logger->info($content = $response->getBody()->getContents());
            $json = json_decode($content, true);
            if (isset($json['code']) && 3 == $json['code']) {
                return true;
            }
        } catch (GuzzleException | Exception $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }

        return false;
    }

    /**
     * @param string $gameType
     *
     * @return array
     */
    public function getShoppingList($gameType)
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
    public function curRaid()
    {
        $userInfo = $this->getUserInfo();

        return 1 == $userInfo['curRaid'];
    }

    /**
     * 附魔.
     *
     * @param int $bossLevel
     */
    public function fm($bossLevel)
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
            $this->buyFuMo(20);
        } elseif ($diff <= 30) {
            $this->buyFuMo(30);
        } elseif ($diff <= 40) {
            $this->buyFuMo(40);
        } elseif ($diff <= 50) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(30);
        } elseif ($diff <= 60) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(40);
        } elseif ($diff <= 70) {
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(40);
        } elseif ($diff <= 80) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(60);
        } elseif ($diff <= 90) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(40);
        } elseif ($diff <= 100) {
            $this->buyFuMo(100);
        } elseif ($diff <= 110) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(60);
        } elseif ($diff <= 120) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 130) {
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 140) {
            $this->buyFuMo(40);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 150) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 160) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(40);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 170) {
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(40);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 180) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(60);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 190) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(40);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 200) {
            $this->buyFuMo(40);
            sleep(3);
            $this->buyFuMo(60);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 210) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(60);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 220) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(40);
            sleep(3);
            $this->buyFuMo(60);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 230) {
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(40);
            sleep(3);
            $this->buyFuMo(60);
            sleep(3);
            $this->buyFuMo(100);
        } elseif ($diff <= 250) {
            $this->buyFuMo(20);
            sleep(3);
            $this->buyFuMo(30);
            sleep(3);
            $this->buyFuMo(40);
            sleep(3);
            $this->buyFuMo(60);
            sleep(3);
            $this->buyFuMo(100);
        }
    }
}
