<?php

namespace App\Helpers;

use App\Models\Product\ReviewLog;
use App\Models\SkuReview;

abstract class ReviewAbstract
{
    const EXECUTE_TASK_NORMAL = 'EXECUTE_TASK_NORMAL';

    const EXECUTE_TASK_AGENT = 'EXECUTE_TASK_AGENT';

    const AGREE = 'AGREE';

    const REFUSE = 'REFUSE';

    /**
     * @param SkuReview $review
     */
    abstract public function handle($review);

    /**
     * @param string $opType
     *
     * @return bool
     */
    public static function executeTaskNormal($opType)
    {
        return self::EXECUTE_TASK_NORMAL === strtoupper($opType) || self::EXECUTE_TASK_AGENT === strtoupper($opType);
    }

    /**
     * @param string $opResult
     *
     * @return bool
     */
    public static function agreed($opResult)
    {
        return self::AGREE === strtoupper($opResult);
    }

    /**
     * @param string $opResult
     *
     * @return bool
     */
    public static function refused($opResult)
    {
        return self::REFUSE === strtoupper($opResult);
    }

    /**
     * @param SkuReview $review
     */
    protected function devPassLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $review->dev_reviewer_id;
        $log->op_staff_name = $review->dev_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->dev_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function devRejectLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '驳回';
        $log->op_staff_id = $review->dev_reviewer_id;
        $log->op_staff_name = $review->dev_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->dev_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function opPassLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $review->op_reviewer_id;
        $log->op_staff_name = $review->op_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->op_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function opRejectLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '驳回';
        $log->op_staff_id = $review->op_reviewer_id;
        $log->op_staff_name = $review->op_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->op_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function designPassLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $review->design_reviewer_id;
        $log->op_staff_name = $review->design_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->design_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function designRejectLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '驳回';
        $log->op_staff_id = $review->design_reviewer_id;
        $log->op_staff_name = $review->design_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->design_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function devdPassLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $review->devd_id;
        $log->op_staff_name = $review->devd_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->devd_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function devdRejectLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '驳回';
        $log->op_staff_id = $review->devd_id;
        $log->op_staff_name = $review->devd_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->devd_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function oplPassLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $review->opl_id;
        $log->op_staff_name = $review->opl_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->opl_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function oplRejectLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '驳回';
        $log->op_staff_id = $review->opl_id;
        $log->op_staff_name = $review->opl_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->opl_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function opdPassLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $review->opd_id;
        $log->op_staff_name = $review->opd_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->opd_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     */
    protected function opdRejectLog($review)
    {
        $log = new ReviewLog();
        $log->review_id = $review->id;
        $log->action = '驳回';
        $log->op_staff_id = $review->opd_id;
        $log->op_staff_name = $review->opd_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($review->opd_review_time));
        $log->save();
    }

    /**
     * @param SkuReview $review
     * @param object    $record
     */
    protected function devReview($review, $record)
    {
        if (SkuReview::OP_AGREE != $review->status) {
            return;
        }

        $review->dev_review_time = date('Y-m-d H:i:s', strtotime($record['date']));
        $review->save();

        if (self::agreed($record['operation_result'])) {
            $this->devPass($review);
        } elseif (self::refused($record['operation_result'])) {
            $this->devReject($review, $record['remark'] ?? '');
        }
    }

    /**
     * @param SkuReview $review
     * @param string    $reason
     */
    protected function devReject($review, $reason)
    {
        $review->status = SkuReview::DEV_REFUSE;
        $review->dev_reject_reason = $reason;
        $review->save();

        $this->devRejectLog($review);
    }

    /**
     * @param SkuReview $review
     */
    protected function devPass($review)
    {
        $review->status = SkuReview::DESIGN_AGREE;
        $review->save();

        $this->devPassLog($review);
    }

    /**
     * @param SkuReview $review
     * @param object    $record
     */
    protected function opReview($review, $record)
    {
        if (SkuReview::OP_RUNNING != $review->status) {
            return;
        }

        $review->op_review_time = date('Y-m-d H:i:s', strtotime($record['date']));
        $review->save();

        if (self::agreed($record['operation_result'])) {
            $this->opPass($review);
        } elseif (self::refused($record['operation_result'])) {
            $this->opReject($review, $record['remark'] ?? '');
        }
    }

    /**
     * @param SkuReview $review
     */
    protected function opPass($review)
    {
        $review->status = SkuReview::OP_AGREE;
        $review->save();

        $this->opPassLog($review);
    }

    /**
     * @param SkuReview $review
     * @param string    $reason
     */
    protected function opReject($review, $reason)
    {
        $review->status = SkuReview::OP_REFUSE;
        $review->op_reject_reason = $reason;
        $review->save();

        $this->opRejectLog($review);
    }

    /**
     * @param SkuReview $review
     * @param object    $record
     */
    protected function designReview($review, $record)
    {
        if (SkuReview::DEV_AGREE != $review->status) {
            return;
        }

        $review->design_review_time = date('Y-m-d H:i:s', strtotime($record['date']));
        $review->save();

        if (self::agreed($record['operation_result'])) {
            $this->designPass($review);
        } elseif (self::refused($record['operation_result'])) {
            $this->designReject($review, $record['remark'] ?? '');
        }
    }

    /**
     * @param SkuReview $review
     * @param string    $reason
     */
    protected function designReject($review, $reason)
    {
        $review->status = SkuReview::DESIGN_REFUSE;
        $review->design_reject_reason = $reason;
        $review->save();

        $this->designRejectLog($review);
    }

    /**
     * @param SkuReview $review
     */
    protected function designPass($review)
    {
        $review->status = SkuReview::DESIGN_AGREE;
        $review->save();

        $this->designPassLog($review);
    }

    /**
     * @param SkuReview $review
     * @param object    $record
     */
    protected function devdReview($review, $record)
    {
        if (SkuReview::DEVD_RUNNING != $review->status) {
            return;
        }

        $review->devd_review_time = date('Y-m-d H:i:s', strtotime($record['date']));
        $review->save();

        if (self::agreed($record['operation_result'])) {
            $this->devdPass($review);
        } elseif (self::refused($record['operation_result'])) {
            $this->devdReject($review, $record['remark'] ?? '');
        }
    }

    /**
     * @param SkuReview $review
     * @param string    $reason
     */
    protected function devdReject($review, $reason)
    {
        $review->status = SkuReview::DEVD_REFUSE;
        $review->devd_reject_reason = $reason;
        $review->save();

        $this->devdRejectLog($review);
    }

    /**
     * @param SkuReview $review
     */
    protected function devdPass($review)
    {
        $review->status = SkuReview::DEVD_AGREE;
        $review->save();

        $this->devdPassLog($review);
    }

    /**
     * @param SkuReview $review
     * @param object    $record
     */
    protected function oplReview($review, $record)
    {
        if (SkuReview::DEVD_AGREE != $review->status) {
            return;
        }

        $review->opl_review_time = date('Y-m-d H:i:s', strtotime($record['date']));
        $review->save();

        if (self::agreed($record['operation_result'])) {
            $this->oplPass($review);
        } elseif (self::refused($record['operation_result'])) {
            $this->oplReject($review, $record['remark'] ?? '');
        }
    }

    /**
     * @param SkuReview $review
     * @param string    $reason
     */
    protected function oplReject($review, $reason)
    {
        $review->status = SkuReview::OPL_REFUSE;
        $review->opl_reject_reason = $reason;
        $review->save();

        $this->oplRejectLog($review);
    }

    /**
     * @param SkuReview $review
     */
    protected function oplPass($review)
    {
        $review->status = SkuReview::OPL_AGREE;
        $review->save();

        $this->oplPassLog($review);
    }

    /**
     * @param SkuReview $review
     * @param object    $record
     */
    protected function opdReview($review, $record)
    {
        if (SkuReview::OPL_AGREE != $review->status) {
            return;
        }

        $review->opd_review_time = date('Y-m-d H:i:s', strtotime($record['date']));
        $review->save();

        if (self::agreed($record['operation_result'])) {
            $this->opdPass($review);
        } elseif (self::refused($record['operation_result'])) {
            $this->opdReject($review, $record['remark'] ?? '');
        }
    }

    /**
     * @param SkuReview $review
     * @param string    $reason
     */
    protected function opdReject($review, $reason)
    {
        $review->status = SkuReview::OPD_REFUSE;
        $review->opd_reject_reason = $reason;
        $review->save();

        $this->opdRejectLog($review);
    }

    /**
     * @param SkuReview $review
     */
    protected function opdPass($review)
    {
        $review->status = SkuReview::OPD_AGREE;
        $review->save();

        $this->opdPassLog($review);
    }

    /**
     * @param SkuReview $review
     * @param array     $operationRecords
     */
    protected function reviewLog($review, $operationRecords)
    {
        if (empty($operationRecords)) {
            return;
        }

        $records = [];
        foreach ($operationRecords as $item) {
            if (self::executeTaskNormal($item['operation_type'])) {
                $records[] = $item;
            }
        }

        if (isset($records[0])) {
            $this->devReview($review, $records[0]);
        }
    }

    /**
     * @param SkuReview $review
     */
    protected function pushAgreedMessage($review)
    {
        $message = sprintf('提交人你好，你在 %s 提交的sku信息修改审核已通过，请登录系统查看。', $review->create_time);
        (new DingTalk())->push('sku信息修改审核已通过', $message, $review->submitter_id);
    }

    /**
     * @param SkuReview $review
     */
    protected function pushRefusedMessage($review)
    {
        $message = sprintf('提交人你好，你在 %s 提交的sku信息修改审核被驳回。', $review->create_time);
        (new DingTalk())->push('sku信息修改审核被驳回', $message, $review->submitter_id);
    }
}
