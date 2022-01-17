<?php

namespace App\Console\Commands;

use App\Models\Assess\AssessFollowerDetail;
use App\Models\Assess\AssessUserDetail;
use App\Models\Assess\DeptList;
use Illuminate\Console\Command;
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

        DeptList::whereIn('dept_id', $cache)->select()->each(function (DeptList $item) use ($userId) {
            $assessUserDetail = AssessUserDetail::where('user_id', $userId)
                ->where('dept_id', $item->dept_id)
                ->first()
            ;
            if (is_null($assessUserDetail)) {
                $model = new AssessUserDetail();
                $model->user_id = $userId;
                $model->dept_id = $item->dept_id;
                $model->save();
            }
        });
    }

    /**
     * @param int $userId
     */
    public function assessAuth($userId)
    {
        DB::connection('assess')
            ->table('assess_demand')
            ->distinct()
            ->get(['follower_id'])
            ->each(function ($item) use ($userId) {
                if (empty($item->follower_id)) {
                    return;
                }

                $assessFollowerDetail = AssessFollowerDetail::where('user_id', $userId)
                    ->where('staff_id', $item->follower_id)
                    ->first()
                ;
                if (is_null($assessFollowerDetail)) {
                    $model = new AssessFollowerDetail();
                    $model->user_id = $userId;
                    $model->staff_id = $item->follower_id;
                    $model->save();
                }
            })
        ;
    }
}
