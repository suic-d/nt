<?php

namespace App\Console\Commands\Product;

use App\Traits\LoggerTrait;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class SyncDeptList extends Command
{
    use LoggerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:syncDeptList';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取部门列表';

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
        try {
            $this->client->request('GET', 'index.php/oaapi/oaapi/deptList');
        } catch (GuzzleException $exception) {
            $this->logger->error($exception->getMessage());
        }
    }

    /**
     * @return ClientInterface
     */
    protected function createDefaultClient()
    {
        if (!$this->client) {
            $this->client = new Client(['base_uri' => env('BASE_URL'), 'verify' => false]);
        }

        return $this->client;
    }
}
