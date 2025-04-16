<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    use HasFactory;
    
    // 家系分析结果
    const REPORT_RESULT_UNKNOWN   = '0';
    const REPORT_RESULT_ANALYZING = '1';
    const REPORT_RESULT_SUCCESS   = '2';
    const REPORT_RESULT_FAIL      = '3';
    // 家系分析结果-对应的中文名称
    const REPORT_RESULT_MAP_NAMES = [
        self::REPORT_RESULT_UNKNOWN   => '未分析',
        self::REPORT_RESULT_ANALYZING => '分析中',
        self::REPORT_RESULT_SUCCESS   => '已分析',
        self::REPORT_RESULT_FAIL      => '分析失败'
    ];

    protected $fillable = [
        'name',
        'report_time',
        'report_result',
        'report_times',
    ];

    public function samples()
    {
        return $this->belongsToMany(Sample::class, 'families_samples');
    }
}