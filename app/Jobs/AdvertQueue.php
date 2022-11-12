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
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function handle()
    {
        $instance = MiniGameClient::getInstance();
        if (AdvertLog::PENDING == $this->advertLog->status) {
            $instance->addMoney($this->advertLog->open_id);
            $this->advertLog->update(['status' => AdvertLog::COMPLETED]);
        }
        $instance->refreshCurRaidOverTime($this->advertLog->open_id);
    }
}
