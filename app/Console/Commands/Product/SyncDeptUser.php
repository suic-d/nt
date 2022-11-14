<?php

namespace App\Console\Commands\Product;

use App\Models\DeptList;
use App\Traits\LoggerTrait;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class SyncDeptUser extends Command
{
    use LoggerTrait;

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
            $deptIdArr = DeptList::get(['dept_id'])->pluck('dept_id');
            foreach ($deptIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/deptUser?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
            },
            'rejected' => function ($reason, $idx) {
                $this->logger->error('dept_id = '.$idx.' '.$reason->getMessage());
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
