<?php

namespace App\Console\Commands\Product;

use App\Models\DeptList;
use App\Traits\ClientTrait;
use App\Traits\LoggerTrait;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class SyncDeptUser extends Command
{
    use LoggerTrait;
    use ClientTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:syncDeptUser';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取部门下用户';

    public function __construct()
    {
        parent::__construct();

        $this->url = env('BASE_URL');
    }

    public function handle()
    {
        $requests = function () {
            $deptIdArr = DeptList::get(['dept_id'])->pluck('dept_id');
            foreach ($deptIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/deptUser?id='.$value);
            }
        };
        $pool = new Pool($this->getClient(), $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
            },
            'rejected' => function ($reason, $idx) {
                $this->getLogger()->error('dept_id = '.$idx.' '.$reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }
}
