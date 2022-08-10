<?php

namespace App\Helpers;

class Shopee extends PublishAbstract
{
    /**
     * 刊登平台.
     *
     * @var string
     */
    protected $platform = 'shopee';

    /**
     * @var string
     */
    protected $url = 'http://shopee.api.nantang-tech.com/listing/sold_out_sku/notify';
}
