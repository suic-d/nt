<?php

namespace App\Helpers;

class Europe extends ShopGrantAuthAbstract
{
    /**
     * @var string[]
     */
    protected $sites = [
        'UK', // 英国
        'IT', // 意大利
        'ES', // 西班牙
        'NL', // 荷兰
        'FR', // 法国
        'DE', // 德国
        'SE', // 瑞典
        'PL', // 波兰
    ];

    /**
     * @var string
     */
    protected $area = 'EU';
}
