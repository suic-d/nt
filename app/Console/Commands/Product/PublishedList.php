<?php

namespace App\Console\Commands\Product;

use App\Models\Sku;
use App\Models\SpuPublished;
use App\Models\SpuPublishedList;
use App\Traits\ClientTrait;
use App\Traits\LoggerTrait;
use Exception;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class PublishedList extends Command
{
    use LoggerTrait;
    use ClientTrait;

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

    public function __construct()
    {
        parent::__construct();

        $this->url = env('BASE_URL');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '512M');

        $this->request();
    }

    public function request()
    {
        $requests = function () {
            $skuArr = SpuPublished::whereRaw('DATE(add_time) >= ?', [date('Y-m-d', strtotime('-10 days'))])
                ->distinct()
                ->get(['sku'])
                ->pluck('sku')
                ->toArray()
            ;
            foreach ($skuArr as $sku) {
                yield $sku => new Request('GET', 'index.php/crontab/TransAttr/updatePublishedList?sku='.$sku);
            }
        };
        $pool = new Pool($this->getClient(), $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
            },
            'rejected' => function ($reason, $idx) {
                $this->getLogger()->error($idx.' '.$reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }

    public function batch()
    {
        $requests = function () {
            $skuArr = SpuPublished::whereRaw('DATE(add_time) >= ?', [date('Y-m-d', strtotime('-10 days'))])
                ->distinct()
                ->get(['sku'])
                ->pluck('sku')
                ->toArray()
            ;
            $size = count($skuArr);
            $length = 100;
            for ($offset = 0; $offset < $size; $offset += $length) {
                yield $offset => new Request(
                    'POST',
                    'index.php/crontab/TransAttr/updatePublishedList',
                    ['Content-Type' => 'application/json'],
                    json_encode(['sku' => array_slice($skuArr, $offset, $length)])
                );
            }
        };
        $pool = new Pool($this->getClient(), $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
            },
            'rejected' => function ($reason, $idx) {
                $this->getLogger()->error('offset = '.$idx.' '.$reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }

    public function db()
    {
        try {
            $skuArr = SpuPublished::whereRaw('DATE(add_time) >= ?', [date('Y-m-d', strtotime('-10 days'))])
                ->distinct()
                ->get(['sku'])
                ->pluck('sku')
                ->toArray()
            ;
            foreach ($skuArr as $sku) {
                $model = SpuPublishedList::where('sku', $sku)->first();
                if (is_null($model)) {
                    $model = new SpuPublishedList();
                    $model->sku = $sku;
                    $publishedModel = SpuPublished::where('sku', $sku)
                        ->where('spu', '!=', '')
                        ->whereNotNull('spu')
                        ->first()
                    ;
                    $model->spu = $publishedModel->spu ?? (Sku::find($sku)->spu ?? '');
                    $model->add_time = date('Y-m-d H:i:s');
                }

                $publishedModels = SpuPublished::where('sku', $sku)->get(['id', 'platform']);
                $publishedGroup = $publishedModels->reduce(function ($carry, $item) {
                    if (!isset($carry[$platform = strtolower($item->platform)])) {
                        $carry[$platform] = [];
                    }
                    $carry[$platform][] = $item->id;

                    return $carry;
                }, []);
                $model->link_count = $publishedModels->count();
                $model->pl_count = count($publishedGroup);
                $model->amazon_count = count($publishedGroup['amazon'] ?? []);
                $model->ebay_count = count($publishedGroup['ebay'] ?? []);
                $model->lazada_count = count($publishedGroup['lazada'] ?? []);
                $model->aliexpress_count = count($publishedGroup['aliexpress'] ?? []);
                $model->shopee_count = count($publishedGroup['shopee'] ?? []);
                if ($model->isDirty()) {
                    $model->update_time = date('Y-m-d H:i:s');
                    $model->save();
                }

                unset($publishedModels, $publishedGroup);
            }
        } catch (Exception $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
    }
}
