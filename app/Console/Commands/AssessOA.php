<?php

namespace App\Console\Commands;

use App\Models\Assess\DeptList;
use App\Models\Assess\StaffList;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Env;

class AssessOA extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crontab:assess-oa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测评OA';

    /**
     * @var string
     */
    protected $url;

    /**
     * @var Client
     */
    protected $client;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->url = env('BASE_URL_ASSESS');
        $this->client = new Client(['base_uri' => $this->url, 'verify' => false]);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->syncDeptList();
        $this->syncDeptUser();
        $this->syncStaffDetail();
        $this->syncShopList();
        $this->syncUserList();

        $this->v2();
    }

    /**
     * 获取部门列表.
     */
    public function syncDeptList()
    {
        try {
            $response = $this->client->request('GET', 'index.php/oaapi/oaapi/deptList');
            echo $response->getBody()->getContents(), PHP_EOL;
        } catch (GuzzleException $exception) {
            echo $exception->getMessage(), PHP_EOL;
        }
    }

    /**
     * 获取部门下用户.
     *
     * @param string $deptId
     */
    public function syncDeptUser($deptId = null)
    {
        $requests = function ($deptId) {
            $deptIdArr = !is_null($deptId) ? [$deptId] : DeptList::get(['dept_id'])->pluck('dept_id');
            foreach ($deptIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/deptUser?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($deptId), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                echo $response->getBody()->getContents(), PHP_EOL;
            },
            'rejected' => function ($reason) {
                echo $reason->getMessage(), PHP_EOL;
            },
        ]);
        $pool->promise()->wait();
    }

    /**
     * 获取员工详情.
     *
     * @param string $staffId
     */
    public function syncStaffDetail($staffId = null)
    {
        $requests = function ($staffId) {
            $staffIdArr = !is_null($staffId) ? [$staffId] : StaffList::where('is_dimission', 1)
                ->get(['staff_id'])
                ->pluck('staff_id')
            ;
            foreach ($staffIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/staffDetail?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($staffId), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                echo $response->getBody()->getContents(), PHP_EOL;
            },
            'rejected' => function ($reason) {
                echo $reason->getMessage(), PHP_EOL;
            },
        ]);
        $pool->promise()->wait();
    }

    /**
     * 拉取店铺信息.
     */
    public function syncShopList()
    {
        $requests = function () {
            $platforms = ['Amazon', 'eBay', 'Aliexpress', 'shopify', 'Lazada'];
            foreach ($platforms as $value) {
                yield new Request('GET', 'index.php/oaapi/oaapi/getShopList?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                echo $response->getBody()->getContents(), PHP_EOL;
            },
            'rejected' => function ($reason) {
                echo $reason->getMessage(), PHP_EOL;
            },
        ]);
        $pool->promise()->wait();
    }

    /**
     * 测评用户.
     */
    public function syncUserList()
    {
        try {
            $response = $this->client->request('GET', 'index.php/oaapi/oaapi/userList');
            echo $response->getBody()->getContents(), PHP_EOL;
        } catch (GuzzleException $exception) {
            echo $exception->getMessage(), PHP_EOL;
        }
    }

    public function v2()
    {
        $client = new Client(['base_uri' => env('BASE_URL_ASSESS_V2'), 'verify' => false]);

        // 获取部门列表
        try {
            $client->request('GET', 'index.php/oaapi/oaapi/deptList');
        } catch (GuzzleException $exception) {
        }

        // 获取部门下用户
        $requests = function () {
            $deptIdArr = DeptList::get(['dept_id'])->pluck('dept_id');
            foreach ($deptIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/deptUser?id='.$value);
            }
        };
        (new Pool($client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                dump($response->getBody()->getContents());
            },
            'rejected' => function ($exception) {
                dump($exception->getMessage());
            },
        ]))->promise()->wait();

        // 获取员工详情
        $requests = function () {
            $staffIdArr = StaffList::where('is_dimission', 1)->get(['staff_id'])->pluck('staff_id');
            foreach ($staffIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/staffDetail?id='.$value);
            }
        };
        (new Pool($client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                dump($response->getBody()->getContents());
            },
            'rejected' => function ($exception) {
                dump($exception->getMessage());
            },
        ]))->promise()->wait();

        // 拉取店铺信息
        $requests = function () {
            $platforms = ['Amazon', 'eBay', 'Aliexpress', 'shopify', 'Lazada'];
            foreach ($platforms as $value) {
                yield new Request('GET', 'index.php/oaapi/oaapi/getShopList?id='.$value);
            }
        };
        (new Pool($client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                dump($response->getBody()->getContents());
            },
            'rejected' => function ($exception) {
                dump($exception->getMessage());
            },
        ]))->promise()->wait();

        // 测评用户
        try {
            $client->request('GET', 'index.php/oaapi/oaapi/userList');
        } catch (GuzzleException $exception) {
        }
    }
}
