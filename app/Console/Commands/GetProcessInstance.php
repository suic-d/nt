<?php

namespace App\Console\Commands;

use App\Models\SkuReview;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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
     * @var string
     */
    private $baseUri = 'http://v2.product.nantang-tech.com';

    /**
     * @var Client
     */
    private $client;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(['base_uri' => $this->baseUri, 'verify' => false]);
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

        foreach ($reviews as $review) {
            $this->request($review);
        }
    }

    /**
     * @param SkuReview $review
     */
    public function request($review)
    {
        try {
            $response = $this->client->request('GET', 'index.php/api/v1/ExternalAPI/getProcessInstance', [
                RequestOptions::QUERY => ['review_id' => $review->id],
            ]);
            dump($response->getBody()->getContents());
        } catch (GuzzleException $exception) {
            dump($exception->getMessage());
        }
    }
}
