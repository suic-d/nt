<?php

namespace App\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

trait ClientTrait
{
    /**
     * @var string
     */
    protected $url;

    /**
     * @var int
     */
    protected $timeout = 30;

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @return ClientInterface
     */
    public function getClient()
    {
        if (!$this->client) {
            $this->client = $this->createDefaultClient();
        }

        return $this->client;
    }

    /**
     * @return ClientInterface
     */
    protected function createDefaultClient()
    {
        return new Client(['base_uri' => $this->url, 'verify' => false, 'timeout' => $this->timeout]);
    }
}
