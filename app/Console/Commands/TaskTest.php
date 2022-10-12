<?php

namespace App\Console\Commands;

use App\Models\StaffDept;
use App\Models\StaffList;
use App\Models\StaffMainDept;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;

/**
 * @internal
 * @coversNothing
 */
class TaskTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'task:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'task test';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
    }

    /**
     * 修复OA同步完，部门为空的问题.
     */
    public function fixEmptyDepartment()
    {
        $staffs = StaffList::where('is_dimission', 1)->where('department', '')->get();
        if ($staffs->isEmpty()) {
            return;
        }

        $client = new Client(['base_uri' => env('BASE_URL'), 'timeout' => 5]);
        foreach ($staffs as $staff) {
            try {
                $response = $client->request('GET', 'index.php/crontab/ding/userGet', [
                    RequestOptions::QUERY => ['staff_id' => $staff->staff_id],
                ]);
                $json = json_decode($response->getBody()->getContents(), true);
                if (!isset($json['data']['orderInDepts'])) {
                    continue;
                }

                $orderInDepts = explode(',', trim($json['data']['orderInDepts'], '{}'));
                if (empty($orderInDepts)) {
                    continue;
                }

                StaffDept::where('staff_id', $staff->staff_id)->delete();
                StaffMainDept::where('staff_id', $staff->staff_id)->delete();

                $department = [];
                foreach ($orderInDepts as $k => $v) {
                    list($deptId, $order) = explode(':', $v);
                    $department[] = $deptId;

                    $sd = new StaffDept();
                    $sd->staff_id = $staff->staff_id;
                    $sd->department = $deptId;
                    $sd->order = $order;
                    $sd->modify_time = date('Y-m-d H:i:s');
                    $sd->save();

                    if (0 == $k) {
                        $smd = new StaffMainDept();
                        $smd->staff_id = $staff->staff_id;
                        $smd->department = $deptId;
                        $smd->modify_time = date('Y-m-d H:i:s');
                        $smd->save();
                    }
                }

                $staff->department = join(',', $department);
                $staff->save();
            } catch (GuzzleException | Exception $exception) {
                dump($exception->getMessage());
            }
        }
    }

    /**
     * 删除产品编辑导入模板.
     */
    public function deleteTemplate()
    {
        $client = new Client(['base_uri' => 'http://v2.product.nantang-tech.com', 'timeout' => 5]);

        try {
            $client->request('GET', 'index.php/api/v1/ExternalAPI/deleteTemplate');
        } catch (GuzzleException $exception) {
        }
    }
}
