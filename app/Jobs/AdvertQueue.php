<?php

namespace App\Jobs;

use App\Helpers\MiniGameClient;
use App\Models\Local\AdvertLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AdvertQueue implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    const QUEUE = 'advert_queue';

    /**
     * @var AdvertLog
     */
    protected $advertLog;

    /**
     * Create a new job instance.
     *
     * @param AdvertLog $advertLog
     */
    public function __construct(AdvertLog $advertLog)
    {
        $this->advertLog = $advertLog;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $instance = MiniGameClient::getInstance();
        if (0 == $this->advertLog->status) {
            $instance->addMoney($this->advertLog->open_id);
            $this->advertLog->update(['status' => 1]);
        }
        $instance->refreshCurRaidOverTime($this->advertLog->open_id);
    }
}
