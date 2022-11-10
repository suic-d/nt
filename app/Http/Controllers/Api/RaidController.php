<?php

namespace App\Http\Controllers\Api;

use App\Helpers\BurningPlain;
use App\Helpers\WarSongGulch;
use App\Http\Controllers\Controller;
use App\Models\Local\AdvertLog;
use App\Models\Local\Buff;
use App\Models\Local\Gear;
use App\Models\Local\Raid;
use App\Models\Local\RaidLog;
use Illuminate\Http\Request;

class RaidController extends Controller
{
    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function raidLogs(Request $request)
    {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);

        $paginator = RaidLog::latest()->paginate($limit, ['*'], 'page', $page);
        $data = [];
        if ($paginator->isNotEmpty()) {
            foreach ($paginator->items() as $item) {
                $data[] = [
                    'id' => $item->id,
                    'open_id' => $item->open_id,
                    'game_type' => $item->game_type,
                    'raid_name' => $item->raid_name,
                    'boss_name' => $item->boss_name,
                    'created_at' => $item->created_at->toDateTimeString(),
                ];
            }
        }

        return response()->json([
            'code' => 0,
            'msg' => '',
            'count' => $paginator->total(),
            'data' => $data,
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function advertLogs(Request $request)
    {
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);

        $paginator = AdvertLog::latest()->paginate($limit, ['*'], 'page', $page);
        $data = [];
        if ($paginator->isNotEmpty()) {
            foreach ($paginator->items() as $item) {
                $data[] = [
                    'id' => $item->id,
                    'open_id' => $item->open_id,
                    'num' => $item->num,
                    'created_at' => $item->created_at->toDateTimeString(),
                ];
            }
        }

        return response()->json([
            'code' => 0,
            'msg' => '',
            'count' => $paginator->total(),
            'data' => $data,
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRaids()
    {
        $raids = Raid::where('zb_got', 0)->orderBy('boss_level')->get();
        $data = [];
        if ($raids->isNotEmpty()) {
            $buffMap = Buff::get()->pluck('buff_detail', 'buff_id');

            foreach ($raids as $item) {
                $data[] = [
                    'id' => $item->id,
                    'raid_name' => $item->raid_name,
                    'boss_name' => $item->boss_name,
                    'boss_level' => $item->boss_level,
                    'buff' => $buffMap[$item->buff] ?? '',
                    'zb_name' => $item->zb_name,
                    'zb_level' => $item->zb_level,
                    'drop_rate' => count(array_unique(explode(',', $item->drop_rate))).'%',
                ];
            }
        }

        return response()->json([
            'code' => 0,
            'msg' => '',
            'data' => $data,
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGears(Request $request)
    {
        $gears = Gear::where('zb_got', 0)->orderBy('boss_level')->get();
        $data = [];
        if ($gears->isNotEmpty()) {
            $buffMap = Buff::get()->pluck('buff_detail', 'buff_id');

            foreach ($gears as $item) {
                $data[] = [
                    'id' => $item->id,
                    'raid_name' => $item->raid_name,
                    'boss_name' => $item->boss_name,
                    'boss_level' => $item->boss_level,
                    'buff' => $buffMap[$item->buff] ?? '',
                    'zb_name' => $item->zb_name,
                    'zb_level' => $item->zb_level,
                    'drop_rate' => count(array_unique(explode(',', $item->drop_rate))).'%',
                ];
            }
        }

        return response()->json([
            'code' => 0,
            'msg' => '',
            'data' => $data,
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRaidKills()
    {
        $instance = new WarSongGulch();
        $raidLogGroup = RaidLog::where('open_id', $instance->getOpenId())
            ->get()
            ->reduce(function ($carry, RaidLog $item) {
                if (!isset($carry[$item->raid_id][$item->boss_id])) {
                    $carry[$item->raid_id][$item->boss_id] = [
                        'raid_name' => $item->raid_name,
                        'boss_name' => $item->boss_name,
                        'kills' => 0,
                    ];
                }

                ++$carry[$item->raid_id][$item->boss_id]['kills'];

                return $carry;
            }, [])
        ;

        $data = [];
        foreach ($raidLogGroup as $values) {
            foreach ($values as $v) {
                $data[] = $v;
            }
        }
        $data = collect($data)->sortByDesc('kills');

        return response()->json([
            'code' => 0,
            'msg' => '',
            'data' => array_values($data->all()),
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGearKills()
    {
        $instance = new BurningPlain();
        $raidLogGroup = RaidLog::where('open_id', $instance->getOpenId())
            ->get()
            ->reduce(function ($carry, RaidLog $item) {
                if (!isset($carry[$item->raid_id][$item->boss_id])) {
                    $carry[$item->raid_id][$item->boss_id] = [
                        'raid_name' => $item->raid_name,
                        'boss_name' => $item->boss_name,
                        'kills' => 0,
                    ];
                }

                ++$carry[$item->raid_id][$item->boss_id]['kills'];

                return $carry;
            }, [])
        ;

        $data = [];
        foreach ($raidLogGroup as $values) {
            foreach ($values as $v) {
                $data[] = $v;
            }
        }
        $data = collect($data)->sortByDesc('kills');

        return response()->json([
            'code' => 0,
            'msg' => '',
            'data' => array_values($data->all()),
        ]);
    }
}
