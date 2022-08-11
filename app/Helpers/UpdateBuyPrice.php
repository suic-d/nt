<?php

namespace App\Helpers;

use App\Models\Product\ReviewLog;
use App\Models\SkuReview;
use App\Repositories\ProductPoolRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateBuyPrice extends ReviewAbstract
{
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
                $this->updateBuyPrice();
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
        $message = sprintf(
            '%s 你好，你在 %s 提交的修改采购价申请已审核通过，请查收。',
            $this->review->submitter_name,
            $this->review->create_time
        );
        (new DingTalk())->push('修改采购价申请已审核通过', $message, $this->review->submitter_id);
    }

    private function pushRefusedMessage()
    {
        $message = sprintf('%s 你好，你在 %s 提交的修改采购价申请被驳回。', $this->review->submitter_name, $this->review->create_time);
        (new DingTalk())->push('修改采购价申请被驳回', $message, $this->review->submitter_id);
    }

    /**
     * @param object $record
     */
    private function devdReview($record)
    {
        if (8 != $this->review->status) {
            return;
        }

        $this->review->devd_review_time = date('Y-m-d H:i:s', strtotime($record->date));
        if (self::agreed($record->operation_result)) {
            $this->review->status = 10;
            $this->review->save();

            $this->devdPassLog();
        } elseif (self::refused($record->operation_result)) {
            $this->review->status = 9;
            $this->review->devd_reject_reason = $record->remark ?? '';
            $this->review->save();

            $this->devdRejectLog();
        }
    }

    /**
     * @param object $record
     */
    private function oplReview($record)
    {
        if (10 != $this->review->status) {
            return;
        }

        $this->review->opl_review_time = date('Y-m-d H:i:s', strtotime($record->date));
        if (self::agreed($record->operation_result)) {
            $this->review->status = 12;
            $this->review->save();

            $this->oplPassLog();
        } elseif (self::refused($record->operation_result)) {
            $this->review->status = 11;
            $this->review->opl_reject_reason = $record->remark ?? '';
            $this->review->save();

            $this->oplRejectLog();
        }
    }

    /**
     * @param object $record
     */
    private function opdReview($record)
    {
        if (12 != $this->review->status) {
            return;
        }

        $this->review->opd_review_time = date('Y-m-d H:i:s', strtotime($record->date));
        if (self::agreed($record->operation_result)) {
            $this->review->status = 14;
            $this->review->save();

            $this->opdPassLog();
        } elseif (self::refused($record->operation_result)) {
            $this->review->status = 13;
            $this->review->opd_reject_reason = $record->remark ?? '';
            $this->review->save();

            $this->opdRejectLog();
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

            if ($this->review->devd_id == $item->userid) {
                $this->devdReview($item);
            } elseif ($this->review->opl_id == $item->userid) {
                $this->oplReview($item);
            } elseif ($this->review->opd_id == $item->userid) {
                $this->opdReview($item);
            }
        }
    }

    private function updateBuyPrice()
    {
        $changes = json_decode($this->review->changes, true);
        if (isset($changes['buy_price'])) {
            ProductPoolRepository::syncBuyPrice(
                $this->review->sku,
                $changes['buy_price'],
                1,
                $this->review->submitter_id,
                $this->review->submitter_name
            );
        }
        if (isset($changes['tax_price'])) {
            ProductPoolRepository::syncBuyPrice(
                $this->review->sku,
                $changes['tax_price'],
                2,
                $this->review->submitter_id,
                $this->review->submitter_name
            );
        }
        if (isset($changes['usd_price'])) {
            ProductPoolRepository::syncBuyPrice(
                $this->review->sku,
                $changes['usd_price'],
                3,
                $this->review->submitter_id,
                $this->review->submitter_name
            );
        }
    }
}
