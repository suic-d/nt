<?php

namespace App\Helpers;

use App\Models\Local\Raid;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Cache;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class MiniGame
{
    const OPEN_ID = 'oFKYW5PdF4z0KlIw_60F99b-12b4';

    /**
     * @var string
     */
    protected $url = 'https://api.kenshinzb.top';

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
    protected $raidList;

    /**
     * @var string
     */
    protected $currentVersion = '80';

    /**
     * @param string $currentVersion
     */
    public function __construct($currentVersion = '')
    {
        if (!empty($currentVersion)) {
            $this->currentVersion = $currentVersion;
        }

        $this->client = new Client(['base_uri' => $this->url, 'verify' => false, 'timeout' => 5]);

        $this->logger = new Logger('MiniGame');
        $this->logger->pushHandler(new StreamHandler(
            storage_path('logs/'.date('Ymd').'/MiniGame.log'),
            Logger::INFO
        ));
    }

    public function run()
    {
        if (!$this->raidOver()) {
            return;
        }

        $this->levelCount();
        $this->clearBag();

        $raid = Raid::where('game_type', $this->currentVersion)
            ->where('prioryty', '!=', 0)
            ->where('zb_got', 0)
            ->orderByDesc('prioryty')
            ->first()
        ;
        if (is_null($raid)) {
            $raid = Raid::where('game_type', $this->currentVersion)
                ->where('zb_got', 0)
                ->orderBy('boss_level')
                ->first()
            ;
        }

        if (!is_null($raid)) {
            $this->doRaid($raid->raid_id, $raid->boss_id);
        }
    }

    /**
     * 背包中的装备添加到图鉴.
     */
    public function levelCount()
    {
        $userInfo = $this->getUserInfo();
        foreach ($userInfo['zbList'] as $zb) {
            try {
                $this->client->request('GET', 'miniGame/levelCount', [
                    RequestOptions::QUERY => ['openid' => self::OPEN_ID, 'zbId' => $zb['id']],
                ]);

                $raid = Raid::where('zb_id', $zb['id'])->first();
                if (!is_null($raid)) {
                    $raid->zb_got = 1;
                    $raid->save();
                }
            } catch (GuzzleException | Exception $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
    }

    /**
     * 清空背包.
     */
    public function clearBag()
    {
        try {
            $this->client->request('GET', 'miniGame/clearBag', [
                RequestOptions::QUERY => ['openid' => self::OPEN_ID],
            ]);
        } catch (GuzzleException $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * 更新已获得装备.
     */
    public function updateRaidState()
    {
        $userInfo = $this->getUserInfo();
        // 已装备
        foreach ($userInfo['bag'] as $v) {
            $raid = Raid::where('zb_id', $v)->first();
            if (!is_null($raid)) {
                $raid->zb_got = 1;
                $raid->save();
            }
        }

        // 未装备
        foreach ($userInfo['zbList'] as $zb) {
            $raid = Raid::where('zb_id', $zb['id'])->first();
            if (!is_null($raid)) {
                $raid->zb_got = 1;
                $raid->save();
            }
        }
    }

    /**
     * 更新副本装备列表.
     *
     * @param string $gameType
     */
    public function updateRaidList($gameType)
    {
        $list = $this->getRaidList($gameType);
        foreach ($list as $item) {
            foreach ($item['bossList'] as $boss) {
                foreach ($boss['zbList'] as $zb) {
                    $raid = Raid::where('zb_id', $zb['id'])->first();
                    if (is_null($raid)) {
                        $raid = new Raid();
                    }

                    $raid->game_type = $gameType;
                    $raid->raid_id = $item['raidId'];
                    $raid->raid_name = $item['raidName'];
                    $raid->raid_time = $item['raidTime'];
                    $raid->boss_id = $boss['bossId'];
                    $raid->boss_name = $boss['bossName'];
                    $raid->boss_level = $boss['bossLevel'];
                    $raid->gold = $boss['goldDrop'];
                    $raid->gong_zheng = $boss['paiziDrop'] ?? 0;
                    $raid->han_bing = $boss['paizi80Drop'] ?? 0;
                    $raid->zb_id = $zb['id'];
                    $raid->zb_name = $zb['name'];
                    $raid->zb_level = $zb['level'];
                    $raid->zb_color = $zb['color'];
                    $raid->drop_rate = join(',', $zb['gailv']);
                    $raid->save();
                }
            }
        }
    }

    /**
     * 副本是否结束.
     *
     * @return bool
     */
    public function raidOver()
    {
        $userInfo = $this->getUserInfo();
        $curRaidOverTime = (int) ceil($userInfo['curRaidOverTime'] / 1000);

        return time() >= $curRaidOverTime;
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
            $response = $this->client->request('GET', 'miniGame/doRaid', [
                RequestOptions::QUERY => [
                    'openid' => self::OPEN_ID,
                    'raidId' => $raidId,
                    'bossId' => $bossId,
                ],
            ]);
            $this->logger->info($response->getBody()->getContents());
        } catch (GuzzleException $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * 获取用户信息.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return array
     */
    public function getUserInfo()
    {
//        $key = 'framework'.DIRECTORY_SEPARATOR.'cache-'.sha1(__METHOD__);
//        if (Cache::has($key)) {
//            return Cache::get($key);
//        }

        if (empty($this->userInfo)) {
            try {
                $response = $this->client->request('GET', 'miniGame/getUserInfo', [
                    RequestOptions::QUERY => ['openid' => self::OPEN_ID],
                ]);

                $this->userInfo = json_decode($response->getBody()->getContents(), true)['data'] ?? [];
            } catch (GuzzleException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }

//        if (!empty($this->userInfo)) {
//            // 缓存5分钟
//            Cache::set($key, $this->userInfo, 300);
//        }

        return $this->userInfo;
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
        if (empty($this->raidList)) {
            try {
                $response = $this->client->request('GET', 'miniGame/getRaidList', [
                    RequestOptions::QUERY => ['gameType' => $gameType],
                ]);

                $this->raidList = json_decode($response->getBody()->getContents(), true)['data'] ?? [];
            } catch (GuzzleException $exception) {
                $this->logger->error($exception->getMessage());
            }
        }

        return $this->raidList;
    }

    /**
     * @param int $level
     *
     * @return bool
     */
    public function buyFuMo($level)
    {
        $detail = $this->getFuMoDetail($level);
        if (empty($detail)) {
            return false;
        }

        return $this->buyZhuangBei($this->getFuMoDetail(30), 'fm');
    }

    /**
     * @param int $level
     *
     * @return string
     */
    public function getFuMoDetail($level)
    {
        $map = array_column($this->getBuffList(json_encode([299])), null, 'level');
        if (isset($map[$level])) {
            return json_encode($map[$level], JSON_UNESCAPED_UNICODE);
        }

        return '';
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
            $this->logger->error($exception->getMessage());
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
                'openid' => self::OPEN_ID,
                'detail' => $detail,
                'shopType' => $shopType,
            ]]);
            $this->logger->info($content = $response->getBody()->getContents());
            $json = json_decode($content, true);
            if (isset($json['code']) && 3 == $json['code']) {
                return true;
            }
        } catch (GuzzleException | Exception $exception) {
            $this->logger->error($exception->getMessage());
        }

        return false;
    }
}
