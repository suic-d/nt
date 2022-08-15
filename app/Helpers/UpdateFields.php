<?php

namespace App\Helpers;

use App\Models\SkuReview;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateFields extends ReviewAbstract
{
    /**
     * @param SkuReview $review
     *
     * @throws \Throwable
     */
    public function handle(SkuReview $review)
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
                $this->import($review);
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
    protected function import(SkuReview $review)
    {
        try {
            $file = tempnam(storage_path(), '');
            file_put_contents($file, file_get_contents($review->annex));
            UploadExcel::updateFields($file, $review->submitter_id, $review->submitter_name);
            @unlink($file);
        } catch (Exception $exception) {
        }
    }
}
