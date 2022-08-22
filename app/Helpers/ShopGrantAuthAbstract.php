<?php

namespace App\Helpers;

use App\Models\Fba\ShopAccount;
use Illuminate\Database\Query\Expression;

abstract class ShopGrantAuthAbstract
{
    /**
     * @var string
     */
    protected $reportType = '_GET_FBA_MYI_ALL_INVENTORY_DATA_';

    /**
     * @var string[]
     */
    protected $marketPlaceIds = [
        'UK' => 'A1F83G8C2ARO7P', // 英国
        'IT' => 'APJ6JRA9NG5V4', // 意大利
        'ES' => 'A1RKKUPIHCS9HS', // 西班牙
        'NL' => 'A1805IZSGTT6HS', // 荷兰
        'FR' => 'A13V1IB3VIYZZH', // 法国
        'DE' => 'A1PA6795UKMFR9', // 德国
        'US' => 'ATVPDKIKX0DER', // 美国
        'CA' => 'A2EUQ1WTGCTBG2', // 加拿大
        'JP' => 'A1VC38T7YXB528', // 日本
    ];

    /**
     * @var string[]
     */
    protected $serviceUrls = [
        'UK' => 'https://mws-eu.amazonservices.com', // 英国
        'IT' => 'https://mws-eu.amazonservices.com', // 意大利
        'ES' => 'https://mws-eu.amazonservices.com', // 西班牙
        'NL' => 'https://mws-eu.amazonservices.com', // 荷兰
        'FR' => 'https://mws-eu.amazonservices.com', // 法国
        'DE' => 'https://mws-eu.amazonservices.com', // 德国
        'US' => 'https://mws.amazonservices.com', // 美国
        'CA' => 'https://mws.amazonservices.ca', // 加拿大
        'JP' => 'https://mws.amazonservices.jp', // 日本
    ];

    /**
     * @param string $sql
     */
    public static function save($sql)
    {
        $file = '/www/sql/'.date('Ymd').'.sql';
        if ($handle = fopen($file, 'a')) {
            fwrite($handle, $sql);
            fclose($handle);
        }
    }

    /**
     * @param string $account
     * @param string $merchantId
     * @param string $refreshToken
     */
    public function refreshToken($account, $merchantId, $refreshToken)
    {
        $shops = ShopAccount::where('account', 'like', '%'.$account.'%')
            ->where(function ($query) {
                $query->whereIn('site', $this->sites)->orWhere('area', $this->area);
            })
            ->get()
        ;
        if ($shops->isEmpty()) {
            return;
        }

        foreach ($shops as $v) {
            $query = $v->newModelQuery()->where('shop_id', new Expression($v->shop_id))->toBase();
            $grammar = $query->getGrammar();
            $update = $grammar->compileUpdate($query, [
                'ia_auth' => new Expression(1),
                'merchant_id' => new Expression($grammar->quoteString($merchantId)),
                'aws_refresh_token' => new Expression($grammar->quoteString($refreshToken)),
            ]);
            echo $update.';'.PHP_EOL;
            self::save($update.';'.PHP_EOL);
        }
    }

    /**
     * @param string $account
     * @param string $merchantId
     * @param string $accessKeyId
     * @param string $secretKey
     * @param string $authToken
     */
    public function auth($account, $merchantId, $accessKeyId, $secretKey, $authToken)
    {
        $shops = ShopAccount::where('account', 'like', '%'.$account.'%')
            ->where(function ($query) {
                $query->whereIn('site', $this->sites)->orWhere('area', $this->area);
            })
            ->get()
        ;
        if ($shops->isEmpty()) {
            return;
        }

        foreach ($shops as $v) {
            $query = $v->newModelQuery()->where('id', new Expression($v->id))->toBase();
            $grammar = $query->getGrammar();
            $update = $grammar->compileUpdate($query, [
                'market_place_id' => new Expression($grammar->quoteString($this->marketPlaceIds[$v->site] ?? '')),
                'service_url' => new Expression($grammar->quoteString($this->serviceUrls[$v->site] ?? '')),
                'ia_auth' => new Expression(1),
                'merchant_id' => new Expression($grammar->quoteString($merchantId)),
                'aws_access_key_id' => new Expression($grammar->quoteString($accessKeyId)),
                'secret_key' => new Expression($grammar->quoteString($secretKey)),
                'mws_auth_token' => new Expression($grammar->quoteString($authToken)),
            ]);
            echo $update.';'.PHP_EOL;
            self::save($update.';'.PHP_EOL);
        }
    }
}
