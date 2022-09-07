<?php

namespace App\Helpers;

use Illuminate\Support\Env;

class Amazon extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'amazon';

    public function __construct()
    {
        $this->url = env('AMAZON_NOTIFY');
    }
}
