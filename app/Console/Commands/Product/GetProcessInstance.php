<?php

namespace App\Console\Commands\Product;

use App\Models\SkuReview;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class GetProcessInstance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:getProcessInstance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '钉钉审核';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(['base_uri' => env('BASE_URL'), 'verify' => false]);
        $this->logger = new Logger('getProcessInstance');
        $this->logger->pushHandler(new StreamHandler(
            storage_path('logs/'.date('Ymd').'/getProcessInstance.log'),
            Logger::INFO
        ));
    }

    public function handle()
    {
        $requests = function () {
            $reviews = SkuReview::whereIn('process_status', ['NEW', 'RUNNING'])
                ->orderBy('id')
                ->forPage(1, 200)
                ->get(['id'])
            ;
            foreach ($reviews as $v) {
                yield $v->id => new Request('GET', 'index.php/api/v1/ExternalAPI/getProcessInstance?review_id='.$v->id);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
                $this->logger->info('review_id = '.$idx.' '.$response->getBody()->getContents());
                $this->logger->close();
            },
            'rejected' => function ($reason, $idx) {
                $this->logger->error('review_id = '.$idx.' '.$reason->getMessage());
                $this->logger->close();
            },
        ]);
        $pool->promise()->wait();
    }
}
