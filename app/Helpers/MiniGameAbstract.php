<?php

namespace App\Helpers;

use App\Jobs\RaidQueue;
use App\Models\Local\Gear;
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
            if (!$this->getMiniGame()->curRaidOver($this->openId) || $this->getMiniGame()->curRaid($this->openId)) {
                return;
            }

            $this->putOn();
            $this->getMiniGame()->clearBag($this->openId);

            if (!is_null($raid = $this->getRaid())) {
                $this->createRaidLog($raid);

                $this->getMiniGame()->setCurRaidOverTime($this->openId, time() + $raid->raid_time);
            }
        } catch (InvalidArgumentException | Throwable | GuzzleException $exception) {
            $this->getLogger()->log(Logger::ERROR, $exception->getMessage());
        }
    }

    public function start()
    {
        try {
            if (!$this->getMiniGame()->curRaidOver($this->openId) || $this->getMiniGame()->curRaid($this->openId)) {
                return;
            }

            $this->putOn();
            $this->getMiniGame()->clearBag($this->openId);

            if (!is_null($raid = $this->getRaid())) {
                $this->getMiniGame()->fm($this->openId, $raid->boss_level);
                sleep(1);
                $this->getMiniGame()->doRaid($this->openId, $raid->raid_id, $raid->boss_id);
                $this->getMiniGame()->createAdvert($this->openId);
                sleep(1);
                $this->getMiniGame()->refreshCurRaidOverTime($this->openId);
            }
        } catch (InvalidArgumentException | GuzzleException | Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
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
        } catch (Exception $exception) {
            DB::rollBack();

            throw $exception;
        }
    }
}
