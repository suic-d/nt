<?php

namespace App\Helpers;

use App\Models\Product\ReviewLog;
use App\Repositories\ProductPoolRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateBuyPrice extends ReviewAbstract
{
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

    /**
     * @param array $operationRecords
     */
    protected function reviewLog($operationRecords)
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

    protected function pushAgreedMessage()
    {
        $message = sprintf(
            '%s 你好，你在 %s 提交的修改采购价申请已审核通过，请查收。',
            $this->review->submitter_name,
            $this->review->create_time
        );
        (new DingTalk())->push('修改采购价申请已审核通过', $message, $this->review->submitter_id);
    }

    protected function pushRefusedMessage()
    {
        $message = sprintf('%s 你好，你在 %s 提交的修改采购价申请被驳回。', $this->review->submitter_name, $this->review->create_time);
        (new DingTalk())->push('修改采购价申请被驳回', $message, $this->review->submitter_id);
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
