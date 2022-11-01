<?php

namespace App\Console\Commands\Local;

use App\Helpers\BurningPlain;
use App\Helpers\WarSongGulch;
use Exception;
use Illuminate\Console\Command;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class MiniGameTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mg:task
                            {--sleep=5 : Number of seconds to sleep when no job is available}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'minigame worker';

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        while (true) {
            $this->doRun();

            $this->wait($this->option('sleep'));
        }
    }

    public function doRun()
    {
        try {
            (new WarSongGulch())->handle();
        } catch (Exception $exception) {
            $this->log(Logger::ERROR, $exception->getMessage());
        }

        try {
            (new BurningPlain())->handle();
        } catch (Exception $exception) {
            $this->log(Logger::ERROR, $exception->getMessage());
        }

        $this->log(Logger::INFO, __METHOD__.' Task Executed');
    }

    /**
     * @param float|int $seconds
     */
    public function wait($seconds)
    {
        if ($seconds < 1) {
            usleep($seconds * 1000000);
        } else {
            sleep($seconds);
        }
    }

    /**
     * @param int|string $level
     * @param string     $message
     * @param array      $context
     */
    protected function log($level, string $message, array $context = [])
    {
        $this->getLogger()->log($level, $message, $context);
        $this->getLogger()->close();
    }

    /**
     * @return LoggerInterface
     */
    protected function getLogger()
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
        $logger = new Logger($name = class_basename(__CLASS__));
        $path = storage_path('logs').DIRECTORY_SEPARATOR.date('Ymd').DIRECTORY_SEPARATOR.$name.'.log';
        $logger->pushHandler(new StreamHandler($path, Logger::INFO));

        return $logger;
    }
}
