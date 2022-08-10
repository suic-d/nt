<?php

namespace App\Helpers;

class AliExpress extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'aliexpress';

    /**
     * @var string
     */
    protected $url = 'http://aliexpress.php.nantang-tech.com/listing/sku/modify/notification';
}
