<?php

namespace App\Console\Commands\Product;

use App\Models\SkuReview;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

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
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    public function __construct()
    {
        parent::__construct();

        $this->createDefaultClient();
        $this->createDefaultLogger();
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

    /**
     * @return ClientInterface
     */
    protected function createDefaultClient()
    {
        if (!$this->client) {
            $this->client = new Client(['base_uri' => env('BASE_URL'), 'verity' => false]);
        }

        return $this->client;
    }

    /**
     * @return LoggerInterface
     */
    protected function createDefaultLogger()
    {
        if (!$this->logger) {
            $name = class_basename(__CLASS__);
            $path = storage_path('logs').DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR.$name.'.log';
            $handler = new StreamHandler($path, Logger::INFO);
            $handler->setFormatter(new LineFormatter(null, $this->dateFormat, true, true));

            $this->logger = new Logger($name);
            $this->logger->pushHandler($handler);
        }

        return $this->logger;
    }
}
