<?php

namespace App\Console\Commands\Product;

use App\Models\StaffList;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class SyncStaffDetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:syncStaffDetail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取员工详情';

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
        $this->logger = new Logger('syncStaffDetail');
        $this->logger->pushHandler(new StreamHandler(storage_path('logs/syncStaffDetail.log'), Logger::INFO));
    }

    public function handle()
    {
        $requests = function () {
            $staffIdArr = StaffList::where('is_dimission', 1)->get(['staff_id'])->pluck('staff_id');
            foreach ($staffIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/staffDetail?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
                $this->logger->info($idx.' => '.$response->getBody()->getContents());
            },
            'rejected' => function ($reason, $idx) {
                $this->logger->error($idx.' => '.$reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }
}
