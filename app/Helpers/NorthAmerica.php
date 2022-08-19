<?php

namespace App\Helpers;

class NorthAmerica extends ShopGrantAuthAbstract
{
    /**
     * @var string[]
     */
    protected $sites = [
        'US', // 美国
        'CA', // 加拿大
        'MX', // 墨西哥
    ];

    /**
     * @var string
     */
    protected $area = 'NA';
}
