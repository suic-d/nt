<?php

namespace App\Helpers;

use Illuminate\Support\Env;

class Lazada extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'lazada';

    public function __construct()
    {
        $this->url = env('LAZADA_NOTIFY');
    }
}
