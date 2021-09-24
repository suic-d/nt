<?php

namespace App\Console\Commands;

use App\Models\AssessDeptList;
use App\Models\AssessStaffList;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

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
    protected $url = 'http://assess.php.nantang-tech.com';

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
        if (is_null($deptId)) {
            $deptIdArr = AssessDeptList::get(['dept_id'])->pluck('dept_id');
        } else {
            $deptIdArr = [$deptId];
        }

        $requests = function () use ($deptIdArr) {
            $uri = 'index.php/oaapi/oaapi/deptUser';
            foreach ($deptIdArr as $value) {
                yield new Request('GET', $uri.'?id='.$value);
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
     * 获取员工详情.
     *
     * @param string $staffId
     */
    public function syncStaffDetail($staffId = null)
    {
        if (is_null($staffId)) {
            $staffIdArr = AssessStaffList::where('is_dimission', '!=', 2)->get(['staff_id'])->pluck('staff_id');
        } else {
            $staffIdArr = [$staffId];
        }

        $requests = function () use ($staffIdArr) {
            $uri = 'index.php/oaapi/oaapi/staffDetail';
            foreach ($staffIdArr as $value) {
                yield new Request('GET', $uri.'?id='.$value);
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
     * 拉取店铺信息.
     */
    public function syncShopList()
    {
        $platforms = ['Amazon', 'eBay', 'Aliexpress', 'shopify', 'Lazada'];
        $requests = function () use ($platforms) {
            $uri = 'index.php/oaapi/oaapi/getShopList';
            foreach ($platforms as $value) {
                yield new Request('GET', $uri.'?id='.$value);
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
}
