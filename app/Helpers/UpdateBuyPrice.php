<?php

namespace App\Helpers;

use App\Models\SkuReview;
use App\Repositories\ProductPoolRepository;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateBuyPrice extends ReviewAbstract
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
                    $this->updateBuyPrice($review);
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
     * @param array     $operationRecords
     */
    protected function reviewLog(SkuReview $review, $operationRecords)
    {
        if (!empty($operationRecords)) {
            foreach ($operationRecords as $item) {
                if (!self::executeTaskNormal($item->operation_type)) {
                    continue;
                }

                if ($review->devd_id == $item->userid) {
                    $this->devdReview($review, $item);
                } elseif ($review->opl_id == $item->userid) {
                    $this->oplReview($review, $item);
                } elseif ($review->opd_id == $item->userid) {
                    $this->opdReview($review, $item);
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
            '%s 你好，你在 %s 提交的修改采购价申请已审核通过，请查收。',
            $review->submitter_name,
            $review->create_time
        );
        (new DingTalk())->push('修改采购价申请已审核通过', $message, $review->submitter_id);
    }

    /**
     * @param SkuReview $review
     */
    protected function pushRefusedMessage(SkuReview $review)
    {
        $message = sprintf('%s 你好，你在 %s 提交的修改采购价申请被驳回。', $review->submitter_name, $review->create_time);
        (new DingTalk())->push('修改采购价申请被驳回', $message, $review->submitter_id);
    }

    /**
     * @param SkuReview $review
     */
    private function updateBuyPrice(SkuReview $review)
    {
        $changes = json_decode($review->changes, true);
        if (isset($changes['buy_price'])) {
            ProductPoolRepository::syncBuyPrice(
                $review->sku,
                $changes['buy_price'],
                1,
                $review->submitter_id,
                $review->submitter_name
            );
        }
        if (isset($changes['tax_price'])) {
            ProductPoolRepository::syncBuyPrice(
                $review->sku,
                $changes['tax_price'],
                2,
                $review->submitter_id,
                $review->submitter_name
            );
        }
        if (isset($changes['usd_price'])) {
            ProductPoolRepository::syncBuyPrice(
                $review->sku,
                $changes['usd_price'],
                3,
                $review->submitter_id,
                $review->submitter_name
            );
        }
    }
}
