<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class transaksi extends Model
{
    //Nama Table
    protected $table = 'transaksi';
    public $timestamps = false; // <--- Add this line

    protected $fillable = [
        'nama_transaksi',
        'batch',
        'date_create',
    ];
}
