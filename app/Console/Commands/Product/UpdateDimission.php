<?php

namespace App\Console\Commands\Product;

use App\Models\StaffList;
use App\Traits\ClientTrait;
use App\Traits\LoggerTrait;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;

class UpdateDimission extends Command
{
    use LoggerTrait;
    use ClientTrait;

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

    public function __construct()
    {
        parent::__construct();

        $this->url = env('BASE_URL');
    }

    public function handle()
    {
        $staffIdArr = StaffList::where('is_dimission', 1)->get(['staff_id'])->pluck('staff_id');
        foreach ($staffIdArr as $v) {
            try {
                $this->getClient()->request('GET', 'index.php/oaapi/oaapi/updateDimission', [
                    RequestOptions::QUERY => ['staff_id' => $v],
                ]);
            } catch (GuzzleException $exception) {
                $this->getLogger()->error('staff_id = '.$v.' '.$exception->getMessage());
            }
        }
    }
}
