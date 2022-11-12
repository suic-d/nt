<?php

namespace App\Jobs;

use App\Helpers\MiniGameClient;
use App\Models\Local\AdvertLog;
use App\Models\Local\MissionLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MissionQueue implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    const QUEUE = 'mission_queue';

    /**
     * @var MissionLog
     */
    protected $missionLog;

    /**
     * Create a new job instance.
     */
    public function __construct(MissionLog $missionLog)
    {
        $this->missionLog = $missionLog;
    }

    /**
     * Execute the job.
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle()
    {
        if (MissionLog::PENDING == $this->missionLog->status) {
            $this->missionLog->load(['advertLogs' => function ($query) {
                $query->where('status', AdvertLog::NONE);
            }]);

            $instance = MiniGameClient::getInstance();
            $instance->doMission($this->missionLog->open_id, $this->missionLog->mission_id);

            if ($this->missionLog->advertLogs->isNotEmpty()) {
                foreach ($this->missionLog->advertLogs as $ad) {
                    AdvertQueue::dispatch($ad)->onQueue(AdvertQueue::QUEUE)->delay(now()->addSeconds($ad->num * 30));
                    $ad->update(['status' => AdvertLog::PENDING]);
                }
            }

            $this->missionLog->update(['status' => MissionLog::COMPLETED]);
        }
    }
}
