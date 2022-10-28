<?php

namespace App\Helpers;

abstract class MiniGameAbstract
{
    /**
     * @var MiniGameClient
     */
    protected $miniGame;

    /**
     * @var string
     */
    protected $openId;

    abstract public function handle();

    /**
     * @return MiniGameClient
     */
    public function getMiniGame(): MiniGameClient
    {
        return $this->miniGame;
    }

    /**
     * @return string
     */
    public function getOpenId(): string
    {
        return $this->openId;
    }
}
