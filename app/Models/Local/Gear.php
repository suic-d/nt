<?php

namespace App\Models\Local;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gear extends Model
{
    use HasFactory;
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var null|string
     */
    protected $connection = 'local';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'gear';
}
