<?php

namespace App\Helpers;

use App\Models\Product\SkuLog;
use App\Models\Sku;
use Exception;
use Illuminate\Support\Facades\DB;

class AddDrawingScore extends ReviewAbstract
{
    public function handle()
    {
        $instance = new DingApproval();
        if ($instance->getProcessInstance($this->review->process_instance_id)) {
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
    }

    /**
     * @param object $record
     */
    protected function devReview($record)
    {
        if (3 == $this->review->status) {
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
    }

    /**
     * @param array $operationRecords
     */
    protected function reviewLog($operationRecords)
    {
        if (!empty($operationRecords)) {
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

    protected function pushAgreedMessage()
    {
        $message = sprintf(
            '%s 你好，你在 %s 提交的积分申请已审核通过，请查收。',
            $this->review->submitter_name,
            $this->review->create_time
        );
        (new DingTalk())->push('积分申请已审核通过', $message, $this->review->submitter_id);
    }

    protected function pushRefusedMessage()
    {
        $message = sprintf('%s 你好，你在 %s 提交的积分申请被驳回。', $this->review->submitter_name, $this->review->create_time);
        (new DingTalk())->push('积分申请被驳回', $message, $this->review->submitter_id);
    }

    private function updateDrawingScore()
    {
        $sku = Sku::find($this->review->sku);
        if (!is_null($sku)) {
            $sku->drawing_score += $this->review->score;
            $sku->save();

            $this->skuLog($sku);
        }
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
}
