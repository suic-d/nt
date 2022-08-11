<?php

namespace App\Helpers;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class OaModel
{
    const URL = 'https://dbsrv.nterp.nantang-tech.com';

    const APP_KEY = 'gYw5ogbgqU91Mub8xA0H';

    const APP_SECRET = '72e80fef340b576bac6af717nterp_oa';

    /**
     * @return string
     */
    public static function getAuth()
    {
        $client = new Client(['base_uri' => self::URL, 'verify' => false]);

        try {
            $response = $client->request('GET', 'rest/authorization/authorize', [
                RequestOptions::QUERY => ['appKey' => self::APP_KEY, 'appSecret' => self::APP_SECRET],
            ]);
            $json = json_decode($response->getBody()->getContents(), true);

            return $json['data']['authorization'] ?? '';
        } catch (Exception | GuzzleException $exception) {
        }

        return '';
    }

    /**
     * @param array $param
     *
     * @return array
     */
    public static function sendPriceChangeToDev($param)
    {
        $pushData['title'] = 'sku采购价变更通知';
        $pushData['text'] = sprintf(
            '尊敬的: %s\n\r亲，您创建的SKU：%s\n\r品名:%s\n\r采购价由%s元更新为%s元\n\r更新时间：%s\n\r修改人：%s\n\r请知悉！',
            $param['depart_name'].$param['developer'],
            $param['sku'],
            $param['sku_name'],
            $param['old_price'],
            $param['new_price'],
            $param['update_time'],
            $param['update_by']
        );
        $client = new Client(['base_uri' => 'https://gosvc.nterp.nantang-tech.com', 'verify' => false]);

        try {
            $response = $client->request('POST', 'gopush/dingtalk/asyncPush', [
                RequestOptions::JSON => $pushData,
                RequestOptions::HEADERS => ['Authorization' => self::getAuth()],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception | GuzzleException $exception) {
        }

        return [];
    }

    /**
     * @param array $param
     *
     * @return array
     */
    public static function sendPriceChangeToSeller($param)
    {
        return ['code' => 0, 'msg' => 'push success'];
    }

    /**
     * @param array $param
     *
     * @return array
     */
    public static function sendSaleStateToDev($param)
    {
        $pushData['title'] = 'sku停售通知';
        $pushData['text'] = sprintf(
            '尊敬的: %s\n\r亲，您创建的SKU：%s\n\r在%s被%s标记为停售状态 \n\r停售原因：%s\n\r请知悉！',
            $param['depart_name'].$param['developer'],
            $param['sku'],
            $param['update_time'],
            $param['update_by'],
            $param['stop_sale_reason']
        );
        $client = new Client(['base_uri' => 'https://gosvc.nterp.nantang-tech.com', 'verify' => false]);

        try {
            $response = $client->request('POST', 'gopush/dingtalk/asyncPush', [
                RequestOptions::JSON => $pushData,
                RequestOptions::HEADERS => ['Authorization' => self::getAuth()],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception | GuzzleException $exception) {
        }

        return [];
    }
}
