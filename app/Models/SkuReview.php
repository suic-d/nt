<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkuReview extends Model
{
    use HasFactory;

    const OP_RUNNING = 1;

    const OP_REFUSE = 2;

    const OP_AGREE = 3;

    const DEV_REFUSE = 4;

    const DEV_AGREE = 5;

    const DESIGN_REFUSE = 6;

    const DESIGN_AGREE = 7;

    const DEVD_RUNNING = 8;

    const DEVD_REFUSE = 9;

    const DEVD_AGREE = 10;

    const OPL_REFUSE = 11;

    const OPL_AGREE = 12;

    const OPD_REFUSE = 13;

    const OPD_AGREE = 14;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nt_sku_review';
}
