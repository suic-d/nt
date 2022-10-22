<?php

namespace App\Console\Commands;

use App\Helpers\MiniGame;
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
        $config = [
            'game_type' => '80',
            'prioryty' => [
                // 魔枢 克莉斯塔萨
                ['raid_id' => '81', 'boss_id' => '4'],
                // 魔枢 斯托比德
                ['raid_id' => '81', 'boss_id' => '99'],
                // 乌特加德城堡 掠夺者因格瓦尔
                ['raid_id' => '80', 'boss_id' => '99'],
                // 安卡赫特-古代王国 耶戈达·觅影者
                ['raid_id' => '82', 'boss_id' => '3'],
                // 安卡赫特-古代王国 塔达拉姆王子
                ['raid_id' => '82', 'boss_id' => '2'],
                // 安卡赫特-古代王国 纳多克斯长老
                ['raid_id' => '82', 'boss_id' => '1'],
                // 魔枢 塑树者奥莫洛克
                ['raid_id' => '81', 'boss_id' => '3'],
                // 魔枢 阿诺玛鲁斯
                ['raid_id' => '81', 'boss_id' => '2'],
                // 魔枢 大魔导师泰蕾丝塔
                ['raid_id' => '81', 'boss_id' => '1'],
            ],
        ];
        (new MiniGame($config))->run();
    }
}
