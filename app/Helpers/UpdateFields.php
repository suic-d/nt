<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\DB;

class UpdateFields extends ReviewAbstract
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
}
