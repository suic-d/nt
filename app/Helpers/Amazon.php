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
        $this->url = Env::get('AMAZON_NOTIFY');
    }
}
