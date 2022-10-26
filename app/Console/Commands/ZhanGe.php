<?php

namespace App\Console\Commands;

use App\Helpers\Ran;
use Illuminate\Console\Command;

class ZhanGe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zg:doRaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '战歌峡谷';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $instance = new Ran(env('MG_GAME_TYPE'));
        $instance->setAdvance(config('raid.high'));
        $instance->handle();
    }
}
