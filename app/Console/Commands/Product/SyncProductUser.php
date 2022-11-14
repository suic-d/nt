<?php

namespace App\Console\Commands\Product;

use App\Traits\ClientTrait;
use App\Traits\LoggerTrait;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class SyncProductUser extends Command
{
    use LoggerTrait;
    use ClientTrait;

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

    public function __construct()
    {
        parent::__construct();

        $this->url = env('BASE_URL');
    }

    public function handle()
    {
        try {
            $this->getClient()->request('GET', 'index.php/oaapi/oaapi/getProductUser');
        } catch (GuzzleException $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
    }
}
