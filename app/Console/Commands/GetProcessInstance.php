<?php

namespace App\Console\Commands;

use App\Models\SkuReview;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Env;

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

    /**
     * @var string
     */
    private $logFile;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->baseUri = env('BASE_URL');
        $this->client = new Client(['base_uri' => $this->baseUri, 'verify' => false]);
        $this->logFile = '/www/logs/laravel-'.date('Y-m-d').'.log';
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->pool();
        $this->log(__METHOD__);
    }

    /**
     * @param string $log
     */
    public function log($log)
    {
        $message = sprintf('[%s] %s'.PHP_EOL, date('Y-m-d H:i:s'), $log);
        error_log($message, 3, $this->logFile);
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
                $this->log($contents = $response->getBody()->getContents());
                dump($contents);
            } catch (GuzzleException $exception) {
                $this->log($msg = $exception->getMessage());
                dump($msg);
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
                $this->log($contents = $response->getBody()->getContents());
                dump($contents);
            },
            'rejected' => function ($reason) {
                $this->log($msg = $reason->getMessage());
                dump($msg);
            },
        ]);
        $pool->promise()->wait();
    }
}
