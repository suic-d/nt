<?php

namespace App\Helpers;

use Illuminate\Support\Env;

class Shopee extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'shopee';

    public function __construct()
    {
        $this->url = env('SHOPEE_NOTIFY');
    }
}
