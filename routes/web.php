<?php

use App\Http\Controllers\FamilyController;
use App\Http\Controllers\SampleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// 家系
Route::get('family/index', [FamilyController::class, 'index'])->name('family.index');
// 获取家系表格数据-ajax使用
Route::get('family/table-data', [FamilyController::class, 'tableData'])->name('family.table-data');
// 家系详情
Route::get('family/detail', [FamilyController::class, 'detail'])->name('family.detail');
// 家系报告分析运行
Route::get('family/analysis-run', [FamilyController::class, 'analysisRun'])->name('family.analysisRun');
// 家系报告分析重运行
Route::get('family/analysis-rerun', [FamilyController::class, 'analysisRerun'])->name('family.analysisRerun');

// 样本
Route::post('sample/import', [SampleController::class, 'import'])->name('sample.import');
// 样本检测运行
Route::get('sample/check-run', [SampleController::class, 'checkRun'])->name('sample.checkRun');
// 样本检测重运行
Route::get('sample/check-rerun', [SampleController::class, 'checkRerun'])->name('sample.checkRerun');
// 样本分析运行
Route::get('sample/analysis-run', [SampleController::class, 'analysisRun'])->name('sample.analysisRun');
// 样本分析重运行
Route::get('sample/analysis-rerun', [SampleController::class, 'analysisRerun'])->name('sample.analysisRerun');