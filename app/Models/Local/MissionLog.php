<?php

namespace App\Models\Local;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MissionLog extends Model
{
    use HasFactory;

    const NONE = 0;

    const PENDING = 1;

    const COMPLETED = 2;

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
    protected $table = 'mission_logs';

    protected static $unguarded = true;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function advertLogs()
    {
        return $this->hasMany(AdvertLog::class, 'raid_log_id', 'id');
    }
}
