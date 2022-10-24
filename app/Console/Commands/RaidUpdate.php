<?php

namespace App\Console\Commands;

use App\Helpers\MiniGame;
use Illuminate\Console\Command;

class RaidUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'raid:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '巫妖王之怒';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        (new MiniGame(['game_type' => '60']))->updateRaidList();
        (new MiniGame(['game_type' => '70']))->updateRaidList();
        (new MiniGame(['game_type' => '80']))->updateRaidList();
    }
}
