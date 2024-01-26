<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopupPackage extends Model
{
    public function product()
    {
        return $this->belongsTo(TopupProduct::class, 'product_id');
    }
}
