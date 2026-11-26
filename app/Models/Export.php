<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    use HasFactory;
    
    protected $connection = 'mysql';

    protected $table = 'export';

    protected $primaryKey = 'id';

    //Hapus Time Stamp Updated_At dan Created_At
    public $timestamps = false;

    protected $fillable = [
        'id',
        'nama_file',
        'bank',
        'start_date',
        'end_date',
    ];
}
