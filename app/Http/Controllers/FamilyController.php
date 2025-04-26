<?php

namespace App\Http\Controllers;

use App\Services\FamilyService;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    protected $familyService;
    public function __construct(FamilyService $familyService)
    {
        $this->familyService = $familyService;
    }

    /**
     * 显示家庭列表
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        $data = $this->familyService->index($request);
        return view('family.index', $data);
    }

    /**
     * 获取家庭表格数据
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function tableData(Request $request)
    {
        $data = $this->familyService->getTableData($request);
        return response()->json([
            'code' => 0,
            'msg' => '',
            'count' => $data['total'],
            'data' => $data['data']
        ]);
    }

    /**
     * 显示家庭详情
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse
     */
    public function detail(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);
        
        $family = $this->familyService->getFamily($request);
        if (!$family) {
            return redirect()->route('family.index')->with('error', '家庭信息不存在！');
        }
        return view('family.detail', compact('family'));
    }

    /**
     * 家系报告分析运行
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analysisRun(Request $request)
    {
        $request->validate([
            'family_id' => 'required|integer'
        ]);
        // 运行分析
        $this->familyService->analysisRun($request);
        return response()->json([
            'code' => 0,
            'msg' => '分析成功！'
        ]);
    }

    /**
     * 家系报告分析重运行
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function analysisRerun(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);
        // 运行分析
        $this->familyService->analysisRerun($request);
        return response()->json([
            'code' => 0,
            'msg' => '分析重运行成功！'
        ]);
    }
}