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
            'family_id' => 'required|integer'
        ]);
        // 运行分析
        $this->familyService->analysisRerun($request);
        return response()->json([
            'code' => 0,
            'msg' => '分析重运行成功！'
        ]);
    }

    /**
     * 获取TSV数据
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTsvData(Request $request, $id)
    {
        try {
            $result = $this->familyService->getTsvData($id, $request);
            // 获取tsv数据
            return response()->json([
                'code' => 0,
                'msg' => '',
                'count' => $result['count'] ?? 0,
                'data' => $result['data'] ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'msg' => '无数据'.$e->getMessage(),
                'count' => 0,
                'data' => []
            ]);
        }
    }

    /**
     * 获取家系图片数据
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPicData(Request $request, $id)
    {
        try {
            $result = $this->familyService->getPicData($id, $request);
            // 获取tsv数据
            return response()->json([
                'code' => 0,
                'msg' => '',
                'data' => $result ?? ''
            ]);
        }  catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'msg' => '未取到数据',
                'data' => ''
            ]);
        }
    }

    /**
     * 搜索数据
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchData(Request $request, $id)
    {
        $request->validate([
            'child_sample' => 'required|string',
            'father_sample' => 'required|string',
            'slider_r' => 'required',
            'slider_s' => 'required',
        ]);
        
        try {
            $data = $this->familyService->searchData($id, $request);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'msg' => $e->getMessage()
            ]);
        }
       
        return response()->json([
            'code' => 0,
            'msg' => '',
            'data' => $data
        ]);
    }

    /**
     * 下载表格
     */
    public function downloadTable(Request $request)
    { 
        $filePath = $this->familyService->downloadTable($request);

        $file_name = str_replace(dirname($filePath) . DIRECTORY_SEPARATOR, '', $filePath);
        
        return response()->download($filePath, $file_name);
    }

    /**
     * 父本排查
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fatherSearch(Request $request, $id)
    {
         $request->validate([
            'father_num' => 'required|integer',
            'child_sample' => 'required|string',
            'father_sample' => 'required|string',
            // 'slider_r' => 'required',
            // 'slider_s' => 'required',
        ]);
        
        try {
            $data = $this->familyService->fatherSearchData($id, $request);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'msg' => $e->getMessage()
            ]);
        }
       
        return response()->json([
            'code' => 0,
            'msg' => '',
            'data' => $data
        ]);
    }

    
    /**
     * 父本排查表格
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fatherSearchTable(Request $request)
    {
        try {
            $result = $this->familyService->fatherSearchTable($request);
            // 父本排查表格
            return response()->json([
                'code' => 0,
                'msg' => '',
                'count' => $result['count'] ?? 0,
                'data' => $result['data'] ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'msg' => '无数据'.$e->getMessage(),
                'count' => 0,
                'data' => []
            ]);
        }
    }
    
    /**
     * 获取TXT数据
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTxtData(Request $request, $id)
    {
        try {
            $result = $this->familyService->getTxtData($id, $request);
            // 获取tsv数据
            return response()->json([
                'code' => 0,
                'msg' => '',
                'count' => $result['count'] ?? 0,
                'data' => $result['data'] ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'msg' => '无数据'.$e->getMessage(),
                'count' => 0,
                'data' => []
            ]);
        }
    }
    /**
     * 同一认定
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unityRun(Request $request, $id)
    {
        $request->validate([
            'sampleAId' => 'required|integer',
            'sampleIds' => 'required|string',
        ]);
        
        try {
            $data = $this->familyService->unityRun($request);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 1,
                'msg' => $e->getMessage()
            ]);
        }
       
        return response()->json([
            'code' => 0,
            'msg' => '',
            'data' => $data
        ]);
    }

    /**
     * 同一认定表格
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unityTable(Request $request, $id)
    {
        $request->validate([
            'sampleAId' => 'required|integer',
        ]);
        $data = $this->familyService->unityTable($request);
        return response()->json([
            'code' => 0,
            'msg' => '',
            'data' => $data
        ]);
    }
}