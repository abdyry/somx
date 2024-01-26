<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', '=', 1);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeAgentAndAll($query): mixed
    {
        return $query->where('receiver', 'agents')->orWhere('receiver', 'all');
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeCustomerAndAll($query): mixed
    {
        return $query->where('receiver', 'customers')->orWhere('receiver', 'all');
    }
}
