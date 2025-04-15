<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sample extends Model
{
    use HasFactory;

    // 样本类型-对应的数字标记
    const SAMPLE_TYPE_DEFAULT   = '0';
    const SAMPLE_TYPE_MOTHER    = '1';
    const SAMPLE_TYPE_CHILD     = '2';
    const SAMPLE_TYPE_FATHER    = '3';
    // 样本类型-对应的英文标记
    CONST SAMPLE_TYPE_MAP = [
        self::SAMPLE_TYPE_DEFAULT   => 'unknown',
        self::SAMPLE_TYPE_MOTHER    => 'mother',
        self::SAMPLE_TYPE_CHILD     => 'child',
        self::SAMPLE_TYPE_FATHER    => 'father'
    ];
    // 样本类型中文名称
    CONST SAMPLE_TYPE_MAP_NAMES = [
        self::SAMPLE_TYPE_DEFAULT   => '未知样本',
        self::SAMPLE_TYPE_MOTHER    => '母本名',
        self::SAMPLE_TYPE_CHILD     => '胎儿名',
        self::SAMPLE_TYPE_FATHER    => '父本名'
    ];

    // 样本检测结果-对应的数字标记
    // 未检测
    const CHECK_RESULT_UNKNOWN   = '0';
    // 检测中
    const CHECK_RESULT_CHECKING  = '1';
    // 检测成功
    const CHECK_RESULT_SUCCESS   = '2';
    // 检测失败
    const CHECK_RESULT_FAIL      = '3';
    // 样本检测结果-对应的中文名称
    CONST CHECK_RESULT_MAP_NAMES = [
        self::CHECK_RESULT_UNKNOWN  => '未检测',
        self::CHECK_RESULT_CHECKING => '检测中',
        self::CHECK_RESULT_SUCCESS  => '已检测',
        self::CHECK_RESULT_FAIL     => '检测失败'
    ];

    // 样本分析结果-对应的数字标记
    // 未分析
    const ANALYSIS_RESULT_UNKNOWN   = '0';
    // 分析中
    const ANALYSIS_RESULT_ANALYZING = '1';
    // 分析成功
    const ANALYSIS_RESULT_SUCCESS   = '2';
    // 分析失败
    const ANALYSIS_RESULT_FAIL      = '3';
    // 样本分析结果-对应的中文名称
    CONST ANALYSIS_RESULT_MAP_NAMES = [
        self::ANALYSIS_RESULT_UNKNOWN   => '未分析',
        self::ANALYSIS_RESULT_ANALYZING => '分析中',
        self::ANALYSIS_RESULT_SUCCESS   => '分析成功',
        self::ANALYSIS_RESULT_FAIL      => '分析失败'
    ];
    
    protected $fillable = [
        'batch_number',
        'sample_name',
        'family_name',
        'check_result',
        'analysis_result',
        'off_machine_time',
        'off_machine_data',
        'analysis_time',
        'report_time',
        'pregnancy_week',
        'analysis_process',
    ];

    public function families()
    {
        return $this->belongsToMany(Family::class, 'families_samples');
    }
}