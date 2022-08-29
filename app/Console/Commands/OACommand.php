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
            dump($response->getBody()->getContents());
        } catch (GuzzleException $exception) {
            dump($exception->getMessage());
        }
    }

    /**
     * 获取部门下用户.
     *
     * @param string $deptId
     */
    public function syncDeptUser($deptId = null)
    {
        $requests = function () use ($deptId) {
            if (is_null($deptId)) {
                $deptIdArr = DeptList::get(['dept_id'])->pluck('dept_id');
            } else {
                $deptIdArr = [$deptId];
            }
            foreach ($deptIdArr as $value) {
                yield new $value() => new Request('GET', 'index.php/oaapi/oaapi/deptUser?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                dump($response->getBody()->getContents());
            },
            'rejected' => function ($reason) {
                dump($reason->getMessage());
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
        $requests = function () use ($staffId) {
            if (is_null($staffId)) {
                $staffIdArr = StaffList::where('is_dimission', 1)->get(['staff_id'])->pluck('staff_id');
            } else {
                $staffIdArr = [$staffId];
            }
            foreach ($staffIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/staffDetail?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                dump($response->getBody()->getContents());
            },
            'rejected' => function ($reason) {
                dump($reason->getMessage());
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
            dump($response->getBody()->getContents());
        } catch (GuzzleException $exception) {
            dump($exception->getMessage());
        }
    }

    /**
     * 同步供应商信息到普源.
     *
     * @param int $supplierId
     */
    public function syncSupplier($supplierId = null)
    {
        $requests = function () use ($supplierId) {
            if (is_null($supplierId)) {
                $supplierIdArr = Supplier::where('is_sync', 1)->where('py_id', 0)->get(['id'])->pluck('id');
            } else {
                $supplierIdArr = [$supplierId];
            }
            foreach ($supplierIdArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/syncSupplierInfo?id='.$value);
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
     * 同步产品品类到普源.
     *
     * @param int $categoryId
     */
    public function syncProductCategory($categoryId = null)
    {
        $requests = function () use ($categoryId) {
            if (is_null($categoryId)) {
                $categoryIdArr = ProductCategory::where('is_sync', 1)->where('py_id', 0)->get(['id'])->pluck('id');
            } else {
                $categoryIdArr = [$categoryId];
            }
            foreach ($categoryIdArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/syncProductCategory?id='.$value);
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
     * 同步商品到普源.
     *
     * @param string $sku
     */
    public function syncGood($sku = null)
    {
        $requests = function () use ($sku) {
            if (is_null($sku)) {
                $skuArr = ProductPool::where('is_sync', 1)->where('py_id', 0)->get(['sku'])->pluck('sku');
            } else {
                $skuArr = [$sku];
            }
            foreach ($skuArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/syncGoodInfo?sku='.$value);
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
     * 同步更新供应商信息到普源.
     *
     * @param int $supplierId
     */
    public function updateSupplier($supplierId = null)
    {
        $requests = function () use ($supplierId) {
            if (is_null($supplierId)) {
                $supplierIdArr = Supplier::where('is_sync', 1)->where('py_id', '!=', 0)->get(['id'])->pluck('id');
            } else {
                $supplierIdArr = [$supplierId];
            }
            foreach ($supplierIdArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/updateSupplierInfo?id='.$value);
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
     * 同步更新产品品类到普源.
     *
     * @param int $categoryId
     */
    public function updateProductCategory($categoryId = null)
    {
        $requests = function () use ($categoryId) {
            if (is_null($categoryId)) {
                $categoryIdArr = ProductCategory::where('is_sync', 1)
                    ->where('py_id', '!=', 0)
                    ->get(['id'])
                    ->pluck('id')
                ;
            } else {
                $categoryIdArr = [$categoryId];
            }
            foreach ($categoryIdArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/syncProductCategory?id='.$value);
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
     * 同步更新商品到普源.
     *
     * @param string $sku
     */
    public function updateGood($sku = null)
    {
        $requests = function () use ($sku) {
            if (is_null($sku)) {
                $skuArr = ProductPool::where('is_sync', 1)->where('py_id', '!=', 0)->get(['sku'])->pluck('sku');
            } else {
                $skuArr = [$sku];
            }
            foreach ($skuArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/updateGoodInfo?sku='.$value);
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
}
