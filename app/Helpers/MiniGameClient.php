<?php

namespace App\Helpers;

use App\Jobs\AdvertisementVisit;
use App\Models\Local\AdvertQueue;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class MiniGameClient
{
    const QUEUE_AD = 'mini_game_ad';

    const HTTP_TIMEOUT = 10;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CacheInterface
     */
    protected $store;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var self
     */
    private static $instance;

    public function __construct()
    {
        $this->url = env('MG_BASE_URL');
    }

    /**
     * @return MiniGameClient
     */
    public static function getInstance(): MiniGameClient
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $openId
     * @param false  $refresh
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return array
     */
    public function getUserInfo(string $openId, bool $refresh = false): array
    {
        $key = 'framework'.DIRECTORY_SEPARATOR.'cache-'.sha1(__METHOD__.$openId);
        if (!$refresh && Cache::has($key)) {
            return Cache::get($key);
        }

        $response = $this->getClient()->request('GET', 'miniGame/getUserInfo', [
            RequestOptions::QUERY => ['openid' => $openId],
        ]);
        $userInfo = json_decode($response->getBody()->getContents(), true)['data'];
        if (!empty($userInfo)) {
            // 缓存5分钟
            Cache::set($key, $userInfo, 300);
        }

        return $userInfo;
    }

    /**
     * 穿戴装备.
     *
     * @param string $openId
     * @param string $zbId
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function levelCount(string $openId, string $zbId)
    {
        $response = $this->getClient()->request('GET', 'miniGame/levelCount', [RequestOptions::QUERY => [
            'openid' => $openId,
            'zbId' => $zbId,
        ]]);
        $this->log(Logger::INFO, __METHOD__.' '.$response->getBody()->getContents());
    }

    /**
     * 清空背包.
     *
     * @param string $openId
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function clearBag(string $openId)
    {
        $response = $this->getClient()->request('GET', 'miniGame/clearBag', [
            RequestOptions::QUERY => ['openid' => $openId],
        ]);
        $this->log(Logger::INFO, __METHOD__.' '.$response->getBody()->getContents());
    }

    /**
     * 开始副本.
     *
     * @param string $openId
     * @param string $raidId
     * @param string $bossId
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function doRaid(string $openId, string $raidId, string $bossId)
    {
        $response = $this->getClient()->request('GET', 'miniGame/doRaid', [RequestOptions::QUERY => [
            'openid' => $openId,
            'raidId' => $raidId,
            'bossId' => $bossId,
        ]]);
        $this->log(Logger::INFO, __METHOD__.' '.$response->getBody()->getContents());
    }

    /**
     * 获取副本列表.
     *
     * @param string $gameType
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return array
     */
    public function getRaidList(string $gameType): array
    {
        $key = 'framework'.DIRECTORY_SEPARATOR.'cache-'.sha1(__METHOD__.$gameType);
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $response = $this->getClient()->request('GET', 'miniGame/getRaidList', [
            RequestOptions::QUERY => ['gameType' => $gameType],
        ]);
        $raidList = json_decode($response->getBody()->getContents(), true)['data'];
        if (!empty($raidList)) {
            // 缓存24小时
            Cache::set($key, $raidList, 86400);
        }

        return $raidList;
    }

    /**
     * 获取附魔列表.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
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
            // 缓存24小时
            Cache::set($key, $fmList, 86400);
        }

        return $fmList;
    }

    /**
     * @param string $buffId
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return array
     */
    public function getBuffList(string $buffId): array
    {
        $response = $this->getClient()->request('GET', 'miniGame/getBuffList', [
            RequestOptions::QUERY => ['buffId' => $buffId],
        ]);

        return json_decode($response->getBody()->getContents(), true)['data'];
    }

    /**
     * @param string $openId
     * @param string $detail
     * @param string $shopType
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function buyZhuangBei(string $openId, string $detail, string $shopType)
    {
        $response = $this->getClient()->request('GET', 'miniGame/buyZhuangbei', [RequestOptions::QUERY => [
            'openid' => $openId,
            'detail' => $detail,
            'shopType' => $shopType,
        ]]);
        $this->log(Logger::INFO, __METHOD__.' '.$detail);
        $this->log(Logger::INFO, __METHOD__.' '.$response->getBody()->getContents());
    }

    /**
     * @param string $openId
     * @param int    $level
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function buyFM(string $openId, int $level)
    {
        $map = array_column($this->getFMList(), null, 'level');
        $this->buyZhuangBei($openId, json_encode($map[$level], JSON_UNESCAPED_UNICODE), 'fm');

        $userInfo = $this->getUserInfo($openId, true);
        $this->getBuffList(json_encode($userInfo['buffList']));
        $this->buffCount($openId);
    }

    /**
     * 更新buff.
     *
     * @param string $openId
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function buffCount(string $openId)
    {
        $response = $this->getClient()->request('GET', 'miniGame/buffCount', [
            RequestOptions::QUERY => ['openid' => $openId],
        ]);
        $this->log(Logger::INFO, __METHOD__.' '.$response->getBody()->getContents());
    }

    /**
     * @param string $gameType
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return array
     */
    public function getShoppingList(string $gameType): array
    {
        $key = 'framework'.DIRECTORY_SEPARATOR.'cache-'.sha1(__METHOD__.$gameType);
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $response = $this->getClient()->request('GET', 'miniGame/getShoppingList', [
            RequestOptions::QUERY => ['gameType' => $gameType],
        ]);
        $shopList = json_decode($response->getBody()->getContents(), true)['data'];
        if (!empty($shopList)) {
            // 缓存24小时
            Cache::set($key, $shopList, 86400);
        }

        return $shopList;
    }

    /**
     * 看广告.
     *
     * @param string $openId
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function addMoney(string $openId)
    {
        $response = $this->getClient()->request('GET', 'miniGame/addMoney', [
            RequestOptions::QUERY => ['openid' => $openId],
        ]);
        $this->log(Logger::INFO, __METHOD__.' '.$response->getBody()->getContents());
    }

    /**
     * 任务列表.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return array
     */
    public function getMissionList(): array
    {
        $key = 'framework'.DIRECTORY_SEPARATOR.'cache-'.sha1(__METHOD__);
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $response = $this->getClient()->request('GET', 'miniGame/getRenwuList');
        $missionList = json_decode($response->getBody()->getContents(), true)['data'];
        if (!empty($missionList)) {
            // 缓存24小时
            Cache::set($key, $missionList, 86400);
        }

        return $missionList;
    }

    /**
     * @param string $openId
     * @param string $name
     * @param string $type
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function buyGear(string $openId, string $name, string $type)
    {
        $map = array_column($this->getShoppingList($type), null, 'name');
        if (!isset($map[$name])) {
            return;
        }

        $this->buyZhuangBei($openId, json_encode($map[$name], JSON_UNESCAPED_UNICODE), $type);
    }

    /**
     * @param string $openId
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return bool
     */
    public function curRaidOver(string $openId): bool
    {
        return time() >= $this->getCurRaidOverTime($openId);
    }

    /**
     * @param string $openId
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return int
     */
    public function getCurRaidOverTime(string $openId): int
    {
        $key = $this->getMutexName($openId);
        if (!$this->getStore()->has($key)) {
            $this->refreshCurRaidOverTime($openId);
        }

        return $this->getStore()->get($key);
    }

    /**
     * @param string $openId
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function refreshCurRaidOverTime(string $openId)
    {
        $userInfo = $this->getUserInfo($openId, true);
        $curRaidOverTime = (int) ceil($userInfo['curRaidOverTime'] / 1000);
        $this->setCurRaidOverTime($openId, $curRaidOverTime);
    }

    /**
     * @param string $openId
     * @param int    $curRaidOverTime
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function setCurRaidOverTime(string $openId, int $curRaidOverTime)
    {
        // 缓存24小时
        $this->getStore()->set($this->getMutexName($openId), $curRaidOverTime, 86400);
    }

    /**
     * @param string $openId
     *
     * @return string
     */
    public function getMutexName(string $openId): string
    {
        return 'framework'.DIRECTORY_SEPARATOR.'cache-'.sha1(__METHOD__.$openId);
    }

    /**
     * @param string $openId
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return bool
     */
    public function curRaid(string $openId): bool
    {
        $userInfo = $this->getUserInfo($openId, true);

        return 1 == $userInfo['curRaid'];
    }

    /**
     * @param string    $openId
     * @param int|int[] $level
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return int
     */
    public function batchFM(string $openId, $level): int
    {
        if (!is_array($level)) {
            $level = [$level];
        }

        foreach ($level as $v) {
            $this->buyFM($openId, $v);
            sleep(3);
        }

        return array_sum($level);
    }

    /**
     * 附魔.
     *
     * @param string $openId
     * @param int    $bossLevel
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return int
     */
    public function fm(string $openId, int $bossLevel): int
    {
        $userInfo = $this->getUserInfo($openId, true);
        $level = $userInfo['level'] + $userInfo['buff'];
        // 高于25，无需fm
        if ($level - $bossLevel > 25) {
            return 0;
        }

        $diff = $bossLevel + 25 - $level;
        // 无需fm
        if ($diff <= 0 || $diff > 250) {
            return 0;
        }
        if ($diff <= 20) {
            return $this->batchFM($openId, 20);
        }
        if ($diff > 20 && $diff <= 30) {
            return $this->batchFM($openId, 30);
        }
        if ($diff > 30 && $diff <= 40) {
            return $this->batchFM($openId, 40);
        }
        if ($diff > 40 && $diff <= 50) {
            return $this->batchFM($openId, [20, 30]);
        }
        if ($diff > 50 && $diff <= 60) {
            return $this->batchFM($openId, [20, 40]);
        }
        if ($diff > 60 && $diff <= 70) {
            return $this->batchFM($openId, [30, 40]);
        }
        if ($diff > 70 && $diff <= 80) {
            return $this->batchFM($openId, [20, 60]);
        }
        if ($diff > 80 && $diff <= 90) {
            return $this->batchFM($openId, [20, 30, 40]);
        }
        if ($diff > 90 && $diff <= 100) {
            return $this->batchFM($openId, 100);
        }
        if ($diff > 100 && $diff <= 110) {
            return $this->batchFM($openId, [20, 30, 60]);
        }
        if ($diff > 110 && $diff <= 120) {
            return $this->batchFM($openId, [20, 100]);
        }
        if ($diff > 120 && $diff <= 130) {
            return $this->batchFM($openId, [30, 100]);
        }
        if ($diff > 130 && $diff <= 140) {
            return $this->batchFM($openId, [40, 100]);
        }
        if ($diff > 140 && $diff <= 150) {
            return $this->batchFM($openId, [20, 30, 100]);
        }
        if ($diff > 150 && $diff <= 160) {
            return $this->batchFM($openId, [20, 40, 100]);
        }
        if ($diff > 160 && $diff <= 170) {
            return $this->batchFM($openId, [30, 40, 100]);
        }
        if ($diff > 170 && $diff <= 180) {
            return $this->batchFM($openId, [20, 60, 100]);
        }
        if ($diff > 180 && $diff <= 190) {
            return $this->batchFM($openId, [20, 30, 40, 100]);
        }
        if ($diff > 190 && $diff <= 200) {
            return $this->batchFM($openId, [40, 60, 100]);
        }
        if ($diff > 200 && $diff <= 210) {
            return $this->batchFM($openId, [20, 30, 60, 100]);
        }
        if ($diff > 210 && $diff <= 220) {
            return $this->batchFM($openId, [20, 40, 60, 100]);
        }
        if ($diff > 220 && $diff <= 230) {
            return $this->batchFM($openId, [30, 40, 60, 100]);
        }
        if ($diff > 230 && $diff <= 250) {
            return $this->batchFM($openId, [20, 30, 40, 60, 100]);
        }

        return 0;
    }

    /**
     * 创建广告队列.
     *
     * @param mixed $openId
     */
    public function createAdvert($openId)
    {
        // 广告1
        $adv1 = new AdvertQueue();
        $adv1->open_id = $openId;
        $adv1->num = 1;
        $adv1->expire_at = time() + 30;
        $adv1->save();
        AdvertisementVisit::dispatch($adv1)->onQueue(self::QUEUE_AD)->delay(now()->addSeconds(30));

        // 广告2
        $adv2 = new AdvertQueue();
        $adv2->open_id = $openId;
        $adv2->num = 2;
        $adv2->expire_at = time() + 60;
        $adv2->save();
        AdvertisementVisit::dispatch($adv2)->onQueue(self::QUEUE_AD)->delay(now()->addSeconds(60));
    }

    /**
     * @param int|string $level
     * @param string     $message
     * @param array      $context
     */
    public function log($level, string $message, array $context = [])
    {
        $this->getLogger()->log($level, $message, $context);
        $this->getLogger()->close();
    }

    /**
     * @return ClientInterface
     */
    public function getClient()
    {
        if (!$this->client) {
            $this->client = $this->createDefaultClient();
        }

        return $this->client;
    }

    /**
     * @return ClientInterface
     */
    public function createDefaultClient()
    {
        return new Client(['base_uri' => $this->url, 'verify' => false, 'timeout' => self::HTTP_TIMEOUT]);
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = $this->createDefaultLogger();
        }

        return $this->logger;
    }

    /**
     * @return LoggerInterface
     */
    public function createDefaultLogger()
    {
        $logger = new Logger($name = class_basename(__CLASS__));
        $path = storage_path('logs').DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR.$name.'.log';
        $logger->pushHandler(new StreamHandler($path, Logger::INFO));

        return $logger;
    }

    /**
     * @return CacheInterface
     */
    public function getStore(): CacheInterface
    {
        if (!$this->store) {
            $this->store = Cache::store('redis');
        }

        return $this->store;
    }
}
