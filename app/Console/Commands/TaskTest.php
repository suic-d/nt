<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * @internal
 * @coversNothing
 */
class TaskTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'task test';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
    }
}
