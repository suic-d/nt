<?php

namespace App\Console\Commands\Product;

use App\Models\Sku;
use App\Models\SpuPublished;
use App\Models\SpuPublishedList;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
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

    /**
     * @var Client
     */
    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new Logger('publishedList');
        $this->logger->pushHandler(new StreamHandler(storage_path('logs/publishedList.log'), Logger::INFO));
        $this->client = new Client(['base_uri' => env('BASE_URL'), 'verify' => false]);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '512M');
        $this->logger->info(__METHOD__.' processing');
        $this->request();
        $this->logger->info(__METHOD__.' processed');
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
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
                $this->logger->info('sku = '.$idx.' '.$response->getBody()->getContents());
                $this->logger->close();
            },
            'rejected' => function ($reason, $idx) {
                $this->logger->error('sku = '.$idx.' '.$reason->getMessage());
                $this->logger->close();
            },
        ]);
        $pool->promise()->wait();
    }

    public function batch()
    {
        $skuArr = SpuPublished::whereRaw('DATE(add_time) >= ?', [date('Y-m-d', strtotime('-10 days'))])
            ->distinct()
            ->get(['sku'])
            ->pluck('sku')
            ->toArray()
        ;
        $offset = 0;
        $length = 100;
        while (true) {
            if (empty($sub = array_slice($skuArr, $offset, $length))) {
                break;
            }

            try {
                $response = $this->client->request('POST', 'index.php/crontab/TransAttr/batchPublishedList', [
                    RequestOptions::JSON => ['sku' => $sub],
                ]);
                $this->logger->info('offset = '.$offset.' length = '.$length.' '.$response->getBody()->getContents());
            } catch (Exception $exception) {
                $this->logger->error($exception->getMessage());
            }

            unset($sub);
            $offset += $length;
        }
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
            $this->logger->error($exception->getMessage());
        }
    }
}
