<?php

namespace App\Helpers;

use App\Models\Sku;
use App\Models\SkuReview;
use Exception;
use Illuminate\Support\Facades\DB;

class SaveBaseInfo extends ReviewAbstract
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
                Sku::updateBaseInfo(
                    $review->sku,
                    json_decode($review->changes, true),
                    $review->submitter_id,
                    $review->submitter_name
                );
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
