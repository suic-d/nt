<?php

namespace App\Helpers;

use App\Jobs\MissionQueue;
use App\Jobs\RaidQueue;
use App\Models\Local\Gear;
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
     * @param Gear|Raid $raid
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
