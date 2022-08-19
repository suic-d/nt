<?php

namespace App\Helpers;

class FarEast extends ShopGrantAuthAbstract
{
    /**
     * @var string[]
     */
    protected $sites = [
        'JP', // 日本
    ];

    /**
     * @var string
     */
    protected $area = 'FE';
}
