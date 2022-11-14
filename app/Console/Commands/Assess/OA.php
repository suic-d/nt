<?php

namespace App\Console\Commands\Assess;

use App\Models\Assess\DeptList;
use App\Models\Assess\StaffList;
use App\Traits\ClientTrait;
use App\Traits\LoggerTrait;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class OA extends Command
{
    use ClientTrait;
    use LoggerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assess:oa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测评OA';

    public function __construct()
    {
        parent::__construct();

        $this->url = env('BASE_URL_ASSESS');
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
            $this->getClient()->request('GET', 'index.php/oaapi/oaapi/userList');
        } catch (GuzzleException $exception) {
            $this->getLogger()->error($exception->getMessage());
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
            },
            'rejected' => function ($exception) {
                $this->getLogger()->error($exception->getMessage());
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
            },
            'rejected' => function ($exception) {
                $this->getLogger()->error($exception->getMessage());
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
            },
            'rejected' => function ($exception) {
                $this->getLogger()->error($exception->getMessage());
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
            $this->getClient()->request('GET', 'index.php/oaapi/oaapi/deptList');
        } catch (GuzzleException $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
    }
}
