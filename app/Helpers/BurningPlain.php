<?php

namespace App\Helpers;

use App\Models\Local\Gear;
use App\Models\Local\RaidOnce;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Psr\SimpleCache\InvalidArgumentException;

class BurningPlain extends MiniGameAbstract
{
    public function __construct()
    {
        $this->setGameType(config('raid.burning_plain.game_type', ''));
        $this->openId = config('raid.burning_plain.open_id', '');
        $this->advance = config('raid.burning_plain.advance', []);
        $this->always = config('raid.burning_plain.always', []);
    }

    public function handle()
    {
        $this->run();
    }

    /**
     * 穿戴装备.
     *
     * @throws InvalidArgumentException
     * @throws GuzzleException
     */
    public function putOn()
    {
        $userInfo = $this->getMiniGame()->getUserInfo($this->openId);
        $zbList = array_column($userInfo['zbList'], 'id');
        if (!empty($zbList)) {
            foreach ($zbList as $v) {
                $this->getMiniGame()->levelCount($this->openId, $v);
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
     * @throws InvalidArgumentException
     * @throws GuzzleException
     */
    public function updateRaidState()
    {
        $userInfo = $this->getMiniGame()->getUserInfo($this->openId);
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
     * @throws InvalidArgumentException
     * @throws GuzzleException
     */
    public function updateRaidList()
    {
        foreach ($this->getMiniGame()->getRaidList($this->gameType) as $item) {
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
                    $raid->buff = $boss['buff'] ?? 0;
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
     * @return null|Gear
     */
    public function getAlwaysRaid(): ?Gear
    {
        if (!empty($this->always) && isset($this->always['raid_id'], $this->always['boss_id'])) {
            return Gear::where('raid_id', $this->always['raid_id'])
                ->where('boss_id', $this->always['boss_id'])
                ->first()
            ;
        }

        return null;
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @return null|Gear
     */
    public function getAdvanceRaid()
    {
        if (!empty($this->advance)) {
            $userInfo = $this->getMiniGame()->getUserInfo($this->openId);
            if (isset($userInfo['baodi']) && $userInfo['baodi'] > 20) {
                $gears = Gear::where('zb_got', 0)
                    ->whereNotIn('boss_id', ['98', '99'])
                    ->orderBy('boss_level')
                    ->get()
                ;
            } else {
                $gears = Gear::where('zb_got', 0)
                    ->orderBy('boss_level')
                    ->get()
                ;
            }
            $gearGroup = $gears->reduce(function ($carry, $item) {
                if (!isset($carry[$item->raid_id])) {
                    $carry[$item->raid_id] = [];
                }
                $carry[$item->raid_id][$item->boss_id] = $item;

                return $carry;
            }, []);

            foreach ($this->advance as $v) {
                if (!isset($v['raid_id']) || empty($v['raid_id'])) {
                    continue;
                }

                if (isset($v['boss_id']) && !empty($v['boss_id'])) {
                    if (is_array($v['boss_id'])) {
                        foreach ($v['boss_id'] as $bossId) {
                            if (isset($gearGroup[$v['raid_id']][$bossId])) {
                                return $gearGroup[$v['raid_id']][$bossId];
                            }
                        }
                    } else {
                        if (isset($gearGroup[$v['raid_id']][$v['boss_id']])) {
                            return $gearGroup[$v['raid_id']][$v['boss_id']];
                        }
                    }
                } else {
                    if (isset($gearGroup[$v['raid_id']])) {
                        return Arr::first($gearGroup[$v['raid_id']]);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @return null|Gear
     */
    public function getRaid(): ?Gear
    {
        if (!is_null($raid = $this->getAlwaysRaid())) {
            return $raid;
        }

        if (!is_null($raid = $this->getOnceRaid())) {
            return $raid;
        }

        if (!is_null($raid = $this->getAdvanceRaid())) {
            return $raid;
        }

        $userInfo = $this->getMiniGame()->getUserInfo($this->openId);
        if (isset($userInfo['baodi']) && $userInfo['baodi'] > 20) {
            $raid = Gear::where('zb_got', 0)
                ->whereNotIn('boss_id', ['98', '99'])
                ->orderBy('boss_level')
                ->first()
            ;
            if (!is_null($raid)) {
                return $raid;
            }
        }

        return Gear::where('zb_got', 0)
            ->orderBy('boss_level')
            ->first()
        ;
    }

    /**
     * @return null|Gear
     */
    public function getOnceRaid(): ?Gear
    {
        $raidOnce = RaidOnce::where('open_id', $this->openId)->orderBy('id')->first();
        if (!is_null($raidOnce)) {
            return Gear::where('raid_id', $raidOnce->raid_id)
                ->where('boss_id', $raidOnce->boss_id)
                ->orderBy('id')
                ->first()
            ;
        }

        return null;
    }
}
