<?php

namespace App\Console\Commands\Product;

use App\Models\AliExpressListingProhibit;
use App\Models\AmazonForbidPublishReason;
use App\Models\EbayBanOn;
use App\Models\LazadaBanOn;
use Illuminate\Console\Command;

class PublishForbidden extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'product:publishForbidden';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '禁上报表';

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
        $this->pullAmazonForbidden();
        $this->pullEbayForbidden();
        $this->pullLazadaForbidden();
        $this->pullAliExpressForbidden();
    }

    /**
     * amazon.
     */
    public function pullAmazonForbidden()
    {
        $page = 1;
        $limit = 200;
        while (true) {
            $amazonForbidPublishReasons = AmazonForbidPublishReason::oldest('created_at')
                ->forPage($page, $limit)
                ->get()
            ;
            if ($amazonForbidPublishReasons->isEmpty()) {
                break;
            }

            $amazonForbidPublishReasons->each(function ($item) {
                $publishForbidden = \App\Models\PublishForbidden::with('publishForbiddenReasons')
                    ->where('spu', $item->spu)
                    ->where('platform', 'amazon')
                    ->first()
                ;
                if (is_null($publishForbidden)) {
                    $publishForbidden = new \App\Models\PublishForbidden();
                    $publishForbidden->spu = $item->spu;
                    $publishForbidden->platform = 'amazon';
                } else {
                    $publishForbidden->publishForbiddenReasons()->delete();
                }
                $publishForbidden->operator_name = (string) $item->operator_name;
                $publishForbidden->developer_name = (string) $item->developer_name;
                $publishForbidden->updated_at = date('Y-m-d H:i:s');
                if (!empty($item->updated_at) && '0000-00-00 00:00:00' != $item->updated_at) {
                    $publishForbidden->updated_at = $item->updated_at;
                } elseif (!empty($item->created_at) && '0000-00-00 00:00:00' != $item->created_at) {
                    $publishForbidden->updated_at = $item->created_at;
                }
                $publishForbidden->save();
                $publishForbidden->publishForbiddenReasons()->create(['reason' => (string) $item->reason]);
            });
            unset($amazonForbidPublishReasons);
            ++$page;
        }
    }

    /**
     * ebay.
     */
    public function pullEbayForbidden()
    {
        $page = 1;
        $limit = 200;
        while (true) {
            $ebayBanOnModels = EbayBanOn::with(['ebayBanOnLists' => function ($query) {
                $query->latest('updated_at');
            }])
                ->oldest('updated_at')
                ->forPage($page, $limit)
                ->get()
            ;
            if ($ebayBanOnModels->isEmpty()) {
                break;
            }

            $ebayBanOnModels->each(function ($item) {
                $publishForbidden = \App\Models\PublishForbidden::with('publishForbiddenReasons')
                    ->where('spu', $item->spu)
                    ->where('platform', 'ebay')
                    ->first()
                ;
                if (is_null($publishForbidden)) {
                    $publishForbidden = new \App\Models\PublishForbidden();
                    $publishForbidden->spu = $item->spu;
                    $publishForbidden->platform = 'ebay';
                } else {
                    $publishForbidden->publishForbiddenReasons()->delete();
                }
                $publishForbidden->updated_at = $item->updated_at;
                $publishForbidden->save();

                if ($item->ebayBanOnLists->isNotEmpty()) {
                    $banOnLists = $item->ebayBanOnLists->reduce(function ($carry, $item) {
                        if (!empty($item->sku) && !isset($carry[$item->sku])) {
                            $carry[$item->sku] = $item;
                        }

                        return $carry;
                    }, []);
                    if (!empty($banOnLists)) {
                        foreach ($banOnLists as $value) {
                            if (empty($publishForbidden->operator_name) && !empty($value->staff_name)) {
                                $publishForbidden->operator_name = (string) $value->staff_name;
                                $publishForbidden->save();
                            }
                            if (empty($publishForbidden->developer_name) && !empty($value->developer_name)) {
                                $publishForbidden->developer_name = (string) $value->developer_name;
                                $publishForbidden->save();
                            }
                            $publishForbidden->publishForbiddenReasons()->create(['reason' => (string) $value->reason]);
                        }
                    }
                }
            });
            unset($ebayBanOnModels);
            ++$page;
        }
    }

    /**
     * lazada.
     */
    public function pullLazadaForbidden()
    {
        $page = 1;
        $limit = 200;
        while (true) {
            $lazadaBanOnModels = LazadaBanOn::with(['lazadaBanOnLists' => function ($query) {
                $query->latest('updated_at');
            }])
                ->oldest('updated_at')
                ->forPage($page, $limit)
                ->get()
            ;
            if ($lazadaBanOnModels->isEmpty()) {
                break;
            }

            $lazadaBanOnModels->each(function ($item) {
                $publishForbidden = \App\Models\PublishForbidden::with('publishForbiddenReasons')
                    ->where('spu', $item->spu)
                    ->where('platform', 'lazada')
                    ->first()
                ;
                if (is_null($publishForbidden)) {
                    $publishForbidden = new \App\Models\PublishForbidden();
                    $publishForbidden->spu = $item->spu;
                    $publishForbidden->platform = 'lazada';
                } else {
                    $publishForbidden->publishForbiddenReasons()->delete();
                }
                $publishForbidden->updated_at = $item->updated_at;
                $publishForbidden->save();

                if ($item->lazadaBanOnLists->isNotEmpty()) {
                    $banOnLists = $item->lazadaBanOnLists->reduce(function ($carry, $item) {
                        if (!empty($item->sku) && !isset($carry[$item->sku])) {
                            $carry[$item->sku] = $item;
                        }

                        return $carry;
                    }, []);
                    if (!empty($banOnLists)) {
                        foreach ($banOnLists as $value) {
                            if (empty($publishForbidden->operator_name) && !empty($value->staff_name)) {
                                $publishForbidden->operator_name = (string) $value->staff_name;
                                $publishForbidden->save();
                            }
                            if (empty($publishForbidden->developer_name) && !empty($value->developer_name)) {
                                $publishForbidden->developer_name = (string) $value->developer_name;
                                $publishForbidden->save();
                            }
                            $publishForbidden->publishForbiddenReasons()->create(['reason' => (string) $value->reason]);
                        }
                    }
                }
            });
            unset($lazadaBanOnModels);
            ++$page;
        }
    }

    /**
     * aliexpress.
     */
    public function pullAliExpressForbidden()
    {
        $page = 1;
        $limit = 200;
        while (true) {
            $aliexpressListingProhibits = AliExpressListingProhibit::oldest('created_at')
                ->forPage($page, $limit)
                ->get()
            ;
            if ($aliexpressListingProhibits->isEmpty()) {
                break;
            }

            $aliexpressListingProhibits->each(function ($item) {
                $publishForbidden = \App\Models\PublishForbidden::with('publishForbiddenReasons')
                    ->where('spu', $item->spu)
                    ->where('platform', 'aliexpress')
                    ->first()
                ;
                if (is_null($publishForbidden)) {
                    $publishForbidden = new \App\Models\PublishForbidden();
                    $publishForbidden->spu = $item->spu;
                    $publishForbidden->platform = 'aliexpress';
                } else {
                    $publishForbidden->publishForbiddenReasons()->delete();
                }
                $publishForbidden->operator_name = (string) $item->operator_name;
                $publishForbidden->developer_name = (string) $item->developer_name;
                $publishForbidden->updated_at = date('Y-m-d H:i:s');
                if (!empty($item->updated_at) && '0000-00-00 00:00:00' != $item->updated_at) {
                    $publishForbidden->updated_at = $item->updated_at;
                } elseif (!empty($item->created_at) && '0000-00-00 00:00:00' != $item->created_at) {
                    $publishForbidden->updated_at = $item->created_at;
                }
                $publishForbidden->save();
                $publishForbidden->publishForbiddenReasons()->create(['reason' => (string) $item->reason]);
            });
            unset($aliexpressListingProhibits);
            ++$page;
        }
    }
}
