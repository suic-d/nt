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
        $schedule->command('crontab:publish_forbidden')->dailyAt('10:00')->withoutOverlapping();
        // 更新各平台刊登信息
        $schedule->command('crontab:spu_published_list')->dailyAt('10:15')->withoutOverlapping();
        // 同步OA
        $schedule->command('crontab:oa')->dailyAt('12:30')->withoutOverlapping();

        /** 资产信息管理 */
        // 更新公司天眼查数据信息，每周二、周五00:00执行
//        $schedule->command('crontab:update_company_info')->days([2, 5])->daily()->withoutOverlapping();

        /** 测评系统 */
        // 同步OA
        $schedule->command('crontab:assess-oa')->dailyAt('12:30')->withoutOverlapping();
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
