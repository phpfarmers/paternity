<?php

namespace App\Console\Commands;

use App\Jobs\SampleAnalysisRunJob;
use Illuminate\Console\Command;
use App\Models\Sample;
use Illuminate\Support\Facades\Log;

class SampleAnalysisRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sample:analysis:run {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动分析样本';

    /**
     * 超时时间
     * 默认1小时
     **/
    protected $timeout = 60 * 60; // 1小时

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 设定开始位置标识
        start:
        $id = $this->argument('id') ?? 0;
        $this->info('开始分析样本');
        Log::info('开始分析样本');
        $samples = Sample::where('check_result', Sample::CHECK_RESULT_SUCCESS);
        // 指定id-前端操作-只要未完成的都可操作
        if ($id > 0) {
            $samples = $samples->where('id', $id)->where('analysis_result', '!=', Sample::ANALYSIS_RESULT_UNKNOWN);
        } else {
            $samples = $samples->where('analysis_result', Sample::ANALYSIS_RESULT_UNKNOWN);
        }
        $samples = $samples->orderBy('analysis_times', 'asc')->orderBy('id', 'asc')->limit(10)->get();
        if ($samples->isEmpty()) {
            $this->info('没有要分析的样本');
            Log::info('没有要分析的样本');
            return 0;
        }
        // 如果正在分析中的样本数量大50，停止1分钟，再从start位置开始执行
        $analyzingCount = Sample::where('analysis_result', Sample::ANALYSIS_RESULT_ANALYZING)->count();
        if ($analyzingCount > 100) {
            $this->info('正在分析中的样本数量大于100，停止1分钟');
            Log::info('正在分析中的样本数量大于100，停止1分钟');
            sleep(60);
            goto start;
        }
        try {
            foreach ($samples as $sample) {
                $this->info('样本分析开始：'.$sample->id).'-'.date('Y-m-d H:i:s');
                Log::info('样本分析开始：'.$sample->id).'-'.date('Y-m-d H:i:s');
                // 次数大于3000次，标记为失败
                if ($sample->analysis_times > 3000) {
                    $sample->analysis_result = Sample::ANALYSIS_RESULT_FAIL;
                    $sample->save();
                    continue;
                }
                
                // 样本分析变为分析中
                $sample->analysis_result = Sample::ANALYSIS_RESULT_ANALYZING;
                $sample->analysis_times += 1;
                $sample->output_dir = generateObjectOutputDir($sample->sample_name);
                $sample->save();
                dispatch(new SampleAnalysisRunJob($sample->id))->onQueue('sample_analysis_run')->delay(now()->addSeconds(5));
                $this->info('样本分析完成-'.date('Y-m-d H:i:s'));
                Log::info('样本分析完成-'.date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            $this->info('样本分析出错：'.$e->getMessage());
            Log::info('样本分析出错：'.$e->getMessage());
        }
        return 0;
    }
}