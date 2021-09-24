<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EditorHelper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'editor-helper';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'generate ide-helper files';

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
        $this->call('clear-compiled');
        $this->call('ide-helper:generate');
        $this->call('ide-helper:meta');
        $this->call('ide-helper:models', ['--nowrite' => true]);
    }
}
