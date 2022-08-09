<?php

namespace App\Console\Commands;

use App\Models\DeptList;
use App\Models\ProductCategory;
use App\Models\ProductPool;
use App\Models\StaffList;
use App\Models\Supplier;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OACommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crontab:oa';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步OA';

    /**
     * @var string
     */
    protected $url = 'http://v2.product.nantang-tech.com';

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
        // 获取部门列表
        $this->syncDeptList();
        // 获取部门下用户
        $this->syncDeptUser();
        // 获取员工详情
        $this->syncStaffDetail();
        // 获取商品中心的用户
        $this->syncProductUser();

        /*
        // 同步供应商信息到普源
        $this->syncSupplier();
        // 同步产品品类到普源
        $this->syncProductCategory();
        // 同步商品到普源
        $this->syncGood();
        // 同步更新供应商信息到普源
        $this->updateSupplier();
        // 同步更新产品品类到普源
        $this->updateProductCategory();
        // 同步更新商品到普源
        $this->updateGood();
        */
    }

    /**
     * 获取部门列表.
     */
    public function syncDeptList()
    {
        try {
            $response = $this->client->request('GET', 'index.php/oaapi/oaapi/deptList');
            Log::info($response->getBody()->getContents(), ['method' => __METHOD__]);
        } catch (GuzzleException $exception) {
            Log::error($exception->getMessage(), ['method' => __METHOD__]);
        }
    }

    /**
     * 获取部门下用户.
     *
     * @param string $deptId
     */
    public function syncDeptUser($deptId = null)
    {
        $deptIdArr = is_null($deptId) ? DeptList::get(['dept_id'])->pluck('dept_id') : [$deptId];

        $requests = function () use ($deptIdArr) {
            foreach ($deptIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/deptUser?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
                Log::info($response->getBody()->getContents(), ['method' => __METHOD__, 'dept_id' => $idx]);
            },
            'rejected' => function ($reason) {
                Log::error($reason->getMessage(), ['method' => __METHOD__]);
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
        $staffIdArr = is_null($staffId) ? StaffList::where('is_dimission', '!=', 2)
            ->get(['staff_id'])
            ->pluck('staff_id') : [$staffId];

        $requests = function () use ($staffIdArr) {
            foreach ($staffIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/staffDetail?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
                Log::info($response->getBody()->getContents(), ['method' => __METHOD__, 'staff_id' => $idx]);
            },
            'rejected' => function ($reason) {
                Log::error($reason->getMessage(), ['method' => __METHOD__]);
            },
        ]);
        $pool->promise()->wait();
    }

    /**
     * 获取商品中心的用户.
     */
    public function syncProductUser()
    {
        try {
            $response = $this->client->request('GET', 'index.php/oaapi/oaapi/getProductUser');
            Log::info($response->getBody()->getContents(), ['method' => __METHOD__]);
        } catch (GuzzleException $exception) {
            Log::error($exception->getMessage(), ['method' => __METHOD__]);
        }
    }

    /**
     * 同步供应商信息到普源.
     *
     * @param int $supplierId
     */
    public function syncSupplier($supplierId = null)
    {
        $supplierIdArr = is_null($supplierId) ? Supplier::where('is_sync', 1)
            ->where('py_id', 0)
            ->get(['id'])
            ->pluck('id') : [$supplierId];

        $requests = function () use ($supplierIdArr) {
            foreach ($supplierIdArr as $value) {
                yield new Request('GET', 'index.php/pyapi/pyapi/syncSupplierInfo?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            //            'concurrency' => 5,
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
     * 同步产品品类到普源.
     *
     * @param int $categoryId
     */
    public function syncProductCategory($categoryId = null)
    {
        $categoryIdArr = is_null($categoryId) ? ProductCategory::where('is_sync', 1)
            ->where('py_id', 0)
            ->get(['id'])
            ->pluck('id') : [$categoryId];

        $requests = function () use ($categoryIdArr) {
            foreach ($categoryIdArr as $value) {
                yield new Request('GET', 'index.php/pyapi/pyapi/syncProductCategory?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            //            'concurrency' => 5,
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
     * 同步商品到普源.
     *
     * @param string $sku
     */
    public function syncGood($sku = null)
    {
        $skuArr = is_null($sku) ? ProductPool::where('is_sync', 1)
            ->where('py_id', 0)
            ->get(['sku'])
            ->pluck('sku') : [$sku];

        $requests = function () use ($skuArr) {
            foreach ($skuArr as $value) {
                yield new Request('GET', 'index.php/pyapi/pyapi/syncGoodInfo?sku='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            //            'concurrency' => 5,
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
     * 同步更新供应商信息到普源.
     *
     * @param int $supplierId
     */
    public function updateSupplier($supplierId = null)
    {
        $supplierIdArr = is_null($supplierId) ? Supplier::where('is_sync', 1)
            ->where('py_id', '!=', 0)
            ->get(['id'])
            ->pluck('id') : [$supplierId];

        $requests = function () use ($supplierIdArr) {
            foreach ($supplierIdArr as $value) {
                yield new Request('GET', 'index.php/pyapi/pyapi/updateSupplierInfo?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            //            'concurrency' => 5,
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
     * 同步更新产品品类到普源.
     *
     * @param int $categoryId
     */
    public function updateProductCategory($categoryId = null)
    {
        $categoryIdArr = is_null($categoryId) ? ProductCategory::where('is_sync', 1)
            ->where('py_id', '!=', 0)
            ->get(['id'])
            ->pluck('id') : [$categoryId];

        $requests = function () use ($categoryIdArr) {
            foreach ($categoryIdArr as $value) {
                yield new Request('GET', 'index.php/pyapi/pyapi/syncProductCategory?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            //            'concurrency' => 5,
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
     * 同步更新商品到普源.
     *
     * @param string $sku
     */
    public function updateGood($sku = null)
    {
        $skuArr = is_null($sku) ? ProductPool::where('is_sync', 1)
            ->where('py_id', '!=', 0)
            ->get(['sku'])
            ->pluck('sku') : [$sku];

        $requests = function () use ($skuArr) {
            foreach ($skuArr as $value) {
                yield new Request('GET', 'index.php/pyapi/pyapi/updateGoodInfo?sku='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            //            'concurrency' => 5,
            'fulfilled' => function ($response) {
                echo $response->getBody()->getContents(), PHP_EOL;
            },
            'rejected' => function ($reason) {
                echo $reason->getMessage(), PHP_EOL;
            },
        ]);
        $pool->promise()->wait();
    }
}
