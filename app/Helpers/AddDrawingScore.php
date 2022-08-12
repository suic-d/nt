<?php

namespace App\Helpers;

use App\Models\Product\SkuLog;
use App\Models\Sku;
use App\Models\SkuReview;
use Exception;
use Illuminate\Support\Facades\DB;

class AddDrawingScore extends ReviewAbstract
{
    /**
     * @param SkuReview $review
     *
     * @throws \Throwable
     */
    public function handle(SkuReview $review)
    {
        $instance = new DingApproval();
        if ($instance->getProcessInstance($review->process_instance_id)) {
            DB::beginTransaction();

            try {
                $review->process_status = $instance->getProcessStatus();
                $review->save();

                $this->reviewLog($review, $instance->getOperationRecords());

                if ($instance->isAgree()) {
                    $this->updateDrawingScore($review);
                }

                DB::commit();
            } catch (Exception $exception) {
                DB::rollBack();
            }

            if ($instance->isAgree()) {
                $this->pushAgreedMessage($review);
            } elseif ($instance->isRefuse()) {
                $this->pushRefusedMessage($review);
            }
        }
    }

    /**
     * @param SkuReview $review
     * @param object    $record
     */
    protected function devReview(SkuReview $review, $record)
    {
        if (3 == $review->status) {
            $review->dev_review_time = date('Y-m-d H:i:s', strtotime($record->date));
            $review->save();

            if (self::agreed($record->operation_result)) {
                $this->devPass($review);
            } elseif (self::refused($record->operation_result)) {
                $this->devReject($review, $record->remark ?? '');
            }
        }
    }

    /**
     * @param SkuReview $review
     * @param string    $reason
     */
    protected function devReject(SkuReview $review, $reason)
    {
        $review->status = 4;
        $review->dev_reject_reason = $reason;
        $review->save();

        $this->devRejectLog($review);
    }

    /**
     * @param SkuReview $review
     */
    protected function devPass(SkuReview $review)
    {
        $review->status = 5;
        $review->save();

        $this->devPassLog($review);
    }

    /**
     * @param SkuReview $review
     * @param array     $operationRecords
     */
    protected function reviewLog(SkuReview $review, $operationRecords)
    {
        if (!empty($operationRecords)) {
            foreach ($operationRecords as $item) {
                if (!self::executeTaskNormal($item->operation_type)) {
                    continue;
                }

                if ($review->op_reviewer_id == $item->userid) {
                    $this->opReview($review, $item);
                } elseif ($review->dev_reviewer_id == $item->userid) {
                    $this->devReview($review, $item);
                } elseif ($review->design_reviewer_id == $item->userid) {
                    $this->designReview($review, $item);
                }
            }
        }
    }

    /**
     * @param SkuReview $review
     */
    protected function pushAgreedMessage(SkuReview $review)
    {
        $message = sprintf(
            '%s 你好，你在 %s 提交的积分申请已审核通过，请查收。',
            $review->submitter_name,
            $review->create_time
        );
        (new DingTalk())->push('积分申请已审核通过', $message, $review->submitter_id);
    }

    /**
     * @param SkuReview $review
     */
    protected function pushRefusedMessage(SkuReview $review)
    {
        $message = sprintf('%s 你好，你在 %s 提交的积分申请被驳回。', $review->submitter_name, $review->create_time);
        (new DingTalk())->push('积分申请被驳回', $message, $review->submitter_id);
    }

    /**
     * @param SkuReview $review
     */
    private function updateDrawingScore(SkuReview $review)
    {
        $sku = Sku::find($review->sku);
        if (!is_null($sku)) {
            $sku->drawing_score += $review->score;
            $sku->save();

            $this->skuLog($review, $sku);
        }
    }

    /**
     * @param SkuReview $review
     * @param Sku       $sku
     */
    private function skuLog(SkuReview $review, $sku)
    {
        $log = new SkuLog();
        $log->sku = $sku->sku;
        $log->log_type_id = 40;
        $log->remark = '增加积分：'.$review->score;
        $log->create_at = date('Y-m-d H:i:s');
        $log->create_id = $review->submitter_id;
        $log->create_name = $review->submitter_name;
        $log->save();
    }
}
