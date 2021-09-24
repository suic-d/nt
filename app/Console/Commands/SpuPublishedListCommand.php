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

                $model->link_count = SpuPublished::where('sku', $item->sku)->count();
                $model->pl_count = SpuPublished::where('sku', $item->sku)->distinct()->count('platform');
                if ($model->isDirty()) {
                    $model->update_time = date('Y-m-d H:i:s');
                    $model->save();
                }
            })
        ;
    }
}
