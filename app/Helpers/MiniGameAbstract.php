<?php

namespace App\Helpers;

use App\Jobs\RaidQueue;
use App\Models\Local\Gear;
use App\Models\Local\Raid;
use App\Models\Local\RaidLog;
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
            sleep(1);
            $this->getMiniGame()->clearBag($this->openId);
            sleep(1);

            if (!is_null($raid = $this->getRaid())) {
                $this->createRaidLog($raid);

                $this->getMiniGame()->setCurRaidOverTime($this->openId, $raid->raid_time);
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

            // 广告1
            $raidLog->advertLogs()->create([
                'open_id' => $raidLog->open_id,
                'num' => 1,
            ]);

            // 广告2
            $raidLog->advertLogs()->create([
                'open_id' => $raidLog->open_id,
                'num' => 2,
            ]);

            DB::commit();

            RaidQueue::dispatch($raidLog)->onQueue(RaidQueue::QUEUE);
        } catch (Exception $exception) {
            DB::rollBack();

            throw $exception;
        }
    }
}
