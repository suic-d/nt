<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var null|string
     */
    protected $connection = 'asset';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'companies';
}
