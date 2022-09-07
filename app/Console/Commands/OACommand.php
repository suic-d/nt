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
use Illuminate\Support\Env;

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
        $this->url = Env::get('BASE_URL');
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
        $requests = function ($deptId) {
            $deptIdArr = !is_null($deptId) ? [$deptId] : DeptList::get(['dept_id'])->pluck('dept_id');
            foreach ($deptIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/deptUser?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($deptId), [
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
        $requests = function ($supplierId) {
            $supplierIdArr = !is_null($supplierId) ? [$supplierId] : Supplier::where('is_sync', 1)
                ->where('py_id', 0)
                ->get(['id'])
                ->pluck('id')
            ;
            foreach ($supplierIdArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/syncSupplierInfo?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($supplierId), [
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
        $requests = function ($categoryId) {
            $categoryIdArr = !is_null($categoryId) ? [$categoryId] : ProductCategory::where('is_sync', 1)
                ->where('py_id', 0)
                ->get(['id'])
                ->pluck('id')
            ;
            foreach ($categoryIdArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/syncProductCategory?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($categoryId), [
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
        $requests = function ($sku) {
            $skuArr = !is_null($sku) ? [$sku] : ProductPool::where('is_sync', 1)
                ->where('py_id', 0)
                ->get(['sku'])
                ->pluck('sku')
            ;
            foreach ($skuArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/syncGoodInfo?sku='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($sku), [
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
        $requests = function ($supplierId) {
            $supplierIdArr = !is_null($supplierId) ? [$supplierId] : Supplier::where('is_sync', 0)
                ->where('py_id', '!=', 0)
                ->get(['id'])
                ->pluck('id')
            ;
            foreach ($supplierIdArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/updateSupplierInfo?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($supplierId), [
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
        $requests = function ($categoryId) {
            $categoryIdArr = !is_null($categoryId) ? [$categoryId] : ProductCategory::where('is_sync', 1)
                ->where('py_id', '!=', 0)
                ->get(['id'])
                ->pluck('id')
            ;
            foreach ($categoryIdArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/syncProductCategory?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($categoryId), [
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
        $requests = function ($sku) {
            $skuArr = !is_null($sku) ? [$sku] : ProductPool::where('is_sync', 1)
                ->where('py_id', '!=', 0)
                ->get(['sku'])
                ->pluck('sku')
            ;
            foreach ($skuArr as $value) {
                yield $value => new Request('GET', 'index.php/pyapi/pyapi/updateGoodInfo?sku='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($sku), [
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
