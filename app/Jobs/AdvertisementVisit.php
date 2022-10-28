<?php

namespace App\Jobs;

use App\Helpers\MiniGameClient;
use App\Models\Local\AdvertQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AdvertisementVisit implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var AdvertQueue
     */
    protected $advertQueue;

    /**
     * Create a new job instance.
     */
    public function __construct(AdvertQueue $advertQueue)
    {
        $this->advertQueue = $advertQueue;
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
        if (0 == $this->advertQueue->status) {
            $instance->addMoney($this->advertQueue->open_id);
            $this->advertQueue->status = 1;
            $this->advertQueue->save();
        }

        $instance->refreshCurRaidOverTime($this->advertQueue->open_id);
    }
}
