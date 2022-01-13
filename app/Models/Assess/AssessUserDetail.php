<?php

namespace App\Models\Assess;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessUserDetail extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The connection name for the model.
     *
     * @var null|string
     */
    protected $connection = 'assess';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'assess_user_detail';
}
