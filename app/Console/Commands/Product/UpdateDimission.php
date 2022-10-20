<?php

namespace App\Console\Commands\Product;

use App\Models\StaffList;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class UpdateDimission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:updateDimission';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新员工在职状态';

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
        $this->client = new Client(['base_uri' => env('BASE_URL'), 'verify' => false, 'timeout' => 5]);
        $this->logger = new Logger('updateDimission');
        $this->logger->pushHandler(new StreamHandler(
            storage_path('logs/'.date('Ymd').'/updateDimission.log'),
            Logger::INFO
        ));
    }

    public function handle()
    {
        $staffIdArr = StaffList::where('is_dimission', 1)->get(['staff_id'])->pluck('staff_id');
        foreach ($staffIdArr as $v) {
            try {
                $response = $this->client->request('GET', 'index.php/oaapi/oaapi/updateDimission', [
                    RequestOptions::QUERY => ['staff_id' => $v],
                ]);
                $this->logger->info('staff_id = '.$v.' '.$response->getBody()->getContents());
                $this->logger->close();
            } catch (GuzzleException $exception) {
                $this->logger->error('staff_id = '.$v.' '.$exception->getMessage());
                $this->logger->close();
            }
        }
    }
}
