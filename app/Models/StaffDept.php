<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffDept extends Model
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
    protected $table = 'nt_staff_dept';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function deptList()
    {
        return $this->hasOne(DeptList::class, 'dept_id', 'department');
    }
}
