<?php

namespace App\Repositories;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class PurchaseEvaluationRepository
{
    const AUTO_PUBLISH_API = 'http://auto-publish.php.nantang-tech.com';

    /**
     * @param string $spu
     */
    public static function pushSaleStateChangeSpu($spu)
    {
        $client = new Client(['base_uri' => self::AUTO_PUBLISH_API, 'verify' => false]);

        try {
            $client->request('POST', 'api/goods/inactive', [RequestOptions::JSON => ['spu' => [$spu]]]);
        } catch (GuzzleException $exception) {
        }
    }
}
