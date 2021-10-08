<?php

namespace App\Console\Commands;

use App\Models\Sku;
use App\Models\SpuPublished;
use App\Models\SpuPublishedList;
use Illuminate\Console\Command;

class SpuPublishedListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crontab:spu_published_list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '刊登报表';

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
        SpuPublished::distinct()
            ->whereRaw('DATE(add_time) >= ?', [date('Y-m-d', strtotime('-10 day'))])
            ->get(['sku'])
            ->each(function ($item) {
                $model = SpuPublishedList::where('sku', $item->sku)->first();
                if (is_null($model)) {
                    $model = new SpuPublishedList();
                    $model->sku = $item->sku;

                    $spuPublishedModel = SpuPublished::where('sku', $item->sku)
                        ->where('spu', '!=', '')
                        ->whereNotNull('spu')
                        ->first()
                    ;
                    if (is_null($spuPublishedModel)) {
                        $skuModel = Sku::find($item->sku);
                        if (!is_null($skuModel)) {
                            $model->spu = $skuModel->spu;
                        }
                    } else {
                        $model->spu = $spuPublishedModel->spu;
                    }
                    $model->add_time = date('Y-m-d H:i:s');
                }

                $spuPublishedModels = SpuPublished::where('sku', $item->sku)->get();
                $spuPublishedGroup = $spuPublishedModels->reduce(function ($carry, SpuPublished $item) {
                    $platform = strtolower($item->platform);
                    if (!isset($carry[$platform])) {
                        $carry[$platform] = [];
                    }
                    $carry[$platform][] = $item;

                    return $carry;
                }, []);

                $model->link_count = $spuPublishedModels->count();
                $model->pl_count = count($spuPublishedGroup);
                $model->amazon_count = isset($spuPublishedGroup['amazon']) ? count($spuPublishedGroup['amazon']) : 0;
                $model->ebay_count = isset($spuPublishedGroup['ebay']) ? count($spuPublishedGroup['ebay']) : 0;
                $model->lazada_count = isset($spuPublishedGroup['lazada']) ? count($spuPublishedGroup['lazada']) : 0;
                if ($model->isDirty()) {
                    $model->update_time = date('Y-m-d H:i:s');
                    $model->save();
                }
            })
        ;
    }
}
