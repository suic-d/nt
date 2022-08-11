<?php

namespace App\Helpers;

use App\Models\Sku;
use Exception;
use Illuminate\Support\Facades\DB;

class SaveBaseInfo extends ReviewAbstract
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
                    Sku::updateBaseInfo(
                        $this->review->sku,
                        json_decode($this->review->changes, true),
                        $this->review->submitter_id,
                        $this->review->submitter_name
                    );
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
}
