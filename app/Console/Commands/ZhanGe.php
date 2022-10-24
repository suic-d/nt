<?php

namespace App\Console\Commands;

use App\Helpers\Ran;
use Illuminate\Console\Command;

class ZhanGe extends Command
{
    const OPEN_ID = 'oFKYW5PdF4z0KlIw_60F99b-12b4';

    const URL = 'https://api.kenshinzb.top';

    const GAME_TYPE = '80';

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
        $instance = new Ran(self::URL, self::GAME_TYPE, self::OPEN_ID);
        $instance->setAdvance([
            // 黑曜石圣殿
            ['raid_id' => '86'],
            //奥妮克希亚的巢穴
            ['raid_id' => '85'],
        ]);
        $instance->handle();
    }
}
