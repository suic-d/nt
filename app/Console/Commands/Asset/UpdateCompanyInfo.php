<?php

namespace App\Console\Commands\Asset;

use App\Models\Company;
use App\Traits\LoggerTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class UpdateCompanyInfo extends Command
{
    use LoggerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'asset:updateCompanyInfo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新公司天眼查数据信息';

    /**
     * @var Client
     */
    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(['base_uri' => env('BASE_URL_ASSET'), 'verify' => false]);
    }

    public function handle()
    {
        $requests = function () {
            $companyIds = Company::get(['id'])->pluck('id');
            foreach ($companyIds as $value) {
                yield $value => new Request('GET', 'listing/test/set_company_status?id='.$value);
            }
        };
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
            },
            'rejected' => function ($reason, $idx) {
                $this->getLogger()->error('company_id = '.$idx.' '.$reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }
}
