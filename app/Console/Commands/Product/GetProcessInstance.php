<?php

namespace App\Console\Commands\Product;

use App\Models\SkuReview;
use App\Traits\ClientTrait;
use App\Traits\LoggerTrait;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Console\Command;

class GetProcessInstance extends Command
{
    use LoggerTrait;
    use ClientTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:getProcessInstance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '钉钉审核';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $requests = function () {
            $reviews = SkuReview::whereIn('process_status', ['NEW', 'RUNNING'])
                ->orderBy('id')
                ->forPage(1, 200)
                ->get(['id'])
            ;
            foreach ($reviews as $v) {
                yield $v->id => new Request('GET', 'index.php/api/v1/ExternalAPI/getProcessInstance?review_id='.$v->id);
            }
        };
        $pool = new Pool($this->getClient(), $requests(), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $idx) {
                $this->getLogger()->info('review_id = '.$idx.' '.$response->getBody()->getContents());
                $this->getLogger()->close();
            },
            'rejected' => function ($reason, $idx) {
                $this->getLogger()->error('review_id = '.$idx.' '.$reason->getMessage());
                $this->getLogger()->close();
            },
        ]);
        $pool->promise()->wait();
    }
}
