<?php

namespace App\Helpers;

use App\Models\Product\ReviewLog;
use App\Models\SkuReview;

abstract class ReviewAbstract
{
    /**
     * @var SkuReview
     */
    private $review;

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

    protected function devPassLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $this->review->dev_reviewer_id;
        $log->op_staff_name = $this->review->dev_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->dev_review_time));
        $log->save();
    }

    protected function devRejectLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '驳回';
        $log->op_staff_id = $this->review->dev_reviewer_id;
        $log->op_staff_name = $this->review->dev_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->dev_review_time));
        $log->save();
    }

    protected function opPassLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $this->review->op_reviewer_id;
        $log->op_staff_name = $this->review->op_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->op_review_time));
        $log->save();
    }

    protected function opRejectLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '驳回';
        $log->op_staff_id = $this->review->op_reviewer_id;
        $log->op_staff_name = $this->review->op_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->op_review_time));
        $log->save();
    }

    protected function designPassLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $this->review->design_reviewer_id;
        $log->op_staff_name = $this->review->design_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->design_review_time));
        $log->save();
    }

    protected function designRejectLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '驳回';
        $log->op_staff_id = $this->review->design_reviewer_id;
        $log->op_staff_name = $this->review->design_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->design_review_time));
        $log->save();
    }

    protected function devdPassLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $this->review->devd_id;
        $log->op_staff_name = $this->review->devd_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->devd_review_time));
        $log->save();
    }

    protected function devdRejectLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '驳回';
        $log->op_staff_id = $this->review->devd_id;
        $log->op_staff_name = $this->review->devd_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->devd_review_time));
        $log->save();
    }

    protected function oplPassLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $this->review->opl_id;
        $log->op_staff_name = $this->review->opl_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->opl_review_time));
        $log->save();
    }

    protected function oplRejectLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '驳回';
        $log->op_staff_id = $this->review->opl_id;
        $log->op_staff_name = $this->review->opl_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->opl_review_time));
        $log->save();
    }

    protected function opdPassLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $this->review->opd_id;
        $log->op_staff_name = $this->review->opd_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->opd_review_time));
        $log->save();
    }

    protected function opdRejectLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '驳回';
        $log->op_staff_id = $this->review->opd_id;
        $log->op_staff_name = $this->review->opd_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->opd_review_time));
        $log->save();
    }
}
