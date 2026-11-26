<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class bank extends Model
{
    //Nama Table
    protected $table = 'bank';
    public $timestamps = false; // <--- Add this line

    protected $fillable = [
        'id',
        'nama_bank',
        'nomor_bank',
    ];
}
