<?php

namespace App\Console\Commands\Product;

use App\Models\SkuReview;
use App\Traits\LoggerTrait;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class GetProcessInstance extends Command
{
    use LoggerTrait;

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
}
