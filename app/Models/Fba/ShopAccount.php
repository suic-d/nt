<?php

namespace App\Models\Fba;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopAccount extends Model
{
    use HasFactory;

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
    protected $table = 'shop_account';

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'fba';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'shop_id';
}
