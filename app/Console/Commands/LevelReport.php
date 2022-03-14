<?php

namespace App\Console\Commands;

use App\Models\LevelConfig;
use App\Models\Sku;
use App\Models\SkuLevel;
use App\Models\SpuInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LevelReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crontab:level_report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sku等级报表';

    /**
     * @var LevelConfig[]
     */
    private static $levelConfigs;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $page = 1;
        $limit = 1000;
        while (true) {
            $productPools = DB::table('nt_product_pool', 'pp')
                ->join('nt_sku as sk', 'sk.sku', '=', 'pp.sku')
                ->join('nt_spu_info as si', 'si.spu', '=', 'pp.spu')
                ->leftJoin('nt_supplier as su', 'su.id', '=', 'sk.supplier_id')
                ->leftJoin('nt_dictionary as di', 'di.id', '=', 'su.shipping_province')
                ->orderBy('pp.done_at', 'desc')
                ->forPage($page, $limit)
                ->get(['pp.sku', 'di.name', 'si.order_arrange', 'sk.moq', 'sk.is_tort', 'pp.supplier_name'])
            ;
            if ($productPools->isEmpty()) {
                break;
            }

            $stepPriceGroup = DB::table('nt_sku_step_price')
                ->whereIn('sku', $skuArr = $productPools->pluck('sku'))
                ->get()
                ->reduce(function ($carry, $item) {
                    if (!isset($carry[$item->sku])) {
                        $carry[$item->sku] = [];
                    }
                    $carry[$item->sku][] = $item;

                    return $carry;
                }, [])
            ;
            $levelMap = SkuLevel::whereIn('sku', $skuArr)->get()->keyBy(function (SkuLevel $item) {
                return $item->sku;
            });
            foreach ($productPools as $productPool) {
                if (isset($levelMap[$productPool->sku])) {
                    $model = $levelMap[$productPool->sku];
                } else {
                    $model = new SkuLevel();
                    $model->sku = $productPool->sku;
                }

                $model->has_step_price = isset($stepPriceGroup[$productPool->sku]) ? 1 : 0;
                $model->has_moq = (0 == $productPool->moq) ? 0 : 1;
                $model->delivery_place = $productPool->name ?? '';
                $model->arrival_time = $this->getArrivalTime($productPool->supplier_name);
                $model->product_level = join('、', $this->getLevel($model));
                $model->save();
            }

            unset($productPools, $skuArr, $stepPriceGroup, $levelMap);

            dump(date('Y-m-d H:i:s'), $page++);
        }
    }

    /**
     * @param string $supplierName
     *
     * @return float
     */
    public function getArrivalTime($supplierName)
    {
        $arrivalList = DB::connection('mysql_data')
            ->table('purchase_stat_current', 'psc')
            ->leftJoin('store as s', 's.storeId', '=', 'psc.storeId')
            ->where('psc.supplierName', $supplierName)
            ->groupBy(['psc.storeId', 'psc.supplierName'])
            ->get(['s.name', 'psc.supplierName', 'psc.period', DB::raw('if(count(*),count(*),0) as batch')])
        ;
        if ($arrivalList->isNotEmpty()) {
            $periodSum = [];
            $batchSum = [];
            foreach ($arrivalList as $value) {
                $periodSum[] = $value->period * $value->batch;
                $batchSum[] = $value->batch;
            }

            return round(array_sum($periodSum) / array_sum($batchSum), 1);
        }

        return 0.0;
    }

    /**
     * @param SkuLevel $skuLevel
     *
     * @return array
     */
    public function getLevel($skuLevel)
    {
        $levels = [];

        $sku = Sku::find($skuLevel->sku);
        if (is_null($sku)) {
            return $levels;
        }
        $spuInfo = SpuInfo::find($sku->spu);
        if (is_null($spuInfo)) {
            return $levels;
        }

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

            if (!is_null($config->is_tort) && $sku->is_tort != $config->is_tort) {
                continue;
            }

            if (!empty($config->delivery_place)
                && !in_array($skuLevel->delivery_place, explode(',', $config->delivery_place))) {
                continue;
            }

            $levels[] = $config->level;
        }

        return $levels;
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
}
