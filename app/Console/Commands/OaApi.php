<?php

namespace App\Console\Commands;

use App\Models\StaffDept;
use App\Models\StaffList;
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
        $this->getDeptUser(412715518);
    }

    /**
     * @param int $deptId
     */
    public function getDeptUser($deptId)
    {
        try {
            $response = $this->client->request('GET', 'rest/ding/user/indept', [
                RequestOptions::HEADERS => ['Authorization' => $this->getAuthorization()],
                RequestOptions::QUERY => ['deptId' => $deptId],
            ]);
            $json = json_decode($response->getBody()->getContents(), true);
            if (isset($json['data']['users']) && !empty($json['data']['users'])) {
                foreach ($json['data']['users'] as $u) {
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

                    StaffDept::where('staff_id', $staff->staff_id)->delete();
                    if (empty($u['deptIds'])) {
                        $staffDept = new StaffDept();
                        $staffDept->staff_id = $staff->staff_id;
                        $staffDept->department = $deptId;
                        $staffDept->order = $u['order'];
                        $staffDept->modify_time = date('Y-m-d H:i:s');
                        $staffDept->save();
                    } else {
                        foreach (explode(',', $u['deptIds']) as $v) {
                            $staffDept = new StaffDept();
                            $staffDept->staff_id = $staff->staff_id;
                            $staffDept->department = $v;
                            $staffDept->order = $u['order'];
                            $staffDept->modify_time = date('Y-m-d H:i:s');
                            $staffDept->save();
                        }
                    }
                }
            }
        } catch (GuzzleException $exception) {
        }
    }

    /**
     * @return string
     */
    private function getAuthorization()
    {
        if (is_null($this->authorization)) {
            try {
                $response = $this->client->request('GET', 'rest/authorization/authorize', [
                    RequestOptions::QUERY => ['appKey' => env('OA_APP_KEY'), 'appSecret' => env('OA_APP_SECRET')],
                ]);
                $json = json_decode($response->getBody()->getContents(), true);
                $this->authorization = $json['data']['authorization'] ?? '';
            } catch (GuzzleException | Exception $exception) {
            }
        }

        return $this->authorization;
    }
}
