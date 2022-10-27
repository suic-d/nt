<?php

namespace App\Helpers;

use App\Models\Local\Gear;

class BurningPlain
{
    /**
     * @var MiniGameClient
     */
    protected $miniGame;

    /**
     * @var string
     */
    protected $openId;

    /**
     * @var string
     */
    protected $gameType;

    /**
     * @var array
     */
    protected $advance;

    public function __construct()
    {
        $this->miniGame = MiniGameClient::getInstance();
        $this->openId = 'oFtFh44QflCoAbo_sISuylskfG04';
        $this->gameType = '60';
        $this->advance = config('raid.rs');
    }

    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handle()
    {
        if (!$this->miniGame->curRaidOver($this->openId) || $this->miniGame->curRaid($this->openId)) {
            return;
        }

        $this->putOn();
        sleep(1);
        $this->miniGame->clearBag($this->openId);
        sleep(1);

        if (!is_null($raid = $this->getRaid())) {
//            $this->miniGame->fm($this->openId, $raid->boss_level);
//            sleep(3);
            for ($i = 0; $i < MiniGameClient::MAX_TRIES; ++$i) {
                if ($this->miniGame->doRaid($this->openId, $raid->raid_id, $raid->boss_id)) {
                    sleep(3);
                    $this->miniGame->refreshCurRaidOverTime($this->openId);
//                    $this->miniGame->createAdvert($this->openId);

                    break;
                }
            }
        }
    }

    /**
     * 穿戴装备.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function putOn()
    {
        $userInfo = $this->miniGame->getUserInfo($this->openId);
        $zbList = array_column($userInfo['zbList'], 'id');
        if (!empty($zbList)) {
            foreach ($zbList as $v) {
                $this->miniGame->levelCount($this->openId, $v);
            }

            Gear::whereIn('zb_id', $zbList)->get()->each(function ($item) {
                $item->zb_got = 1;
                $item->save();
            });
        }
    }

    /**
     * 更新装备状态.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function updateRaidState()
    {
        $userInfo = $this->miniGame->getUserInfo($this->openId);
        // 已装备
        if (!empty($userInfo['bag'])) {
            Gear::whereIn('zb_id', $userInfo['bag'])->get()->each(function ($item) {
                $item->zb_got = 1;
                $item->save();
            });
        }

        // 未装备
        if (!empty($zbList = array_column($userInfo['zbList'], 'id'))) {
            Gear::whereIn('zb_id', $zbList)->get()->each(function ($item) {
                $item->zb_got = 1;
                $item->save();
            });
        }
    }

    /**
     * 更新副本.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function updateRaidList()
    {
        foreach ($this->miniGame->getRaidList($this->gameType) as $item) {
            foreach ($item['bossList'] as $boss) {
                foreach ($boss['zbList'] as $zb) {
                    $raid = Gear::where('zb_id', $zb['id'])->first();
                    if (is_null($raid)) {
                        $raid = new Gear();
                    }

                    $raid->game_type = $this->gameType;
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
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return null|Gear
     */
    public function getRaid(): ?Gear
    {
        $userInfo = $this->miniGame->getUserInfo($this->openId);

        if (!empty($this->advance)) {
            foreach ($this->advance as $v) {
                if (!isset($v['raid_id']) || empty($v['raid_id'])) {
                    continue;
                }

                if (isset($v['boss_id']) && !empty($v['boss_id'])) {
                    $bossIds = is_array($v['boss_id']) ? $v['boss_id'] : [$v['boss_id']];
                } else {
                    $bossIds = Gear::where('raid_id', $v['raid_id'])
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

                    $raid = Gear::where('raid_id', $v['raid_id'])
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
            $raid = Gear::where('game_type', $this->gameType)
                ->where('zb_got', 0)
                ->whereNotIn('boss_id', ['98', '99'])
                ->orderBy('boss_level')
                ->first()
            ;
            if (!is_null($raid)) {
                return $raid;
            }
        }

        return Gear::where('game_type', $this->gameType)
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
