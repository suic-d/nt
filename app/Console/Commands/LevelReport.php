<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LevelReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crontab:level_report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'sku等级报表';

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
        $perPage = 1000;
        $lastPage = DB::table('nt_product_pool', 'pp')
            ->join('nt_sku as sk', 'sk.sku', '=', 'pp.sku')
            ->join('nt_spu_info as si', 'si.spu', '=', 'pp.spu')
            ->leftJoin('nt_supplier as su', 'su.id', '=', 'sk.supplier_id')
            ->leftJoin('nt_dictionary as di', 'di.id', '=', 'su.shipping_province')
            ->orderBy('pp.done_at', 'desc')
            ->paginate($perPage, ['pp.sku'], 'page', 1)
            ->lastPage()
        ;
        $client = new Client(['base_uri' => 'http://dev.laravel.cn', 'verify' => false]);
        $requests = function () use ($perPage, $lastPage) {
            for ($page = 1; $page <= $lastPage; ++$page) {
                yield new Request('GET', 'api/level_report/update?page='.$page.'&limit='.$perPage);
            }
        };
        $pool = new Pool($client, $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response) {
                dump($response->getBody()->getContents());
            },
            'rejected' => function ($reason) {
                dump($reason->getMessage());
            },
        ]);
        $pool->promise()->wait();
    }
}
