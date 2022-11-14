<?php

namespace App\Helpers;

use App\Jobs\MissionQueue;
use App\Jobs\RaidQueue;
use App\Models\Local\Buff;
use App\Models\Local\Mission;
use App\Models\Local\MissionLog;
use App\Models\Local\Raid;
use App\Models\Local\RaidLog;
use App\Models\Local\RaidOnce;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;
use Tightenco\Collect\Support\Arr;

abstract class MiniGameAbstract
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

    /**
     * @var array
     */
    protected $always;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    abstract public function handle();

    /**
     * @return MiniGameClient
     */
    public function getMiniGame(): MiniGameClient
    {
        if (!$this->miniGame) {
            $this->miniGame = MiniGameClient::getInstance();
        }

        return $this->miniGame;
    }

    /**
     * @return string
     */
    public function getOpenId(): string
    {
        return $this->openId;
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

    public function run()
    {
        try {
            if (!$this->curRaidOver() || $this->curRaid()) {
                return;
            }

            $this->putOn();
            $this->clearBag();

            if (!is_null($raid = $this->getRaid())) {
                $this->createRaidLog($raid);
                $this->setCurRaidOverTime(time() + $raid->raid_time);
            }
        } catch (InvalidArgumentException | Throwable | GuzzleException $exception) {
            $this->getLogger()->log(Logger::ERROR, $exception->getMessage());
        }
    }

    public function startMission()
    {
        try {
            if (!$this->curRaidOver() || $this->curRaid()) {
                return;
            }

            $this->putOn();
            $this->clearBag();

            if (!is_null($mission = $this->getMission())) {
                $this->createMissionLog($mission);
                $this->setCurRaidOverTime(time() + $mission->time);
            }
        } catch (InvalidArgumentException | GuzzleException | Throwable $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
    }

    /**
     * @return null|Mission
     */
    public function getMission(): ?Mission
    {
        return Mission::where('open_id', $this->openId)
            ->where('status', 0)
            ->orderBy('level')
            ->orderBy('time')
            ->first()
            ;
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @return string
     */
    public function getCurRaidTimeLeft()
    {
        $diff = $this->getMiniGame()->getCurRaidOverTime($this->openId) - time();
        if ($diff < 0) {
            $diff = 0;
        }

        $second = $diff % 60;
        $second = str_pad($second, 2, '0', STR_PAD_LEFT);

        $diff /= 60;
        $minute = $diff % 60;
        $minute = str_pad($minute, 2, '0', STR_PAD_LEFT);

        $diff /= 60;
        $hour = $diff % 60;
        $hour = str_pad($hour, 2, '0', STR_PAD_LEFT);

        return sprintf('%s时%s分%s秒', $hour, $minute, $second);
    }

    /**
     * @param string $gameType
     */
    public function setGameType(string $gameType): void
    {
        $this->gameType = $gameType;
    }

    /**
     * @param string $name
     * @param string $type
     *
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function buyGear(string $name, string $type)
    {
        $this->getMiniGame()->buyGear($this->openId, $name, $type);
    }

    /**
     * @param string $raidId
     * @param string $raidName
     * @param string $bossId
     * @param string $bossName
     */
    public function createRaidOnce(string $raidId, string $raidName, string $bossId, string $bossName)
    {
        RaidOnce::create([
            'open_id' => $this->openId,
            'raid_id' => $raidId,
            'raid_name' => $raidName,
            'boss_id' => $bossId,
            'boss_name' => $bossName,
        ]);
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function updateMissionList()
    {
        foreach ($this->getMiniGame()->getMissionList() as $item) {
            $data = [
                'open_id' => $this->openId,
                'mission_id' => $item['id'],
                'name' => $item['name'],
                'sw' => $item['sw'],
                'sw_val' => $item['swVal'],
                'level' => $item['level'],
                'time' => $item['times'],
            ];

            $mission = Mission::where('open_id', $this->openId)
                ->where('mission_id', $item['id'])
                ->first()
            ;
            is_null($mission) ? Mission::create($data) : $mission->update($data);
        }
    }

    /**
     * @param int $curRaidOverTime
     *
     * @throws InvalidArgumentException
     */
    public function setCurRaidOverTime(int $curRaidOverTime)
    {
        $this->getMiniGame()->setCurRaidOverTime($this->openId, $curRaidOverTime);
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    public function curRaidOver(): bool
    {
        return $this->getMiniGame()->curRaidOver($this->openId);
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    public function curRaid(): bool
    {
        return $this->getMiniGame()->curRaid($this->openId);
    }

    /**
     * @throws GuzzleException
     */
    public function clearBag()
    {
        $this->getMiniGame()->clearBag($this->openId);
    }

    /**
     * @param false $refresh
     *
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function getUserInfo(bool $refresh = false): array
    {
        return $this->getMiniGame()->getUserInfo($this->openId, $refresh);
    }

    /**
     * @param string $zbId
     *
     * @throws GuzzleException
     */
    public function levelCount(string $zbId)
    {
        $this->getMiniGame()->levelCount($this->openId, $zbId);
    }

    /**
     * @param string $buffId
     *
     * @throws GuzzleException
     *
     * @return array
     */
    public function getBuffList(string $buffId)
    {
        return $this->getMiniGame()->getBuffList($buffId);
    }

    /**
     * @throws GuzzleException
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function getRaidList()
    {
        return $this->getMiniGame()->getRaidList($this->gameType);
    }

    /**
     * 穿戴装备.
     *
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function putOn()
    {
        $userInfo = $this->getUserInfo();
        $zbList = array_column($userInfo['zbList'], 'id');
        if (!empty($zbList)) {
            foreach ($zbList as $v) {
                $this->levelCount($v);
            }

            Raid::where('open_id', $this->openId)->whereIn('zb_id', $zbList)->update(['zb_got' => 1]);
        }
    }

    /**
     * 更新装备状态.
     *
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function updateRaidState()
    {
        $userInfo = $this->getUserInfo();
        // 已装备
        if (!empty($userInfo['bag'])) {
            Raid::where('open_id', $this->openId)->whereIn('zb_id', $userInfo['bag'])->update(['zb_got' => 1]);
        }

        // 未装备
        if (!empty($zbList = array_column($userInfo['zbList'], 'id'))) {
            Raid::where('open_id', $this->openId)->whereIn('zb_id', $zbList)->update(['zb_got' => 1]);
        }
    }

    /**
     * 更新副本.
     *
     * @throws GuzzleException
     * @throws InvalidArgumentException
     */
    public function updateRaidList()
    {
        foreach ($this->getRaidList() as $item) {
            foreach ($item['bossList'] as $boss) {
                foreach ($boss['zbList'] as $zb) {
                    $data = [
                        'open_id' => $this->openId,
                        'game_type' => $this->gameType,
                        'raid_id' => $item['raidId'],
                        'raid_name' => $item['raidName'],
                        'raid_time' => $item['raid_time'],
                        'boss_id' => $boss['bossId'],
                        'boss_name' => $boss['bossName'],
                        'boss_level' => $boss['bossLevel'],
                        'buff' => $boss['buff'] ?? 0,
                        'zb_id' => $zb['id'],
                        'zb_name' => $zb['name'],
                        'zb_level' => $zb['level'],
                        'zb_color' => $zb['color'],
                        'drop_rate' => join(',', $zb['gailv']),
                        'gold' => $boss['goldDrop'],
                        'gong_zheng' => $boss['paiziDrop'] ?? 0,
                        'han_bing' => $boss['paizi80Drop'] ?? 0,
                    ];
                    $raid = Raid::where('open_id', $this->openId)->where('zb_id', $zb['id'])->first();
                    is_null($raid) ? Raid::create($data) : $raid->update($data);
                }
            }
        }
    }

    /**
     * @return null|Raid
     */
    public function getAlwaysRaid()
    {
        if (!empty($this->always) && isset($this->always['raid_id'], $this->always['boss_id'])) {
            return Raid::where('open_id', $this->openId)
                ->where('raid_id', $this->always['raid_id'])
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
    public function getAdvanceRaid()
    {
        if (!empty($this->advance)) {
            $userInfo = $this->getUserInfo();

            if (isset($userInfo['baodi']) && $userInfo['baodi'] > 20) {
                $raids = Raid::where('open_id', $this->openId)
                    ->where('zb_got', 0)
                    ->whereNotIn('boss_id', ['98', '99'])
                    ->orderBy('boss_level')
                    ->get()
                ;
            } else {
                $raids = Raid::where('open_id', $this->openId)
                    ->where('zb_got', 0)
                    ->orderBy('boss_level')
                    ->get()
                ;
            }
            $raidGroup = $raids->reduce(function ($carry, $item) {
                if (!isset($carry[$item->raid_id])) {
                    $carry[$item->raid_id] = [];
                }
                $carry[$item->raid_id][$item->boss_id] = $item;

                return $carry;
            }, []);
            unset($raids);

            foreach ($this->advance as $v) {
                if (!isset($v['raid_id']) || empty($v['raid_id'])) {
                    continue;
                }

                if (isset($v['boss_id']) && !empty($v['boss_id'])) {
                    if (is_array($v['boss_id'])) {
                        foreach ($v['boss_id'] as $bossId) {
                            if (isset($raidGroup[$v['raid_id']][$bossId])) {
                                return $raidGroup[$v['raid_id']][$bossId];
                            }
                        }
                    } else {
                        if (isset($raidGroup[$v['raid_id']][$v['boss_id']])) {
                            return $raidGroup[$v['raid_id']][$v['boss_id']];
                        }
                    }
                } else {
                    if (isset($raidGroup[$v['raid_id']])) {
                        return Arr::first($raidGroup[$v['raid_id']]);
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return null|Raid
     */
    public function getOnceRaid()
    {
        $raidOnce = RaidOnce::where('open_id', $this->openId)->orderBy('id')->first();
        if (!is_null($raidOnce)) {
            return Raid::where('open_id', $this->openId)
                ->where('raid_id', $raidOnce->raid_id)
                ->where('boss_id', $raidOnce->boss_id)
                ->orderBy('id')
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
    public function getRaid()
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

        $userInfo = $this->getUserInfo();
        if (isset($userInfo['baodi']) && $userInfo['baodi'] > 20) {
            $raid = Raid::where('open_id', $this->openId)
                ->where('zb_got', 0)
                ->whereNotIn('boss_id', ['98', '99'])
                ->orderBy('boss_level')
                ->orderBy('raid_time')
                ->first()
            ;
            if (!is_null($raid)) {
                return $raid;
            }
        }

        return Raid::where('open_id', $this->openId)
            ->where('zb_got', 0)
            ->orderBy('boss_level')
            ->orderBy('raid_time')
            ->first()
            ;
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
        foreach ($this->getBuffList(json_encode($buffIds)) as $bl) {
            $data = [
                'buff_id' => $bl['id'],
                'name' => $bl['name'],
                'buff_detail' => $bl['buffDetail'],
                'story' => $bl['story'],
                'level' => $bl['level'],
                'price' => $bl['price'],
                'paizi' => $bl['paizi'],
            ];

            $buff = Buff::where('buff_id', $bl['id'])->first();
            is_null($buff) ? Buff::create($data) : $buff->update($data);
        }
    }

    public static function timeFormat($time)
    {
        $format = '';

        $second = $time % 60;
        if (0 != $second) {
            $format = str_pad($second, 2, '0', STR_PAD_LEFT).'秒';
        }

        $time /= 60;
        $minute = $time % 60;
        if (0 != $minute) {
            $format = str_pad($minute, 2, '0', STR_PAD_LEFT).'分'.$format;
        }

        $time /= 60;
        $hour = $time % 60;
        if (0 != $hour) {
            $format = str_pad($hour, 2, '0', STR_PAD_LEFT).'小时'.$format;
        }

        return sprintf('%s小时%s分%s秒', $hour, $minute, $second);
    }

    /**
     * @return LoggerInterface
     */
    protected function createDefaultLogger()
    {
        $logger = new Logger($name = class_basename(static::class));
        $path = storage_path('logs').DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR.$name.'.log';
        $logger->pushHandler(new StreamHandler($path, Logger::INFO));

        return $logger;
    }

    /**
     * @param Mission $mission
     *
     * @throws Throwable
     */
    protected function createMissionLog(Mission $mission)
    {
        DB::beginTransaction();

        try {
            $missionLog = MissionLog::create([
                'open_id' => $this->openId,
                'mission_id' => $mission->mission_id,
                'name' => $mission->name,
            ]);

            // 广告
            for ($num = 1; $num <= 2; ++$num) {
                $missionLog->advertLogs()->create([
                    'open_id' => $this->openId,
                    'num' => $num,
                ]);
            }

            DB::commit();

            MissionQueue::dispatch($missionLog)->onQueue(MissionQueue::QUEUE);
            $missionLog->update(['status' => MissionLog::PENDING]);
        } catch (Exception $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    /**
     * @param Raid $raid
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Throwable
     */
    protected function createRaidLog($raid)
    {
        $fmToBuy = $this->getMiniGame()->fmToBuy($this->openId, $raid->boss_level);

        DB::beginTransaction();

        try {
            $raidLog = RaidLog::create([
                'game_type' => $raid->game_type,
                'open_id' => $this->openId,
                'raid_id' => $raid->raid_id,
                'raid_name' => $raid->raid_name,
                'boss_id' => $raid->boss_id,
                'boss_name' => $raid->boss_name,
            ]);
            if (!empty($fmToBuy)) {
                foreach ($fmToBuy as $lv) {
                    $raidLog->fmLogs()->create([
                        'open_id' => $this->openId,
                        'level' => $lv,
                    ]);
                }
            }

            // 广告
            for ($num = 1; $num <= 2; ++$num) {
                $raidLog->advertLogs()->create([
                    'open_id' => $this->openId,
                    'num' => $num,
                ]);
            }

            $raidOnce = RaidOnce::where('open_id', $this->openId)
                ->where('raid_id', $raid->raid_id)
                ->where('boss_id', $raid->boss_id)
                ->first()
            ;
            if (!is_null($raidOnce)) {
                $raidOnce->delete();
            }

            DB::commit();

            RaidQueue::dispatch($raidLog)->onQueue(RaidQueue::QUEUE);
            $raidLog->update(['status' => RaidLog::PENDING]);
        } catch (Exception $exception) {
            DB::rollBack();

            throw $exception;
        }
    }
}
