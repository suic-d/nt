<?php

namespace App\Console\Commands;

use App\Helpers\Ran;
use App\Models\Local\AdvertQueue;
use App\Traits\MiniGame;
use Illuminate\Console\Command;

class AdvertVisit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ad:visit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '观看视频，领取奖励';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $adv = AdvertQueue::where('status', 0)
            ->where('expire_at', '<=', time())
            ->oldest()
            ->first()
        ;
        if (!is_null($adv)) {
            $instance = new Ran(env('MG_GAME_TYPE'));
            for ($i = 0; $i < MiniGame::$maxTries; ++$i) {
                if ($instance->addMoney()) {
                    $adv->status = 1;
                    $adv->save();

                    break;
                }
            }
        }
    }
}
