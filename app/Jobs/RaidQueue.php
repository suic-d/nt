<?php

namespace App\Jobs;

use App\Helpers\MiniGameClient;
use App\Models\Local\AdvertLog;
use App\Models\Local\FMLog;
use App\Models\Local\RaidLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RaidQueue implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    const QUEUE = 'raid_queue';

    /**
     * @var RaidLog
     */
    protected $raidLog;

    /**
     * Create a new job instance.
     *
     * @param RaidLog $raidLog
     */
    public function __construct(RaidLog $raidLog)
    {
        $this->raidLog = $raidLog;
    }

    /**
     * Execute the job.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handle()
    {
        if (RaidLog::PENDING == $this->raidLog->status) {
            $this->raidLog->load(['fmLogs' => function ($query) {
                $query->where('status', FMLog::NONE);
            }]);
            $this->raidLog->load(['advertLogs' => function ($query) {
                $query->where('status', AdvertLog::NONE);
            }]);

            $instance = MiniGameClient::getInstance();
            if ($this->raidLog->fmLogs->isNotEmpty()) {
                foreach ($this->raidLog->fmLogs as $fm) {
                    $instance->buyFM($fm->open_id, $fm->level);
                    $fm->update(['status' => FMLog::COMPLETED]);
                    sleep(1);
                }
            }

            $instance->doRaid($this->raidLog->open_id, $this->raidLog->raid_id, $this->raidLog->boss_id);
            $this->raidLog->update(['status' => RaidLog::COMPLETED]);

            if ($this->raidLog->advertLogs->isNotEmpty()) {
                foreach ($this->raidLog->advertLogs as $ad) {
                    AdvertQueue::dispatch($ad)->onQueue(AdvertQueue::QUEUE)->delay(now()->addSeconds($ad->num * 30));
                    $ad->update(['status' => AdvertLog::PENDING]);
                }
            }
        }
    }
}
