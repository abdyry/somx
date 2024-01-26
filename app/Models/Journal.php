<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    protected $table = 'journal';
    
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver');
    }
    
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender');
    }

}
