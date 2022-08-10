<?php

namespace App\Helpers;

class Lazada extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'lazada';

    /**
     * @var string
     */
    protected $url = 'http://lazada.back.nantang-tech.com/listing/sku/modify/notification';
}
