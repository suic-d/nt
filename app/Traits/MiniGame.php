<?php

namespace App\Traits;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Monolog\Logger;

trait MiniGame
{
    /**
     * @var int
     */
    public static $maxTries = 5;

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
     * @var string
     */
    protected $gameType;

    /**
     * @param bool $refresh
     *
     * @return array
     */
    public function getUserInfo(bool $refresh = false): array
    {
        if (!$refresh) {
            if (!empty($this->userInfo)) {
                return $this->userInfo;
            }
        }

        try {
            $response = $this->client->request('GET', 'miniGame/getUserInfo', [
                RequestOptions::QUERY => ['openid' => $this->openId],
            ]);
            $this->userInfo = json_decode($response->getBody()->getContents(), true)['data'] ?? [];
        } catch (GuzzleException $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
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
     *
     * @return bool
     */
    public function doRaid(string $raidId, string $bossId): bool
    {
        try {
            $response = $this->client->request('GET', 'miniGame/doRaid', [RequestOptions::QUERY => [
                'openid' => $this->openId,
                'raidId' => $raidId,
                'bossId' => $bossId,
            ]]);
            $this->logger->info(__METHOD__.' '.$response->getBody()->getContents());

            return true;
        } catch (GuzzleException $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }

        return false;
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
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return array
     */
    public function getFMList(): array
    {
        $key = 'framework'.DIRECTORY_SEPARATOR.'cache-'.sha1(__METHOD__);
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $fmList = $this->getBuffList(json_encode([299]));
        if (!empty($fmList)) {
            Cache::set($key, $fmList, 86400);
        }

        return $fmList;
    }

    /**
     * @param int $level
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function buyFM(int $level)
    {
        $map = array_column($this->getFMList(), null, 'level');
        if (isset($map[$level])) {
            for ($i = 0; $i < self::$maxTries; ++$i) {
                if ($this->buyZhuangBei(json_encode($map[$level], JSON_UNESCAPED_UNICODE), 'fm')) {
                    break;
                }
            }

            $userInfo = $this->getUserInfo(true);
            if (isset($userInfo['buffList'])) {
                $this->getBuffList(json_encode($userInfo['buffList']));
            }

            for ($i = 0; $i < self::$maxTries; ++$i) {
                if ($this->buffCount()) {
                    break;
                }
            }
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
     *
     * @return bool
     */
    public function buyZhuangBei(string $detail, string $shopType): bool
    {
        try {
            $response = $this->client->request('GET', 'miniGame/buyZhuangbei', [RequestOptions::QUERY => [
                'openid' => $this->openId,
                'detail' => $detail,
                'shopType' => $shopType,
            ]]);
            $this->logger->info($response->getBody()->getContents());

            return true;
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
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function fm(int $bossLevel)
    {
        $userInfo = $this->getUserInfo(true);
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

    /**
     * 看广告.
     *
     * @return bool
     */
    public function addMoney(): bool
    {
        try {
            $response = $this->client->request('GET', 'miniGame/addMoney', [
                RequestOptions::QUERY => ['openid' => $this->openId],
            ]);
            $this->logger->info(__METHOD__.' '.$response->getBody()->getContents());

            return true;
        } catch (GuzzleException $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }

        return false;
    }

    /**
     * 更新buff.
     *
     * @return bool
     */
    public function buffCount(): bool
    {
        try {
            $response = $this->client->request('GET', 'miniGame/buffCount', [
                RequestOptions::QUERY => ['openid' => $this->openId],
            ]);
            $this->logger->info(__METHOD__.' '.$response->getBody()->getContents());

            return true;
        } catch (GuzzleException | Exception $exception) {
            $this->logger->error(__METHOD__.' '.$exception->getMessage());
        }

        return false;
    }
}
