<?php

namespace App\Console\Commands\Product;

use App\Traits\LoggerTrait;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class SyncProductUser extends Command
{
    use LoggerTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:syncProductUser';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取商品中心的用户';

    /**
     * @var ClientInterface
     */
    protected $client;

    public function __construct()
    {
        parent::__construct();

        $this->createDefaultClient();
    }

    public function handle()
    {
        try {
            $this->client->request('GET', 'index.php/oaapi/oaapi/getProductUser');
        } catch (GuzzleException $exception) {
            $this->getLogger()->error($exception->getMessage());
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
