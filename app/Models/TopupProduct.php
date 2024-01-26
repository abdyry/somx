<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopupProduct extends Model
{
    public function operator()
    {
        return $this->belongsTo(TopupOperator::class, 'operator_id');
    }
}
