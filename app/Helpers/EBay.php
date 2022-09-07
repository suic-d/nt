<?php

namespace App\Helpers;

use Illuminate\Support\Env;

class EBay extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'ebay';

    public function __construct()
    {
        $this->url = Env::get('EBAY_NOTIFY');
    }
}
