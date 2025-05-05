<?php

namespace App\Jobs;

use App\Models\Sample;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SampleAnalysisRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;
    public $tries = 3;
    public $timeout = 3000;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function handle(): void
    {
        Log::info("开始样本分析：{$this->id}-".date('Y-m-d H:i:s'));
        $this->runAnalysis($this->id);
        Log::info("样本分析结束：{$this->id}-".date('Y-m-d H:i:s'));
    }

    protected function runAnalysis($id): void
    {
        start:
        $samples = Sample::where('check_result', Sample::CHECK_RESULT_SUCCESS)
            ->where('id', $id)
            ->where('analysis_result', '!=', Sample::ANALYSIS_RESULT_SUCCESS)
            ->orderBy('analysis_times', 'asc')
            ->orderBy('id', 'asc')
            ->limit(1)
            ->get();

        if ($samples->isEmpty()) {
            Log::info('没有要分析的样本');
            return;
        }

        $analyzingCount = Sample::where('analysis_result', Sample::ANALYSIS_RESULT_ANALYZING)->count();
        if ($analyzingCount > 50) {
            Log::info('正在分析中的样本数量大于50，停止1分钟');
            sleep(60);
            goto start;
        }

        foreach ($samples as $sample) {
            try {
                Log::info('样本分析开始：'.$sample->id.'-'.date('Y-m-d H:i:s'));
                
                $sample->analysis_result = Sample::ANALYSIS_RESULT_ANALYZING;
                $sample->analysis_times += 1;
                $sample->save();

                // 准备命令参数
                $command = $this->buildCommand($sample);
                Log::info('执行命令：'.$command);

                // 使用 proc_open 执行命令
                $process = $this->executeCommand($command);
                
                if ($process['status'] === 0) {
                    Log::info("命令执行成功");
                    $sample->analysis_time = date('Y-m-d');
                    $sample->analysis_result = Sample::ANALYSIS_RESULT_SUCCESS;
                } else {
                    Log::error("命令执行失败，返回码: ".$process['status']);
                    Log::error("错误输出: ".$process['error']);
                    $sample->analysis_result = Sample::CHECK_RESULT_FAIL;
                    $sample->output_dir = $this->getOutputDir($sample);
                }
                
                $sample->save();
                Log::info('样本分析完成-'.date('Y-m-d H:i:s'));

            } catch (\Exception $e) {
                Log::error('样本分析出错：'.$e->getMessage());
                if (isset($sample)) {
                    $sample->analysis_result = Sample::CHECK_RESULT_FAIL;
                    $sample->save();
                }
            }
        }
    }

    /**
     * 构建执行命令
     */
    protected function buildCommand(Sample $sample): string
    {
        $sampleName = escapeshellarg($sample->sample_name);
        $r1Url = escapeshellarg($sample->r1_url);
        $r2Url = escapeshellarg($sample->r2_url);
        $analysisProcess = escapeshellarg($sample->analysis_process);
        $u = empty(trim($sample->analysis_process)) ? '' : ' -u '.$analysisProcess;

        $ossAnalysisProjectLocal = config('data')['analysis_project'];
        $outputDir = 'pipeline_'.$sample->sample_name.'_run_'.date('YmdHis');
        $outputFullDir = escapeshellarg($ossAnalysisProjectLocal.$outputDir);

        $commandPl = config('data')['sample_analysis_run_command_pl'];
        
        return $commandPl." -s {$sampleName} -r1 {$r1Url} -r2 {$r2Url}{$u} -o {$outputFullDir}";
    }

    /**
     * 使用 proc_open 执行命令
     */
    protected function executeCommand(string $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);
        
        if (!is_resource($process)) {
            throw new \RuntimeException("无法启动进程");
        }

        // 关闭不需要的 stdin
        fclose($pipes[0]);

        // 读取输出
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        // 关闭管道
        fclose($pipes[1]);
        fclose($pipes[2]);

        // 获取返回状态
        $status = proc_close($process);

        // 记录输出日志
        if (!empty($output)) {
            Log::info("命令输出: ".$output);
        }

        return [
            'status' => $status,
            'output' => $output,
            'error' => $error
        ];
    }

    /**
     * 获取输出目录
     */
    protected function getOutputDir(Sample $sample): string
    {
        return 'pipeline_'.$sample->sample_name.'_run_'.date('YmdHis');
    }
}
