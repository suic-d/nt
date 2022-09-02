<?php

namespace App\Console\Commands;

use App\Models\Company;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

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
    protected $url = 'http://assetinfo.api.nantang-tech.com';

//    protected $url = 'http://test.assetinfo.api.nantang-tech.com';

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
            if (is_null($companyId)) {
                $companyIds = Company::get(['id'])->pluck('id');
            } else {
                $companyIds = [$companyId];
            }
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
