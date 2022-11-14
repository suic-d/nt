<?php

namespace App\Console\Commands\AssessV2;

use App\Models\Assess\DeptList;
use App\Models\Assess\StaffList;
use App\Traits\ClientTrait;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class OA extends Command
{
    use ClientTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assess-v2:oa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测评OA';

    public function __construct()
    {
        parent::__construct();

        $this->url = env('BASE_URL_ASSESS_V2');
    }

    public function handle()
    {
        $this->syncDeptList();
        $this->syncDeptUser();
        $this->syncStaffDetail();
        $this->syncShopList();
        $this->syncUserList();
    }

    /**
     * 测评用户.
     */
    public function syncUserList()
    {
        try {
            $response = $this->getClient()->request('GET', 'index.php/oaapi/oaapi/userList');
            echo $response->getBody()->getContents(), PHP_EOL;
        } catch (GuzzleException $exception) {
            echo $exception->getMessage(), PHP_EOL;
        }
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
        $pool = new Pool($this->getClient(), $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                echo $response->getBody()->getContents(), PHP_EOL;
            },
            'rejected' => function ($exception) {
                echo $exception->getMessage(), PHP_EOL;
            },
        ]);
        $pool->promise()->wait();
    }

    /**
     * 获取员工详情.
     */
    public function syncStaffDetail()
    {
        $requests = function () {
            $staffIdArr = StaffList::where('is_dimission', 1)
                ->get(['staff_id'])
                ->pluck('staff_id')
            ;
            foreach ($staffIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/staffDetail?id='.$value);
            }
        };
        $pool = new Pool($this->getClient(), $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                echo $response->getBody()->getContents(), PHP_EOL;
            },
            'rejected' => function ($exception) {
                echo $exception->getMessage(), PHP_EOL;
            },
        ]);
        $pool->promise()->wait();
    }

    /**
     * 获取部门下用户.
     */
    public function syncDeptUser()
    {
        $requests = function () {
            $deptIdArr = DeptList::get(['dept_id'])->pluck('dept_id');
            foreach ($deptIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/deptUser?id='.$value);
            }
        };
        $pool = new Pool($this->getClient(), $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                echo $response->getBody()->getContents(), PHP_EOL;
            },
            'rejected' => function ($exception) {
                echo $exception->getMessage(), PHP_EOL;
            },
        ]);
        $pool->promise()->wait();
    }

    /**
     * 获取部门列表.
     */
    public function syncDeptList()
    {
        try {
            $response = $this->getClient()->request('GET', 'index.php/oaapi/oaapi/deptList');
            echo $response->getBody()->getContents(), PHP_EOL;
        } catch (GuzzleException $exception) {
            echo $exception->getMessage(), PHP_EOL;
        }
    }
}
