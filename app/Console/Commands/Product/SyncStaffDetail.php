<?php

namespace App\Console\Commands\Product;

use App\Models\StaffList;
use App\Traits\LoggerTrait;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class SyncStaffDetail extends Command
{
    use LoggerTrait;

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
     * @var ClientInterface
     */
    protected $client;

    public function __construct()
    {
        parent::__construct();

        $this->createDefaultClient();
        $this->createDefaultLogger();
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
            },
            'rejected' => function ($reason, $idx) {
                $this->logger->error('staff_id = '.$idx.' '.$reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }

    /**
     * @return ClientInterface
     */
    protected function createDefaultClient()
    {
        if (!$this->client) {
            $this->client = new Client(['base_uri' => env('BASE_URL'), 'verify' => false]);
        }

        return $this->client;
    }
}
