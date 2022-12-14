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
        $name = class_basename(static::class);
        $path = storage_path('logs').DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR.$name.'.log';
        $handler = new StreamHandler($path, Logger::INFO);
        $handler->setFormatter(new LineFormatter(null, $this->dateFormat, true, true));

        $logger = new Logger($name);
        $logger->pushHandler($handler);

        return $logger;
    }
}
