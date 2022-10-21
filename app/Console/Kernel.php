<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     */
    protected function schedule(Schedule $schedule)
    {
        /** 商品中心 */
        // 禁上报表
        $schedule->command('product:publishForbidden')->dailyAt('10:00')->withoutOverlapping();
        // 更新各平台刊登信息
        $schedule->command('product:publishedList')->dailyAt('10:15')->withoutOverlapping();
        // 同步OA
        $schedule->command('product:syncDeptList')->dailyAt('12:00')->withoutOverlapping();
        $schedule->command('product:syncDeptUser')->dailyAt('12:05')->withoutOverlapping();
        $schedule->command('product:syncStaffDetail')->dailyAt('12:10')->withoutOverlapping();
        $schedule->command('product:syncProductUser')->dailyAt('12:15')->withoutOverlapping();
        // 钉钉审核
        $schedule->command('product:getProcessInstance')->everyFiveMinutes();

        /** 资产信息管理 */
        // 更新公司天眼查数据信息，每周二执行
        $schedule->command('asset:updateCompanyInfo')
            ->days([Schedule::TUESDAY])
            ->dailyAt('10:00')
            ->withoutOverlapping()
        ;

        // 战歌峡谷
        $schedule->command('zg:doRaid')->everyMinute();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
