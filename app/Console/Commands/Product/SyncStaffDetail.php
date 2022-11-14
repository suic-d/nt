<?php

namespace App\Console\Commands\Product;

use App\Models\StaffList;
use App\Traits\ClientTrait;
use App\Traits\LoggerTrait;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class SyncStaffDetail extends Command
{
    use LoggerTrait;
    use ClientTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:syncStaffDetail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '获取员工详情';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $requests = function () {
            $staffIdArr = StaffList::where('is_dimission', 1)->get(['staff_id'])->pluck('staff_id');
            foreach ($staffIdArr as $value) {
                yield $value => new Request('GET', 'index.php/oaapi/oaapi/staffDetail?id='.$value);
            }
        };
        $pool = new Pool($this->getClient(), $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
            },
            'rejected' => function ($reason, $idx) {
                $this->getLogger()->error('staff_id = '.$idx.' '.$reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }
}
