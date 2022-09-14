<?php

namespace App\Console\Commands;

use App\Models\Assess\DeptList;
use App\Models\StaffDept;
use App\Models\StaffList;
use App\Models\StaffMainDept;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;

class OaApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oa-api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步OA';

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    private $authorization;

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client(['base_uri' => env('BASE_URL_DBRSV'), 'verify' => false]);
    }

    public function handle()
    {
        DeptList::get(['dept_id'])->each(function ($item) {
            $this->saveDeptUser($item->dept_id);
        });
    }

    /**
     * @param int $deptId
     */
    public function saveDeptUser($deptId)
    {
        foreach ($this->getUsers($deptId) as $u) {
            $staff = StaffList::where('staff_id', $u['id'])->first();
            if (is_null($staff)) {
                $staff = new StaffList();
            }
            $staff->staff_id = $u['id'];
            $staff->staff_name = $u['name'];
            $staff->is_dimission = ('4' === $u['employeeStatus']) ? 2 : 1;
            $staff->is_leader = ('true' === $u['isLeader']) ? 1 : 0;
            $staff->department = empty($u['deptIds']) ? $deptId : $u['deptIds'];
            $staff->job_number = $u['jobnumber'];
            $staff->position = $u['position'];
            $staff->employee_type = empty($u['employeeType']) ? 0 : $u['employeeType'];
            $staff->employee_status = empty($u['employeeStatus']) ? -1 : $u['employeeStatus'];
            $staff->modify_time = date('Y-m-d H:i:s');
            $staff->save();

            $deptIds = empty($u['deptIds']) ? [$deptId] : explode(',', $u['deptIds']);
            $this->saveStaffDept($staff->staff_id, $deptIds, $u['order']);

            if (!empty($userDetail = $this->getUserDetail($staff->staff_id))) {
                $staff->union_id = $userDetail['unionId'];
                $staff->mobile = $userDetail['mobile'];
                $staff->work_place = $userDetail['workPlace'];
                $staff->avatar = $userDetail['avatar'];
                $staff->is_admin = ('true' === $userDetail['isAdmin']) ? 1 : 0;
                $staff->is_boss = ('true' === $userDetail['isBoss']) ? 1 : 0;
                $staff->is_hide = ('true' === $userDetail['isHide']) ? 1 : 0;
                $staff->active = ('true' === $userDetail['active']) ? 1 : 0;
                $staff->hired_date = empty($userDetail['hiredDate']) ? '0000-00-00' : $userDetail['hiredDate'];
                $staff->email = $userDetail['email'];
                $staff->remark = $userDetail['remark'];
                $staff->save();

                $mainDeptId = empty($userDetail['mainDeptId']) ? $deptId : $userDetail['mainDeptId'];
                $this->saveStaffMainDept($staff->staff_id, $mainDeptId);
            }
        }
    }

    /**
     * @param string $staffId
     * @param string $deptId
     */
    public function saveStaffMainDept($staffId, $deptId)
    {
        StaffMainDept::where('staff_id', $staffId)->delete();
        $staffMainDept = new StaffMainDept();
        $staffMainDept->staff_id = $staffId;
        $staffMainDept->department = $deptId;
        $staffMainDept->modify_time = date('Y-m-d H:i:s');
        $staffMainDept->save();
    }

    /**
     * @param string $staffId
     * @param array  $deptIds
     * @param string $order
     */
    public function saveStaffDept($staffId, $deptIds, $order)
    {
        StaffDept::where('staff_id', $staffId)->delete();
        foreach ($deptIds as $v) {
            $staffDept = new StaffDept();
            $staffDept->staff_id = $staffId;
            $staffDept->department = $v;
            $staffDept->order = $order;
            $staffDept->modify_time = date('Y-m-d H:i:s');
            $staffDept->save();
        }
    }

    /**
     * @param int $deptId
     *
     * @return array
     */
    public function getUsers($deptId)
    {
        try {
            $response = $this->client->request('GET', 'rest/ding/user/indept', [
                RequestOptions::HEADERS => ['Authorization' => $this->getAuthorization()],
                RequestOptions::QUERY => ['deptId' => $deptId],
            ]);
            $json = json_decode($response->getBody()->getContents(), true);

            return $json['data']['users'] ?? [];
        } catch (GuzzleException $exception) {
            dump($exception->getMessage());
        }

        return [];
    }

    /**
     * @param string $staffId
     *
     * @return array
     */
    public function getUserDetail($staffId)
    {
        try {
            $response = $this->client->request('GET', 'rest/ding/user/'.$staffId, [
                RequestOptions::HEADERS => ['Authorization' => $this->getAuthorization()],
            ]);
            $json = json_decode($response->getBody()->getContents(), true);

            return $json['data']['userDetails'] ?? [];
        } catch (GuzzleException $exception) {
            dump($exception->getMessage());
        }

        return [];
    }

    /**
     * @return string
     */
    public function getAuthorization()
    {
        if (is_null($this->authorization)) {
            $this->generateAuthorization();
        }

        return $this->authorization;
    }

    private function generateAuthorization()
    {
        try {
            $response = $this->client->request('GET', 'rest/authorization/authorize', [
                RequestOptions::QUERY => ['appKey' => env('OA_APP_KEY'), 'appSecret' => env('OA_APP_SECRET')],
            ]);
            $json = json_decode($response->getBody()->getContents(), true);
            $this->authorization = $json['data']['authorization'] ?? '';
        } catch (GuzzleException | Exception $exception) {
            dump($exception->getMessage());
        }
    }
}
