<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sample;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SampleCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sample:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动检测样本';

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
        $data = config('data');
        $ossData = $data['oss_data_local'] ?? '/akdata/oss_data/'; // oss数据目录 样本数据下机目录

        $samples = Sample::where('check_result', Sample::CHECK_RESULT_UNKNOWN)
        ->orderBy('check_times', 'asc')
        ->orderBy('id', 'asc')
        ->limit(10)->get();
        if ($samples->isEmpty()) {
            $this->info('没有要检测的样本');
            return 0;
        }
        try {
            foreach ($samples as $sample) {
                $this->info('检测样本开始：'.$sample->id).'-'.date('Y-m-d H:i:s');
                // 检测次数大于3000次-更新检测结果状态为失败
                if ($sample->check_times >= 3000) {
                    $sample->check_result = Sample::CHECK_RESULT_FAIL;
                    $sample->save();
                    continue;
                }
                
                // 样本测试变为检测中
                $sample->check_result = Sample::CHECK_RESULT_CHECKING;
                $sample->check_times += 1;
                $sample->save();

                $this->doProcess($sample);
                /* continue;
                // shell命令参数
                $searchPattern = escapeshellarg("*{$sample->sample_name}*.gz"); // 搜索模式-样本名
                // $searchPattern = escapeshellarg('*Ignition.php'); // 测试
                $searchPath = escapeshellarg($ossData); // 搜索路径
                // $command = "find {$searchPath} -name {$searchPattern} -type f -printf '%T@ %p\n' | sort -nr | cut -d' ' -f2-";
                // $command = "find {$searchPath} -name {$searchPattern} -type f";
                // $command = sprintf('/usr/bin/find %s -type f -name %s 2>/dev/null', $searchPath, $searchPattern);
                $command = '/usr/bin/find ' . $searchPath . ' -type f -name ' . $searchPattern . ' 2>/dev/null';
                $this->info('执行命令：'.$command);
                Log::info('执行命令：'.$command);
                // 执行shell命令
                exec($command, $output, $returnVar);
                
                if ($returnVar === 0) {
                    $this->info("找到以下文件:");
                    $r1Url = ''; // R1数据文件
                    $r2Url = ''; // R2数据文件

                    foreach ($output as $file) {
                        $this->info($file);
                        // 获取文件名
                        $fileName = basename($file);
                        Log::info($sample->sample_name . ":" . $file);
                        // Log::info($sample->sample_name . ":" . $fileName);
                        // r1文件路径
                        if ((strpos($fileName, '1.fq.gz') !== false || strpos($fileName, '1.fastq.gz') !== false) && empty($r1Url)) {
                            $r1Url = $file;
                            $this->info('r1Url:' . $r1Url);
                            Log::info($sample->sample_name . ":" . 'r1Url:' . $r1Url);
                        }
                        // r2文件路径
                        if ((strpos($fileName, '2.fq.gz') !== false || strpos($fileName, '2.fastq.gz') !== false) && empty($r2Url)) {
                            $r2Url = $file;
                            $this->info('r2Url:' . $r2Url);
                            Log::info($sample->sample_name . ":" . 'r2Url:' . $r2Url);
                        }
                        $this->info($fileName);
                    }
                    // 符合条件-更新检测结果状态为成功
                    if(!empty($r1Url) && !empty($r2Url)){
                        $sample->check_result = Sample::CHECK_RESULT_SUCCESS;
                        $sample->off_machine_time = date('Y-m-d');
                        $sample->r1_url = $r1Url;
                        $sample->r2_url = $r2Url;
                        $sample->save();
                    }else{
                        // 未下机-变为未检测-继续检测
                        $this->info("文件数量不正确:r1url:{$r1Url}-r2url:{$r2Url}");
                        Log::info("文件数量不正确:r1url:{$r1Url}-r2url:{$r2Url}");
                        $sample->check_result = Sample::CHECK_RESULT_UNKNOWN;
                        $sample->save();
                    }
                } else {
                    $this->error("未找到文件或命令执行失败");
                    // 不符合条件-更新检测结果状态为失败
                    $sample->check_result = Sample::CHECK_RESULT_FAIL;
                    $sample->save();
                } */
                $this->info('检测样本完成-'.date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            $this->info('检测样本出错：'.$e->getMessage());
        }
        return 0;
    }

    public function doProcess($sample){
        $sampleName = $sample->sample_name;
        $process = new Process(["/usr/bin/find", "/akdata/oss_data/", "-type", "f", "-name", "*".$sampleName."*.gz"]);
        $process->run();

        if ($process->isSuccessful()) {
            $files = array_filter(explode("\n", trim($process->getOutput())));
            foreach ($files as $file) {
                $fileName = basename($file);
                Log::info($sampleName . ":" . $file);
                // Log::info($sample->sample_name . ":" . $fileName);
                // r1文件路径
                if ((strpos($fileName, '1.fq.gz') !== false || strpos($fileName, '1.fastq.gz') !== false) && empty($r1Url)) {
                    $r1Url = $file;
                    $this->info('r1Url:' . $r1Url);
                    Log::info($sampleName . ":" . 'r1Url:' . $r1Url);
                }
                // r2文件路径
                if ((strpos($fileName, '2.fq.gz') !== false || strpos($fileName, '2.fastq.gz') !== false) && empty($r2Url)) {
                    $r2Url = $file;
                    $this->info('r2Url:' . $r2Url);
                    Log::info($sampleName . ":" . 'r2Url:' . $r2Url);
                }
            }
            
            // 符合条件-更新检测结果状态为成功
            if(!empty($r1Url) && !empty($r2Url)){
                $sample->check_result = Sample::CHECK_RESULT_SUCCESS;
                $sample->off_machine_time = date('Y-m-d');
                $sample->r1_url = $r1Url;
                $sample->r2_url = $r2Url;
                $sample->save();
            }else{
                // 未下机-变为未检测-继续检测
                $this->info("文件数量不正确:r1url:{$r1Url}-r2url:{$r2Url}");
                Log::info("文件数量不正确:r1url:{$r1Url}-r2url:{$r2Url}");
                $sample->check_result = Sample::CHECK_RESULT_UNKNOWN;
                $sample->save();
            }
        } else {
            Log::error("执行失败: " . $process->getErrorOutput());
            // 不符合条件-更新检测结果状态为失败
            $sample->check_result = Sample::CHECK_RESULT_FAIL;
            $sample->save();
        }

        return true;
    }
}