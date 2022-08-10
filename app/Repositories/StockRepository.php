<?php

namespace App\Repositories;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class StockRepository
{
    const WMS_JAVA_API = 'http://wms.java.nantang-tech.com';

    /**
     * 直发仓SKU库存数量.
     *
     * @param string $sku
     *
     * @return int
     */
    public function getDirectHairWarehouseStock($sku)
    {
        $stock = 0;
        $client = new Client(['base_uri' => self::WMS_JAVA_API, 'verify' => false]);

        try {
            $response = $client->request('GET', 'warehouse/stock/sku/'.$sku);
            if (200 == $response->getStatusCode()) {
                $json = json_decode($response->getBody()->getContents());
                if (isset($json->data) && is_array($json->data) && !empty($json->data)) {
                    foreach ($json->data as $value) {
                        if (false === mb_strpos($value->whouseName, '直发仓', 0, 'UTF-8')) {
                            continue;
                        }
                        $stock += isset($value->skuUsableCount) ? (int) $value->skuUsableCount : 0;
                    }
                }
            }
        } catch (GuzzleException $exception) {
        }

        return $stock;
    }
}
