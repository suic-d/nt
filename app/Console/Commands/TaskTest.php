<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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

    public function deleteTemplate()
    {
        $client = new Client(['base_uri' => 'http://v2.product.nantang-tech.com', 'timeout' => 5]);

        try {
            $client->request('GET', 'index.php/api/v1/ExternalAPI/deleteTemplate');
        } catch (GuzzleException $exception) {
        }
    }
}
