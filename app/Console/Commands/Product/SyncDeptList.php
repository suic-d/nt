<?php

namespace App\Console\Commands\Product;

use App\Traits\ClientTrait;
use App\Traits\LoggerTrait;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class SyncDeptList extends Command
{
    use LoggerTrait;
    use ClientTrait;

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

    public function __construct()
    {
        parent::__construct();

        $this->url = env('BASE_URL');
    }

    public function handle()
    {
        try {
            $this->getClient()->request('GET', 'index.php/oaapi/oaapi/deptList');
        } catch (GuzzleException $exception) {
            $this->getLogger()->error($exception->getMessage());
        }
    }
}
