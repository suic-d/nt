<?php

namespace App\Helpers;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

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

    /**
     * @var LoggerInterface
     */
    protected $logger;

    abstract public function handle();

    /**
     * @return MiniGameClient
     */
    public function getMiniGame(): MiniGameClient
    {
        if (!$this->miniGame) {
            $this->miniGame = MiniGameClient::getInstance();
        }

        return $this->miniGame;
    }

    /**
     * @return string
     */
    public function getOpenId(): string
    {
        return $this->openId;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = $this->createDefaultLogger();
        }

        return $this->logger;
    }

    /**
     * @return LoggerInterface
     */
    protected function createDefaultLogger()
    {
        $logger = new Logger($name = class_basename(static::class));
        $path = storage_path('logs').DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR.$name.'.log';
        $logger->pushHandler(new StreamHandler($path, Logger::INFO));

        return $logger;
    }
}
