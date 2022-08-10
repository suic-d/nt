<?php

namespace App\Helpers;

class Amazon extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'amazon';

    /**
     * @var string
     */
    protected $url = 'http://amazon.back.nantang-tech.com/listing/sku/modify/notification';
}
