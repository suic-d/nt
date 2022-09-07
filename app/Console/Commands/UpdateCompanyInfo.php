<?php

namespace App\Console\Commands;

use App\Models\Company;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Env;

class UpdateCompanyInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crontab:update_company_info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'crontab:update_company_info';

    /**
     * @var string
     */
    protected $url;

    /**
     * @var Client
     */
    protected $client;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
        $this->url = env('BASE_URL_ASSET');
        $this->client = new Client(['base_uri' => $this->url, 'verify' => false]);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->updateCompanyStatus();
    }

    /**
     * @param int $companyId
     */
    public function updateCompanyStatus($companyId = null)
    {
        $requests = function ($companyId) {
            $companyIds = !is_null($companyId) ? [$companyId] : Company::get(['id'])->pluck('id');
            foreach ($companyIds as $value) {
                yield $value => new Request('GET', 'listing/test/set_company_status?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests($companyId), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                dump($response->getBody()->getContents());
            },
            'rejected' => function ($reason) {
                dump($reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }
}
