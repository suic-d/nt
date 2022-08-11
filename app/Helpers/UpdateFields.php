<?php

namespace App\Helpers;

use App\Models\Product\ReviewLog;
use App\Models\SkuReview;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateFields extends ReviewAbstract
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
                try {
                    $file = tempnam(storage_path(), '');
                    file_put_contents($file, file_get_contents($this->review->annex));
                    UploadExcel::updateFields($file, $this->review->submitter_id, $this->review->submitter_name);
                    @unlink($file);
                } catch (Exception $exception) {
                }
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
}
