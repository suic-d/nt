<?php

namespace App\Helpers;

use App\Models\Product\ReviewLog;
use App\Models\Product\SkuLog;
use App\Models\Sku;
use App\Models\SkuReview;
use Exception;
use Illuminate\Support\Facades\DB;

class AddDrawingScore extends ReviewAbstract
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
                $this->updateDrawingScore();
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

    private function updateDrawingScore()
    {
        $sku = Sku::find($this->review->sku);
        if (is_null($sku)) {
            return;
        }

        $sku->drawing_score += $this->review->score;
        $sku->save();

        $this->skuLog($sku);
    }

    /**
     * @param Sku $sku
     */
    private function skuLog($sku)
    {
        $log = new SkuLog();
        $log->sku = $sku->sku;
        $log->log_type_id = 40;
        $log->remark = '增加积分：'.$this->review->score;
        $log->create_at = date('Y-m-d H:i:s');
        $log->create_id = $this->review->submitter_id;
        $log->create_name = $this->review->submitter_name;
        $log->save();
    }

    private function pushAgreedMessage()
    {
        $message = sprintf(
            '%s 你好，你在 %s 提交的积分申请已审核通过，请查收。',
            $this->review->submitter_name,
            $this->review->create_time
        );
        (new DingTalk())->push('积分申请已审核通过', $message, $this->review->submitter_id);
    }

    private function pushRefusedMessage()
    {
        $message = sprintf('%s 你好，你在 %s 提交的积分申请被驳回。', $this->review->submitter_name, $this->review->create_time);
        (new DingTalk())->push('积分申请被驳回', $message, $this->review->submitter_id);
    }

    /**
     * @param object $record
     */
    private function opReview($record)
    {
        if (1 != $this->review->status) {
            return;
        }

        $this->review->op_review_time = date('Y-m-d H:i:s', strtotime($record->date));
        if (self::agreed($record->operation_result)) {
            $this->review->status = 3;
            $this->review->save();

            $this->opPassLog();
        } elseif (self::refused($record->operation_result)) {
            $this->review->status = 2;
            $this->review->op_reject_reason = $record->remark ?? '';
            $this->review->save();

            $this->opRejectLog();
        }
    }

    private function devReview($record)
    {
        if (3 != $this->review->status) {
            return;
        }

        $this->review->dev_review_time = date('Y-m-d H:i:s', strtotime($record->date));
        if (self::agreed($record->operation_result)) {
            $this->review->status = 5;
            $this->review->save();

            $this->devPassLog();
        } elseif (self::refused($record->operation_result)) {
            $this->review->status = 4;
            $this->review->dev_reject_reason = $record->remark ?? '';
            $this->review->save();

            $this->devRejectLog();
        }
    }

    private function designReview($record)
    {
        if (5 != $this->review->status) {
            return;
        }

        $this->review->design_review_time = date('Y-m-d H:i:s', strtotime($record->date));
        if (self::agreed($record->operation_result)) {
            $this->review->status = 7;
            $this->review->save();

            $this->designPassLog();
        } elseif (self::refused($record->operation_result)) {
            $this->review->status = 6;
            $this->review->design_reject_reason = $record->remark ?? '';
            $this->review->save();

            $this->designRejectLog();
        }
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

            if ($this->review->op_reviewer_id == $item->userid) {
                $this->opReview($item);
            } elseif ($this->review->dev_reviewer_id == $item->userid) {
                $this->devReview($item);
            } elseif ($this->review->design_reviewer_id == $item->userid) {
                $this->designReview($item);
            }
        }
    }
}
