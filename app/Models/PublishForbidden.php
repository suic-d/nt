<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PublishForbidden extends Model
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
    protected $table = 'nt_publish_forbidden';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function publishForbiddenReasons()
    {
        return $this->hasMany(PublishForbiddenReason::class, 'forbidden_id', 'id');
    }
}
