<?php

namespace App\Helpers;

use App\Models\SkuReview;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateFields extends ReviewAbstract
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
}
