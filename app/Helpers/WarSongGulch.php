<?php

namespace App\Helpers;

use App\Models\Local\Raid;
use App\Models\Local\RaidOnce;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\SimpleCache\InvalidArgumentException;

class WarSongGulch extends MiniGameAbstract
{
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
        $this->openId = env('MG_OPEN_ID');
        $this->gameType = '80';
        $this->advance = config('raid.zg');
    }

    public function handle()
    {
        try {
            if (!$this->getMiniGame()->curRaidOver($this->openId) || $this->getMiniGame()->curRaid($this->openId)) {
                return;
            }

            $this->putOn();
            sleep(1);
            $this->getMiniGame()->clearBag($this->openId);
            sleep(1);

            if (!is_null($raid = $this->getRaid())) {
                $this->getMiniGame()->fm($this->openId, $raid->boss_level);
                sleep(3);
                $this->getMiniGame()->doRaid($this->openId, $raid->raid_id, $raid->boss_id);
                $this->getMiniGame()->createAdvert($this->openId);
                sleep(3);
                $this->getMiniGame()->refreshCurRaidOverTime($this->openId);
            }
        } catch (InvalidArgumentException | GuzzleException | Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
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
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @return null|Raid
     */
    public function getRaid(): ?Raid
    {
        if (!is_null($raid = $this->getRaidOnce())) {
            return $raid;
        }

        $userInfo = $this->getMiniGame()->getUserInfo($this->openId);

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
     * @return null|Raid
     */
    public function getRaidOnce(): ?Raid
    {
        $raidOnce = RaidOnce::where('open_id', $this->openId)->orderBy('id')->first();
        if (!is_null($raidOnce)) {
            $raid = Raid::where('raid_id', $raidOnce->raid_id)
                ->where('boss_id', $raidOnce->boss_id)
                ->orderBy('id')
                ->first()
            ;
            $raidOnce->delete();

            return $raid;
        }

        return null;
    }
}
