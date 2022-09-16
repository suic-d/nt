<?php

namespace App\Console\Commands;

use App\Models\SkuReview;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
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
    protected $signature = 'get-process-instance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '钉钉审核';

    /**
     * @var string
     */
    private $baseUri;

    /**
     * @var Client
     */
    private $client;

    private $logger;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->baseUri = env('BASE_URL');
        $this->client = new Client(['base_uri' => $this->baseUri, 'verify' => false]);
        $this->logger = new Logger('getProcessInstance');
        $this->logger->pushHandler(new StreamHandler(storage_path('logs/getProcessInstance.log'), Logger::INFO));
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->pool();
    }

    public function request()
    {
        $reviews = SkuReview::whereIn('process_status', ['NEW', 'RUNNING'])
            ->orderBy('id')
            ->forPage(1, 200)
            ->get(['id'])
        ;
        if ($reviews->isEmpty()) {
            return;
        }

        foreach ($reviews as $v) {
            try {
                $response = $this->client->request('GET', 'index.php/api/v1/ExternalAPI/getProcessInstance', [
                    RequestOptions::QUERY => ['review_id' => $v->id],
                ]);
                $this->logger->info($response->getBody()->getContents());
            } catch (GuzzleException $exception) {
                $this->logger->info($exception->getMessage());
            }
        }
    }

    public function pool()
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
            'fulfilled' => function ($response) {
                $this->logger->info($response->getBody()->getContents());
            },
            'rejected' => function ($reason) {
                $this->logger->info($reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }
}
