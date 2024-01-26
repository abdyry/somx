<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class COA extends Model
{
    protected $table = 'chart_of_accounts';
    
    public function category()
    {
        return $this->belongsTo(COA::class, 'coa_id');
    }
    
    public function type()
    {
        return $this->belongsTo(Journal::class, 'reference', 'reference');
    }

}
