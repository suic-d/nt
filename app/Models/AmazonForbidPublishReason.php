<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmazonForbidPublishReason extends Model
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
    protected $connection = 'amazon';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'amazon_spu_forbid_publish_reason';
}
