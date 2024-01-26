<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralLegder extends Model
{
    protected $table = 'general_ledger';
    
    public function coa()
    {
        return $this->belongsTo(COA::class, 'coa_id');
    }
    
    public function journal()
    {
        return $this->belongsTo(Journal::class, 'reference', 'reference');
    }

}
