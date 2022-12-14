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
    public function handle($review)
    {
        $instance = new DingApproval();
        if (!$instance->getProcessInstance($review->process_instance_id)) {
            return;
        }

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

    /**
     * @param SkuReview $review
     */
    protected function devPass($review)
    {
        $review->status = SkuReview::DEV_AGREE;
        $review->save();

        $this->devPassLog($review);
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
            $this->opReview($review, $records[0]);
        }
        if (isset($records[1])) {
            $this->devReview($review, $records[1]);
        }
        if (isset($records[2])) {
            $this->designReview($review, $records[2]);
        }
    }

    /**
     * @param SkuReview $review
     */
    protected function pushAgreedMessage($review)
    {
        $message = sprintf(
            '%s ??????????????? %s ???????????????????????????????????????????????????',
            $review->submitter_name,
            $review->create_time
        );
        (new DingTalk())->push('???????????????????????????', $message, $review->submitter_id);
    }

    /**
     * @param SkuReview $review
     */
    protected function pushRefusedMessage($review)
    {
        $message = sprintf('%s ??????????????? %s ?????????????????????????????????', $review->submitter_name, $review->create_time);
        (new DingTalk())->push('?????????????????????', $message, $review->submitter_id);
    }

    /**
     * @param SkuReview $review
     */
    private function updateDrawingScore($review)
    {
        $sku = Sku::find($review->sku);
        if (is_null($sku)) {
            return;
        }

        $sku->drawing_score += $review->score;
        $sku->save();
        $this->skuLog($review);
    }

    /**
     * @param SkuReview $review
     */
    private function skuLog($review)
    {
        $log = new SkuLog();
        $log->sku = $review->sku;
        $log->log_type_id = 40;
        $log->remark = '???????????????'.$review->score;
        $log->create_at = date('Y-m-d H:i:s');
        $log->create_id = $review->submitter_id;
        $log->create_name = $review->submitter_name;
        $log->save();
    }
}
