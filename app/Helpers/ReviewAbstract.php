<?php

namespace App\Helpers;

abstract class ReviewAbstract
{
    abstract public function run();

    /**
     * @param string $opType
     *
     * @return bool
     */
    public static function executeTaskNormal($opType)
    {
        return 'EXECUTE_TASK_NORMAL' == strtoupper($opType);
    }

    /**
     * @param string $opResult
     *
     * @return bool
     */
    public static function agreed($opResult)
    {
        return 'AGREE' == strtoupper($opResult);
    }

    /**
     * @param string $opResult
     *
     * @return bool
     */
    public static function refused($opResult)
    {
        return 'REFUSE' == strtoupper($opResult);
    }
}
