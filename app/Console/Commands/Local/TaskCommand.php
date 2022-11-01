<?php

namespace App\Console\Commands\Local;

use App\Helpers\BurningPlain;
use App\Helpers\WarSongGulch;
use Exception;
use Illuminate\Console\Command;

class TaskCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mg:task
                            {--sleep=5 : Number of seconds to sleep when no job is available}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {
            $this->doRun();

            $this->wait($this->option('sleep'));
        }
    }

    public function doRun()
    {
        try {
            (new WarSongGulch())->handle();
        } catch (Exception $exception) {
        }

        $this->wait(1);

        try {
            (new BurningPlain())->handle();
        } catch (Exception $exception) {
        }

        $this->line(__METHOD__.' - '.now()->format('Y-m-d H:i:s.u'));
    }

    /**
     * @param float|int $seconds
     */
    public function wait($seconds)
    {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }
}
