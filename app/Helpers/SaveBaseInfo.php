<?php

namespace App\Helpers;

use App\Models\Product\ReviewLog;
use App\Models\Sku;
use App\Models\SkuReview;
use Exception;
use Illuminate\Support\Facades\DB;

class SaveBaseInfo extends ReviewAbstract
{
    /**
     * @var SkuReview
     */
    private $review;

    /**
     * @param SkuReview $review
     */
    public function __construct($review)
    {
        $this->review = $review;
    }

    public function run()
    {
        $instance = new DingApproval();
        if (!$instance->getProcessInstance($this->review->process_instance_id)) {
            return;
        }

        DB::beginTransaction();

        try {
            $this->review->process_status = $instance->getProcessStatus();
            $this->review->save();

            $this->reviewLog($instance->getOperationRecords());

            if ($instance->isAgree()) {
                Sku::updateBaseInfo(
                    $this->review->sku,
                    json_decode($this->review->changes, true),
                    $this->review->submitter_id,
                    $this->review->submitter_name
                );
            }

            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
        }

        if ($instance->isAgree()) {
            $this->pushAgreedMessage();
        } elseif ($instance->isRefuse()) {
            $this->pushRefusedMessage();
        }
    }

    private function pushAgreedMessage()
    {
        $message = sprintf('提交人你好，你在 %s 提交的sku信息修改审核已通过，请登录系统查看。', $this->review->create_time);
        (new DingTalk())->push('sku信息修改审核已通过', $message, $this->review->submitter_id);
    }

    private function pushRefusedMessage()
    {
        $message = sprintf('提交人你好，你在 %s 提交的sku信息修改审核被驳回。', $this->review->create_time);
        (new DingTalk())->push('sku信息修改审核被驳回', $message, $this->review->submitter_id);
    }

    /**
     * @param array $operationRecords
     */
    private function reviewLog($operationRecords)
    {
        if (empty($operationRecords)) {
            return;
        }

        foreach ($operationRecords as $item) {
            if (!self::executeTaskNormal($item->operation_type)) {
                continue;
            }

            if ($this->review->dev_reviewer_id == $item->userid) {
                $this->devReview($item);
            }
        }
    }

    /**
     * @param object $record
     */
    private function devReview($record)
    {
        if (3 != $this->review->status) {
            return;
        }

        $this->review->dev_review_time = date('Y-m-d H:i:s', strtotime($record->date));
        if (self::agreed($record->operation_result)) {
            $this->review->status = 7;
            $this->review->save();

            $this->devPassLog();
        } elseif (self::refused($record->operation_result)) {
            $this->review->status = 4;
            $this->review->dev_reject_reason = $record->remark ?? '';
            $this->review->save();

            $this->devRejectLog();
        }
    }

    private function devPassLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '审核通过';
        $log->op_staff_id = $this->review->dev_reviewer_id;
        $log->op_staff_name = $this->review->dev_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->dev_review_time));
        $log->save();
    }

    private function devRejectLog()
    {
        $log = new ReviewLog();
        $log->review_id = $this->review->id;
        $log->action = '驳回';
        $log->op_staff_id = $this->review->dev_reviewer_id;
        $log->op_staff_name = $this->review->dev_reviewer_name;
        $log->op_time = date('Y-m-d H:i:s', strtotime($this->review->dev_review_time));
        $log->save();
    }
}
