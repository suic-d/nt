<?php

namespace App\Helpers;

class EBay extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'ebay';

    /**
     * @var string
     */
    protected $url = 'http://ebay.back.nantang-tech.com/index.php/listing/sku/modify/notification';
}
