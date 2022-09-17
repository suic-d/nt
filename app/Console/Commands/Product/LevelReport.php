<?php

namespace App\Console\Commands\Product;

use App\Models\LevelConfig;
use App\Models\Product\Dictionary;
use App\Models\Product\SkuStepPrice;
use App\Models\ProductPool;
use App\Models\Sku;
use App\Models\SkuLevel;
use App\Models\SpuInfo;
use App\Models\Supplier;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LevelReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:levelReport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sku等级报表';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Illuminate\Database\Eloquent\Collection|LevelConfig[]
     */
    private static $levelConfigs;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(['base_uri' => env('BASE_URL'), 'verify' => false]);
        $this->logger = new Logger('levelReport');
        $this->logger->pushHandler(new StreamHandler(storage_path('logs/levelReport.log'), Logger::INFO));
    }

    public function handle()
    {
        $this->logger->info(__METHOD__.' processing');
        $this->rl();
        $this->logger->info(__METHOD__.' processed');
    }

    public function rl()
    {
        $page = 1;
        $limit = 1000;
        while (true) {
            $products = ProductPool::where('sample', 1)
                ->orderBy('done_at')
                ->forPage($page, $limit)
                ->get(['sku'])
            ;
            if ($products->isEmpty()) {
                break;
            }

            try {
                $response = $this->client->request('POST', 'index.php/crontab/TransAttr/rl', [
                    RequestOptions::JSON => ['skus' => $products->pluck('sku')],
                ]);
                dump($response->getBody()->getContents());
            } catch (GuzzleException $exception) {
                dump($exception->getMessage());
            }

            unset($products);
            dump($page++);
        }
    }

    public function url()
    {
        $perPage = 100;
        $lastPage = DB::table('nt_product_pool', 'pp')
            ->join('nt_sku as sk', 'sk.sku', '=', 'pp.sku')
            ->join('nt_spu_info as si', 'si.spu', '=', 'pp.spu')
            ->leftJoin('nt_supplier as su', 'su.id', '=', 'sk.supplier_id')
            ->leftJoin('nt_dictionary as di', 'di.id', '=', 'su.shipping_province')
            ->orderByDesc('pp.done_at')
            ->paginate($perPage, ['pp.sku'], 'page', 1)
            ->lastPage()
        ;
        $requests = function () use ($perPage, $lastPage) {
            for ($page = 1; $page <= $lastPage; ++$page) {
                yield new Request('GET', 'index.php/crontab/TransAttr/lr?page='.$page.'&limit='.$perPage);
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

    public function db()
    {
        ini_set('memory_limit', '512M');

        $page = 1;
        $limit = 1000;
        while (true) {
            $products = ProductPool::where('sample', 1)
                ->orderBy('done_at')
                ->forPage($page, $limit)
                ->get(['sku'])
            ;
            if ($products->isEmpty()) {
                break;
            }

            foreach ($products as $v) {
                $this->updateSkuLevel($v->sku);
            }

            unset($products);
            dump($page++);
        }
    }

    /**
     * @param string $sku
     */
    public function updateSkuLevel($sku)
    {
        $skuLevel = SkuLevel::where('sku', $sku)->first();
        if (is_null($skuLevel)) {
            $skuLevel = new SkuLevel();
            $skuLevel->sku = $sku;
        }
        $skuLevel->has_step_price = 0;
        $skuLevel->has_moq = 0;
        $skuLevel->arrival_time = 0.0;
        $skuLevel->delivery_place = '';
        $skuLevel->product_level = '';

        $skuStepPrices = SkuStepPrice::where('sku', $sku)->get();
        if ($skuStepPrices->isNotEmpty()) {
            $skuLevel->has_step_price = 1;
        }

        $skuModel = Sku::find($sku);
        if (!is_null($skuModel)) {
            if (0 != $skuModel->moq) {
                $skuLevel->has_moq = 1;
            }

            $supplier = Supplier::find($skuModel->supplier_id);
            if (!is_null($supplier)) {
                $skuLevel->arrival_time = self::getArrivalTime($supplier->supplier_name);
                $dictionary = Dictionary::find($supplier->shipping_province);
                if (!is_null($dictionary)) {
                    $skuLevel->delivery_place = $dictionary->name;
                }
            }

            $spuInfo = SpuInfo::find($skuModel->spu);
            if (!is_null($spuInfo)) {
                $levels = [];
                foreach (self::getLevelConfigs() as $config) {
                    if (!is_null($config->arrival_time_min)) {
                        if (1 == $config->arrival_time_min_contain) {
                            if (bccomp($skuLevel->arrival_time, $config->arrival_time_min, 1) < 0) {
                                continue;
                            }
                        } else {
                            if (bccomp($skuLevel->arrival_time, $config->arrival_time_min, 1) <= 0) {
                                continue;
                            }
                        }
                    }

                    if (!is_null($config->arrival_time_max)) {
                        if (1 == $config->arrival_time_max_contain) {
                            if (bccomp($skuLevel->arrival_time, $config->arrival_time_max, 1) > 0) {
                                continue;
                            }
                        } else {
                            if (bccomp($skuLevel->arrival_time, $config->arrival_time_max, 1) >= 0) {
                                continue;
                            }
                        }
                    }

                    if (!is_null($config->has_step_price) && $skuLevel->has_step_price != $config->has_step_price) {
                        continue;
                    }

                    if (!is_null($config->has_moq) && $skuLevel->has_moq != $config->has_moq) {
                        continue;
                    }

                    if (!is_null($config->order_arrange) && $spuInfo->order_arrange !== $config->order_arrange) {
                        continue;
                    }

                    if (!is_null($config->is_tort) && $skuModel->is_tort != $config->is_tort) {
                        continue;
                    }

                    if (!empty($config->delivery_place)
                        && !in_array($skuLevel->delivery_place, explode(',', $config->delivery_place))) {
                        continue;
                    }

                    $levels[] = $config->level;
                }
                $skuLevel->product_level = join('、', $levels);
            }
        }

        $skuLevel->save();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|LevelConfig[]
     */
    public static function getLevelConfigs()
    {
        if (is_null(self::$levelConfigs)) {
            self::$levelConfigs = LevelConfig::get();
        }

        return self::$levelConfigs;
    }

    /**
     * @param string $supplierName
     *
     * @return float
     */
    public static function getArrivalTime($supplierName)
    {
        $arrivalList = DB::connection('mysql_data')
            ->table('purchase_stat_current', 'psc')
            ->leftJoin('store as s', 's.storeId', '=', 'psc.storeId')
            ->where('psc.supplierName', $supplierName)
            ->groupBy(['psc.storeId', 'psc.supplierName'])
            ->get(['s.name', 'psc.supplierName', 'psc.period', DB::raw('IF(COUNT(*), COUNT(*), 0) as batch')])
        ;
        if ($arrivalList->isEmpty()) {
            return 0.0;
        }

        $periodSum = 0;
        $batchSum = 0;
        foreach ($arrivalList as $val) {
            $periodSum = bcadd($periodSum, bcmul($val->period, $val->batch, 2), 2);
            $batchSum += $val->batch;
        }

        return round($periodSum / $batchSum, 1);
    }
}
