<?php

namespace App\Console\Commands;

use App\Models\SkuReview;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;

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
        $reviews = SkuReview::whereIn('process_status', ['NEW', 'RUNNING'])->forPage(1, 200)->get();
        if ($reviews->isEmpty()) {
            return;
        }

        $client = new Client(['base_uri' => 'http://v2.product.nantang-tech.com', 'verify' => false]);
        foreach ($reviews as $review) {
            try {
                $response = $client->request('GET', 'index.php/api/v1/ExternalAPI/getProcessInstance', [
                    RequestOptions::QUERY => ['review_id' => $review->id],
                ]);
                dump($response->getBody()->getContents());
            } catch (\Throwable $exception) {
                dump($exception->getMessage());
            }
        }
    }
}
