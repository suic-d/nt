<?php

namespace App\Models\BuyPlan;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetail extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var null|string
     */
    protected $connection = 'buyplan';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'nt_purchase_order_detail';
}
