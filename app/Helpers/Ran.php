<?php

namespace App\Helpers;

use App\Models\Local\Raid;
use App\Traits\MiniGame;
use GuzzleHttp\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Ran
{
    use MiniGame;

    const QUEUE_AD = 'mini_game_ad';

    /**
     * @var array
     */
    protected $advance;

    /**
     * Create a new command instance.
     *
     * @param string $gameType
     */
    public function __construct(string $gameType)
    {
        $this->url = env('MG_BASE_URL');
        $this->gameType = $gameType;
        $this->openId = env('MG_OPEN_ID');

        $this->client = new Client(['base_uri' => $this->url, 'verify' => false, 'timeout' => 5]);
        $this->logger = new Logger('MiniGame');
        $this->logger->pushHandler(new StreamHandler(
            storage_path('logs/'.date('Ymd').'/MiniGame.log'),
            Logger::INFO
        ));
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handle()
    {
        if ($this->hasMutex() || $this->curRaid()) {
            return;
        }

        $this->putOn();
        sleep(1);
        $this->clearBag();
        sleep(1);

        if (!is_null($raid = $this->getRaid())) {
            $this->fm($raid->boss_level);
            sleep(3);
            for ($i = 0; $i < self::$maxTries; ++$i) {
                if ($this->doRaid($raid->raid_id, $raid->boss_id)) {
                    break;
                }
            }
            sleep(3);

            $this->createAdvert();
            $this->setMutex();
        }
    }

    /**
     * 更新装备状态.
     */
    public function updateRaidState()
    {
        $userInfo = $this->getUserInfo();
        // 已装备
        if (!empty($userInfo['bag'])) {
            Raid::whereIn('zb_id', $userInfo['bag'])->get()->each(function ($item) {
                $item->zb_got = 1;
                $item->save();
            });
        }

        // 未装备
        if (!empty($zbList = array_column($userInfo['zbList'], 'id'))) {
            Raid::whereIn('zb_id', $zbList)->get()->each(function ($item) {
                $item->zb_got = 1;
                $item->save();
            });
        }
    }

    /**
     * 更新副本.
     *
     * @param string $gameType
     */
    public function updateRaidList(string $gameType)
    {
        foreach ($this->getRaidList($gameType) as $item) {
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
     * 穿戴装备.
     */
    public function putOn()
    {
        $userInfo = $this->getUserInfo();
        $zbList = array_column($userInfo['zbList'], 'id');
        if (!empty($zbList)) {
            foreach ($zbList as $v) {
                $this->levelCount($v);
            }

            Raid::whereIn('zb_id', $zbList)->get()->each(function ($item) {
                $item->zb_got = 1;
                $item->save();
            });
        }
    }

    /**
     * @return null|Raid
     */
    public function getRaid(): ?Raid
    {
        $userInfo = $this->getUserInfo();

        if (!empty($this->advance)) {
            foreach ($this->advance as $v) {
                if (!isset($v['raid_id']) || empty($v['raid_id'])) {
                    continue;
                }

                if (isset($v['boss_id']) && !empty($v['boss_id'])) {
                    $bossIds = is_array($v['boss_id']) ? $v['boss_id'] : [$v['boss_id']];
                } else {
                    $bossIds = Raid::where('raid_id', $v['raid_id'])
                        ->where('zb_got', 0)
                        ->distinct()
                        ->get(['boss_id'])
                        ->pluck('boss_id')
                        ->toArray()
                    ;
                }

                foreach ($bossIds as $bossId) {
                    if (isset($userInfo['baodi']) && $userInfo['baodi'] > 20 && in_array($bossId, ['98', '99'])) {
                        continue;
                    }

                    $raid = Raid::where('raid_id', $v['raid_id'])
                        ->where('boss_id', $bossId)
                        ->where('zb_got', 0)
                        ->first()
                    ;
                    if (!is_null($raid)) {
                        return $raid;
                    }
                }
            }
        }

        if (isset($userInfo['baodi']) && $userInfo['baodi'] > 20) {
            $raid = Raid::where('game_type', $this->gameType)
                ->where('zb_got', 0)
                ->whereNotIn('boss_id', ['98', '99'])
                ->orderBy('boss_level')
                ->first()
            ;
            if (!is_null($raid)) {
                return $raid;
            }
        }

        return Raid::where('game_type', $this->gameType)
            ->where('zb_got', 0)
            ->orderBy('boss_level')
            ->first()
            ;
    }

    /**
     * @param array $advance
     */
    public function setAdvance(array $advance)
    {
        $this->advance = $advance;
    }
}
