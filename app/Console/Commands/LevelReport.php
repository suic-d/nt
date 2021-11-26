<?php

namespace App\Console\Commands;

use App\Models\SkuLevel;
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

                $arrivalList = DB::connection('mysql_data')
                    ->table('purchase_stat_current', 'psc')
                    ->leftJoin('store as s', 's.storeId', '=', 'psc.storeId')
                    ->where('psc.supplierName', $productPool->supplier_name)
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
                    $batchSum = array_sum($batchSum);
                    $periodSum = array_sum($periodSum);
                    $model->arrival_time = round($periodSum / $batchSum, 1);
                }
                unset($arrivalList);

                $model->save();
            }

            unset($productPools, $skuArr, $stepPriceGroup, $levelMap);

            ++$page;
        }
    }
}
