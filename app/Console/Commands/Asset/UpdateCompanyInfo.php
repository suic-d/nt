<?php

namespace App\Console\Commands\Asset;

use App\Models\Company;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Monolog\Formatter\LineFormatter;
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

    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client(['base_uri' => env('BASE_URL_ASSET'), 'verify' => false]);

        $this->logger = new Logger($name = class_basename(__CLASS__));
        $path = storage_path('logs').DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR.$name.'.log';
        $handler = new StreamHandler($path, Logger::INFO);
        $handler->setFormatter(new LineFormatter(null, $this->dateFormat, true, true));
        $this->logger->pushHandler($handler);
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
