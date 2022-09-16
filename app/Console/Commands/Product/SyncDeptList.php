<?php

namespace App\Console\Commands\Product;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class SyncDeptList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:syncDeptList';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取部门列表';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(['base_uri' => env('BASE_URL'), 'verify' => false]);
        $this->logger = new Logger('syncDeptList');
        $this->logger->pushHandler(new StreamHandler(storage_path('logs/syncDeptList'), Logger::INFO));
    }

    public function handle()
    {
        try {
            $response = $this->client->request('GET', 'index.php/oaapi/oaapi/deptList');
            $this->logger->info($response->getBody()->getContents());
        } catch (GuzzleException $exception) {
            $this->logger->error($exception->getMessage());
        }
    }
}
