<?php

namespace App\Traits;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

trait LoggerTrait
{
    /**
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @return LoggerInterface
     */
    protected function createDefaultLogger()
    {
        if (!$this->logger) {
            $name = class_basename(__CLASS__);
            $path = storage_path('logs').DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR.$name.'.log';
            $handler = new StreamHandler($path, Logger::INFO);
            $handler->setFormatter(new LineFormatter(null, $this->dateFormat, true, true));

            $this->logger = new Logger($name);
            $this->logger->pushHandler($handler);
        }

        return $this->logger;
    }
}
