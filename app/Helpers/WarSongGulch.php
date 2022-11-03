<?php

namespace App\Helpers;

use App\Models\Local\Buff;
use App\Models\Local\Raid;
use App\Models\Local\RaidOnce;
use GuzzleHttp\Exception\GuzzleException;
use Psr\SimpleCache\InvalidArgumentException;

class WarSongGulch extends MiniGameAbstract
{
    public function __construct()
    {
        $this->setGameType(config('raid.war_song_gulch.game_type', ''));
        $this->openId = config('raid.war_song_gulch.open_id', '');
        $this->advance = config('raid.war_song_gulch.advance', []);
        $this->always = config('raid.war_song_gulch.always', []);
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

            Raid::whereIn('zb_id', $zbList)->get()->each(function ($item) {
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
     * @throws InvalidArgumentException
     * @throws GuzzleException
     */
    public function updateRaidList()
    {
        foreach ($this->getMiniGame()->getRaidList($this->gameType) as $item) {
            foreach ($item['bossList'] as $boss) {
                foreach ($boss['zbList'] as $zb) {
                    $raid = Raid::where('zb_id', $zb['id'])->first();
                    if (is_null($raid)) {
                        $raid = new Raid();
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
     * @return null|Raid
     */
    public function getAlwaysRaid(): ?Raid
    {
        if (!empty($this->always) && isset($this->always['raid_id'], $this->always['boss_id'])) {
            return Raid::where('raid_id', $this->always['raid_id'])
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
     * @return null|Raid
     */
    public function getAdvanceRaid(): ?Raid
    {
        if (!empty($this->advance)) {
            $userInfo = $this->getMiniGame()->getUserInfo($this->openId);

            foreach ($this->advance as $v) {
                if (!isset($v['raid_id']) || empty($v['raid_id'])) {
                    continue;
                }

                if (isset($v['boss_id']) && !empty($v['boss_id'])) {
                    $bossIds = is_array($v['boss_id']) ? $v['boss_id'] : [$v['boss_id']];
                } else {
                    $bossIds = Raid::where('raid_id', $v['raid_id'])
                        ->where('zb_got', 0)
                        ->orderBy('boss_level')
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

        return null;
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @return null|Raid
     */
    public function getRaid(): ?Raid
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
            $raid = Raid::where('zb_got', 0)
                ->whereNotIn('boss_id', ['98', '99'])
                ->orderBy('boss_level')
                ->first()
            ;
            if (!is_null($raid)) {
                return $raid;
            }
        }

        return Raid::where('zb_got', 0)
            ->orderBy('boss_level')
            ->first()
        ;
    }

    /**
     * @return null|Raid
     */
    public function getOnceRaid(): ?Raid
    {
        $raidOnce = RaidOnce::where('open_id', $this->openId)->orderBy('id')->first();
        if (!is_null($raidOnce)) {
            return Raid::where('raid_id', $raidOnce->raid_id)
                ->where('boss_id', $raidOnce->boss_id)
                ->orderBy('id')
                ->first()
            ;
        }

        return null;
    }

    /**
     * @throws GuzzleException
     */
    public function updateBuff()
    {
        $buffIds = Raid::where('buff', '!=', 0)
            ->distinct()
            ->get(['buff'])
            ->pluck('buff')
            ->toArray()
        ;
        $buffList = $this->getMiniGame()->getBuffList(json_encode($buffIds));
        foreach ($buffList as $bl) {
            $buff = Buff::where('buff_id', $bl['id'])->first();
            if (is_null($buff)) {
                $buff = new Buff();
            }

            $buff->buff_id = $bl['id'];
            $buff->name = $bl['name'];
            $buff->buff_detail = $bl['buffDetail'];
            $buff->story = $bl['story'];
            $buff->level = $bl['level'];
            $buff->price = $bl['price'];
            $buff->paizi = $bl['paizi'];
            $buff->save();
        }
    }
}
