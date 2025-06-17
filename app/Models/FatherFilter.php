<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FatherFilter extends Model
{
    use HasFactory;

    protected $fillable = [
        'family_id',
        'child_id',
        'father_id',
        'father_name',
    ];
    protected $table = 'father_filters';
    public $timestamps = false;
    protected $primaryKey = 'id';
}