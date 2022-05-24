<?php

namespace App\Repositories;

use App\Models\DeptList;
use App\Models\StaffDept;
use App\Models\StaffList;
use App\Models\StaffMainDept;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Throwable;

class OaRepository
{
    /**
     * @var string
     */
    private $oaUrl = 'https://dbsrv.nterp.nantang-tech.com';

    /**
     * @var string
     */
    private $oaAppKey = 'gYw5ogbgqU91Mub8xA0H';

    /**
     * @var string
     */
    private $oaAppSecret = '72e80fef340b576bac6af717nterp_oa';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $authorization;

    public function __construct()
    {
        $this->client = new Client(['base_uri' => $this->oaUrl, 'verify' => false]);
    }

    /**
     * @return string
     */
    public function getAuthorization()
    {
        if (is_null($this->authorization)) {
            try {
                $response = $this->client->request('GET', 'rest/authorization/authorize', [
                    RequestOptions::QUERY => [
                        'appKey' => $this->oaAppKey,
                        'appSecret' => $this->oaAppSecret,
                    ],
                ]);
                $json = json_decode($response->getBody()->getContents(), true);
                $this->authorization = $json['data']['authorization'] ?? '';
            } catch (Throwable $exception) {
            }
        }

        return $this->authorization;
    }

    /**
     * @return array
     */
    public function getDeptList()
    {
        try {
            $response = $this->client->request('GET', 'rest/ding/dept', [
                RequestOptions::HEADERS => ['Authorization' => $this->getAuthorization()],
            ]);
            $json = json_decode($response->getBody()->getContents(), true);

            return $json['data']['depts'] ?? [];
        } catch (Throwable $exception) {
        }

        return [];
    }

    /**
     * @param string $deptId
     *
     * @return array
     */
    public function getDeptUser($deptId)
    {
        try {
            $response = $this->client->request('GET', 'rest/ding/user/indept', [
                RequestOptions::HEADERS => ['Authorization' => $this->getAuthorization()],
                RequestOptions::QUERY => ['deptId' => $deptId],
            ]);
            $json = json_decode($response->getBody()->getContents(), true);

            return $json['data']['users'] ?? [];
        } catch (Throwable $exception) {
        }

        return [];
    }

    /**
     * @param string $staffId
     *
     * @return array
     */
    public function getStaffDetail($staffId)
    {
        try {
            $response = $this->client->request('GET', 'rest/ding/user/'.$staffId, [
                RequestOptions::HEADERS => ['Authorization' => $this->getAuthorization()],
            ]);
            $json = json_decode($response->getBody()->getContents(), true);

            return $json['data']['userDetails'] ?? [];
        } catch (Throwable $exception) {
        }

        return [];
    }

    public function syncDeptList()
    {
        if (empty($deptList = $this->getDeptList())) {
            return;
        }

        foreach ($deptList as $item) {
            $dept = DeptList::find($item['id']);
            if (is_null($dept)) {
                $dept = new DeptList();
            }

            $dept->parent_id = $item['parentId'];
            $dept->dept_id = $item['id'];
            $dept->dept_name = $item['name'];
            $dept->dept_manager_userid_list = $item['leaderUserId'];
            $dept->order = $item['deptOrder'];
            $dept->modify_time = date('Y-m-d H:i:s');
            $dept->save();
        }
    }

    public function syncDeptUser()
    {
        $depts = DeptList::get();
        if ($depts->isEmpty()) {
            return;
        }

        foreach ($depts as $dept) {
            if (empty($users = $this->getDeptUser($dept->dept_id))) {
                continue;
            }

            foreach ($users as $user) {
                $staff = StaffList::where('staff_id', $user['id'])->first();
                if (is_null($staff)) {
                    $staff = new StaffList();
                }
                $staff->staff_id = $user['id'];
                $staff->staff_name = $user['name'];
                $staff->is_dimission = (4 == $user['employeeStatus']) ? 2 : 1;
                $staff->is_leader = ('true' == $user['isLeader']) ? 1 : 0;
                $staff->department = $user['deptIds'];
                $staff->job_number = $user['jobnumber'];
                $staff->position = $user['position'];
                $staff->employee_type = $user['employeeType'];
                $staff->employee_status = $user['employeeStatus'];
                $staff->modify_time = date('Y-m-d H:i:s');
                $staff->save();

                StaffDept::where('staff_id', $staff->staff_id)->get()->each(function (StaffDept $item) {
                    $item->delete();
                });
                if (!empty($user['deptIds'])) {
                    foreach (explode(',', $user['deptIds']) as $deptId) {
                        $staffDept = new StaffDept();
                        $staffDept->staff_id = $staff->staff_id;
                        $staffDept->department = $deptId;
                        $staffDept->order = $user['order'];
                        $staffDept->modify_time = date('Y-m-d H:i:s');
                        $staffDept->save();
                    }
                }
            }
        }
    }

    public function syncStaffDetail()
    {
        $staffs = StaffList::get();
        if ($staffs->isEmpty()) {
            return;
        }

        foreach ($staffs as $staff) {
            $user = $this->getStaffDetail($staff->staff_id);
            if (empty($user)) {
                continue;
            }

            $staff->union_id = $user['unionId'];
            $staff->mobile = $user['mobile'];
            $staff->work_place = $user['workPlace'];
            $staff->avatar = $user['avatar'];
            $staff->is_admin = ('true' == $user['isAdmin']) ? 1 : 0;
            $staff->is_boss = ('true' == $user['isBoss']) ? 1 : 0;
            $staff->is_hide = ('true' == $user['isHide']) ? 1 : 0;
            $staff->active = ('true' == $user['active']) ? 1 : 0;
            $staff->hired_date = $user['hiredDate'];
            $staff->email = $user['email'];
            $staff->remark = $user['remark'];
            $staff->modify_time = date('Y-m-d H:i:s');
            $staff->save();

            StaffMainDept::where('staff_id', $staff->staff_id)->get()->each(function (StaffMainDept $item) {
                $item->delete();
            });
            $staffMainDept = new StaffMainDept();
            $staffMainDept->staff_id = $staff->staff_id;
            $staffMainDept->department = $user['mainDeptId'];
            $staffMainDept->modify_time = date('Y-m-d H:i:s');
            $staffMainDept->save();
        }
    }
}
