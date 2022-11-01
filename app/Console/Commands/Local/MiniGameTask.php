<?php

namespace App\Console\Commands\Local;

use App\Helpers\BurningPlain;
use App\Helpers\MiniGameAbstract;
use App\Helpers\WarSongGulch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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
     * @var int
     */
    protected $expiresAt = 60;

    /**
     * @var string
     */
    protected $store = 'redis';

    /**
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * @var array|MiniGameAbstract[]
     */
    protected $tasks = [];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();

        $this->cache = Cache::store($this->store);
        $this->tasks[] = new WarSongGulch();
        $this->tasks[] = new BurningPlain();
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
        if (!$this->cache->add($this->mutexName(), true, $this->expiresAt)) {
            return;
        }

        foreach ($this->tasks as $task) {
            $task->handle();
        }

        $this->cache->forget($this->mutexName());
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
    public function log($level, string $message, array $context = [])
    {
        $this->getLogger()->log($level, $message, $context);
        $this->getLogger()->close();
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
     * @return string
     */
    public function mutexName(): string
    {
        return 'framework'.DIRECTORY_SEPARATOR.'cache-'.sha1(__METHOD__);
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
