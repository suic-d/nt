<?php

namespace App\Console\Commands\Asset;

use App\Models\Company;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class UpdateCompanyInfo extends Command
{
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

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(['base_uri' => env('BASE_URL_ASSET'), 'verify' => false]);
        $this->logger = new Logger('updateCompanyInfo');
        $this->logger->pushHandler(new StreamHandler(
            storage_path('logs/'.date('Ymd').'/updateCompanyInfo.log'),
            Logger::INFO
        ));
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
                $this->logger->error('company_id = '.$idx.' '.$reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }
}
