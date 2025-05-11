<?php

namespace App\Http\Controllers;

use App\Services\SampleService;
use Illuminate\Http\Request;

class SampleController extends Controller
{
    private $sampleService;
    public function __construct(SampleService $sampleService)
    {
        $this->sampleService = $sampleService;
    }

    /**
     * 导入样本数据
     *
     * @param Request $request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xls,xlsx|max:10240' // 限制文件大小为10MB
        ]);

        $file = $request->file('file');
        if (!$file->isValid()) {
            return response()->json([
                'code' => 1,
                'message' => '文件上传失败'
            ]);
        }

        $this->sampleService->import($request);

        return response()->json([
            'code' => 0,
            'message' => '样本数据导入成功'
        ]);
    }

    /**
     * 样本检测运行
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkRun(Request $request)
    {
        $request->validate([
            'sample_id' => 'required|integer'
        ]);
     
        // 运行检测
        $this->sampleService->checkRun($request);
        return response()->json([
            'code' => 0,
            'message' => '检测成功！'
        ]);
    }

    /**
     * 样本检测重运行
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkRerun(Request $request)
    {
        $request->validate([
            'sample_id' => 'required|integer'
        ]);
        // 运行检测
        $this->sampleService->checkRerun($request);
        return response()->json([
            'code' => 0,
            'message' => '检测重运行成功！'
        ]);
    }

    /**
     * 样本分析运行
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analysisRun(Request $request)
    {
        $request->validate([
            'sample_id' => 'required|integer'
        ]);
        // 运行分析
        $this->sampleService->analysisRun($request);
        return response()->json([
            'code' => 0,
            'message' => '分析成功！'
        ]);
    }

    /**
     * 样本分析重运行
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analysisRerun(Request $request)
    {
        $request->validate([
            'sample_id' => 'required|integer'
        ]);
        // 运行分析
        $this->sampleService->analysisRerun($request);
        return response()->json([
            'code' => 0,
            'message' => '分析重运行成功！'
        ]);
    }
}