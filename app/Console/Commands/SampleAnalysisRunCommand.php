<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sample;

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
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $id = $this->argument('id') ?? 0;
        
        $samples = Sample::where('check_result', Sample::CHECK_RESULT_SUCCESS)->where('analysis_result', Sample::ANALYSIS_RESULT_UNKNOWN);
        // 指定id-前端操作
        if ($id > 0) {
            $samples = $samples->where('id', $id);
        }
        $samples = $samples->orderBy('analysis_times', 'asc')->orderBy('id', 'asc')->limit(1)->get();
        if ($samples->isEmpty()) {
            $this->info('没有要分析的样本');
            return 0;
        }
        try {
            foreach ($samples as $sample) {
                $this->info('样本分析开始：'.$sample->id).'-'.date('Y-m-d H:i:s');
                // 样本分析变为分析中
                $sample->analysis_result = Sample::ANALYSIS_RESULT_ANALYZING;
                $sample->analysis_times += 1;
                $sample->save();
                
                // shell命令参数
                $sampleName = escapeshellarg($sample->sample_name);
                $r1Url = escapeshellarg($sample->r1_url);
                $r2Url = escapeshellarg($sample->r2_url);
                $analysisProcess = escapeshellarg($sample->analysis_process);
                $outputDir = escapeshellarg(''); // 输出路径
                $commandPl = config('data')['sample_analysis_run_command_pl'];
                // $command = $commandPl." -s {$sampleName} -r1 {$r1Url} -r2 {$r2Url} -u {$analysisProcess} -o {$outputDir} 2>&1";
                $command = $commandPl." -s {$sampleName} -r1 {$r1Url} -r2 {$r2Url} -u {$analysisProcess} 2>&1";
                $this->info('执行命令：'.$command);
                // 执行shell命令
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0) {
                    $this->info("找到以下文件:");
                    foreach ($output as $file) {
                        // $this->info($file);
                        // 获取文件名
                        $fileName = basename($file);
                        $this->info($fileName);
                    }
                    // 符合条件-更新检测结果状态为成功
                    // if(count($output) > 0){
                        $sample->analysis_result = Sample::ANALYSIS_RESULT_SUCCESS;
                        $sample->analysis_time = date('Y-m-d');
                        $sample->save();
                    // }else{
                    //     // 未下机-变为未检测-继续检测
                    //     $this->info("文件数量不正确");
                    //     $sample->analysis_result = Sample::ANALYSIS_RESULT_UNKNOWN;
                    //     $sample->save();
                    // }
                } else {
                    $this->error("未找到文件或命令执行失败");
                    // 不符合条件-更新检测结果状态为失败
                    $sample->analysis_result = Sample::CHECK_RESULT_FAIL;
                    $sample->save();
                }
                $this->info('样本分析完成-'.date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            $this->info('样本分析出错：'.$e->getMessage());
        }
        return 0;
    }
}