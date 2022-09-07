<?php

namespace App\Console\Commands;

use App\Models\Assess\AssessFollowerDetail;
use App\Models\Assess\AssessUserDetail;
use App\Models\Assess\DeptList;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;

class AssessHelper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assess-helper';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'assess helper';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
    }

    /**
     * @param int   $deptId
     * @param array $cache
     */
    public function getLastChildren($deptId, &$cache)
    {
        $children = DeptList::where('parent_id', $deptId)->get();
        if ($children->isEmpty()) {
            $cache[] = $deptId;
        } else {
            foreach ($children as $item) {
                $this->getLastChildren($item->dept_id, $cache);
            }
        }
    }

    /**
     * @param int $userId
     */
    public function auth($userId)
    {
        $parentIds = [133596419, 133685394, 133653357, 348799669, 321161080, 414293614, 133597412, 144439973];
        $parents = DeptList::whereIn('dept_id', $parentIds)->get();
        $cache = [];
        foreach ($parents as $item) {
            $this->getLastChildren($item->dept_id, $cache);
        }

        $depts = DeptList::whereIn('dept_id', $cache)->get();
        if ($depts->isEmpty()) {
            return;
        }

        $assessUserMap = AssessUserDetail::where('user_id', $userId)->get()->keyBy(function ($item) {
            return $item->dept_id;
        });
        foreach ($depts as $d) {
            if (isset($assessUserMap[$d->dept_id])) {
                continue;
            }

            $assessUserDetail = new AssessUserDetail();
            $assessUserDetail->user_id = $userId;
            $assessUserDetail->dept_id = $d->dept_id;
            $assessUserDetail->save();
        }
    }

    /**
     * @param int $userId
     */
    public function assessAuth($userId)
    {
        $followers = DB::connection('assess')
            ->table('assess_demand')
            ->distinct()
            ->get(['follower_id'])
        ;
        if ($followers->isEmpty()) {
            return;
        }

        $followerMap = AssessFollowerDetail::where('user_id', $userId)->get()->keyBy(function ($item) {
            return $item->staff_id;
        });
        foreach ($followers as $f) {
            if (isset($followerMap[$f->follower_id])) {
                continue;
            }

            $assessFollowerDetail = new AssessFollowerDetail();
            $assessFollowerDetail->user_id = $userId;
            $assessFollowerDetail->staff_id = $f->follower_id;
            $assessFollowerDetail->save();
        }
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return string
     */
    public function getToken($username, $password)
    {
        $client = new Client(['base_uri' => env('BASE_URL_DBRSV'), 'verify' => false]);

        try {
            $response = $client->request('GET', 'rest/auth/user/login', [RequestOptions::QUERY => [
                'mobile' => $username,
                'password' => $password,
            ]]);
            $json = json_decode($response->getBody()->getContents(), true);

            return $json['data']['token'] ?? '';
        } catch (Exception | GuzzleException $exception) {
            dump($exception->getMessage());
        }

        return '';
    }

    /**
     * @param string $token
     */
    public function deleteToken($token)
    {
        $client = new Client(['base_uri' => env('BASE_URL_ASSESS'), 'verify' => false]);

        try {
            $client->request('GET', 'index.php/assess/test/deleteToken', [
                RequestOptions::QUERY => ['token' => $token],
            ]);
        } catch (GuzzleException $exception) {
            dump($exception->getMessage());
        }
    }
}
