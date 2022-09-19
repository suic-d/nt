<?php

namespace App\Console\Commands\Product;

use App\Models\DeptList;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class SyncDeptUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:syncDeptUser';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取部门下用户';

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
        $this->logger = new Logger('syncDeptUser');
        $this->logger->pushHandler(new StreamHandler(storage_path('logs/syncDeptUser.log'), Logger::INFO));
    }

    public function handle()
    {
        $requests = function () {
            $deptIdArr = DeptList::get(['dept_id'])->pluck('dept_id');
            foreach ($deptIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/deptUser?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
                $this->logger->info('dept_id = '.$idx.' '.$response->getBody()->getContents());
                $this->logger->close();
            },
            'rejected' => function ($reason, $idx) {
                $this->logger->error('dept_id = '.$idx.' '.$reason->getMessage());
                $this->logger->close();
            },
        ]);
        $pool->promise()->wait();
    }
}
