<?php

namespace App\Helpers;

use Illuminate\Support\Env;

class AliExpress extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'aliexpress';

    public function __construct()
    {
        $this->url = env('ALIEXPRESS_NOTIFY');
    }
}
