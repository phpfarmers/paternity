<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilySample extends Model
{
    use HasFactory;

    protected $table = 'families_samples';

    protected $fillable = [
        'family_id',
        'sample_id'
    ];
}