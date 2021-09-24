<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LazadaBanOn extends Model
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
    protected $connection = 'lazada';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lazada_ban_on';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function lazadaBanOnLists()
    {
        return $this->hasMany(LazadaBanOnList::class, 'banon_id', 'id');
    }
}
