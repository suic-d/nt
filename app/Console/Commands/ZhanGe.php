<?php

namespace App\Console\Commands;

use App\Models\Local\Raid;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ZhanGe extends Command
{
    const OPEN_ID = 'oFKYW5PdF4z0KlIw_60F99b-12b4';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zg:doRaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '战歌峡谷';

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

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(['base_uri' => 'https://api.kenshinzb.top', 'timeout' => 3]);
        $this->logger = new Logger('doRaid');
        $this->logger->pushHandler(new StreamHandler(
            storage_path('logs/'.date('Ymd').'/doRaid.log'),
            Logger::INFO
        ));
    }

    public function handle()
    {
        $this->begin();
    }

    public function begin()
    {
        if (!$this->raidOver()) {
            return;
        }

        $this->levelCount();
        $this->clearBag();
        $this->updateRaidList($this->currentVersion);
        $this->updateRaidState();

        $raid = Raid::where('game_type', $this->currentVersion)
            ->where('zb_got', 0)
            ->orderBy('boss_level')
            ->first()
        ;
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
     * @return array
     */
    public function getUserInfo()
    {
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
}
