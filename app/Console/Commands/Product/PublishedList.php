<?php

namespace App\Console\Commands\Product;

use App\Models\Sku;
use App\Models\SpuPublished;
use App\Models\SpuPublishedList;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class PublishedList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:publishedList';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '刊登报表';

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger('publishedList');
        $this->logger->pushHandler(new StreamHandler(storage_path('logs/publishedList.log'), Logger::INFO));
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->logger->info(__METHOD__.' processing');

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

                $spuPublishedModels = SpuPublished::where('sku', $item->sku)->get(['id', 'platform']);
                $spuPublishedGroup = $spuPublishedModels->reduce(function ($carry, $item) {
                    $platform = strtolower($item->platform);
                    if (!isset($carry[$platform])) {
                        $carry[$platform] = [];
                    }
                    $carry[$platform][] = $item->id;

                    return $carry;
                }, []);

                $model->link_count = $spuPublishedModels->count();
                $model->pl_count = count($spuPublishedGroup);
                $model->amazon_count = isset($spuPublishedGroup['amazon']) ? count($spuPublishedGroup['amazon']) : 0;
                $model->ebay_count = isset($spuPublishedGroup['ebay']) ? count($spuPublishedGroup['ebay']) : 0;
                $model->lazada_count = isset($spuPublishedGroup['lazada']) ? count($spuPublishedGroup['lazada']) : 0;
                $model->aliexpress_count = isset($spuPublishedGroup['aliexpress'])
                    ? count($spuPublishedGroup['aliexpress']) : 0;
                $model->shopee_count = isset($spuPublishedGroup['shopee']) ? count($spuPublishedGroup['shopee']) : 0;
                if ($model->isDirty()) {
                    $model->update_time = date('Y-m-d H:i:s');
                    $model->save();
                }
            })
        ;

        $this->logger->info(__METHOD__.' processed');
    }
}
