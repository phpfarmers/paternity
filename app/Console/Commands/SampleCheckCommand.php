<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sample;
use Illuminate\Support\Facades\Log;

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
        ->limit(1)->get();
        if ($samples->isEmpty()) {
            $this->info('没有要检测的样本');
            return 0;
        }
        try {
            foreach ($samples as $sample) {
                $this->info('检测样本开始：'.$sample->id).'-'.date('Y-m-d H:i:s');
                // 样本测试变为检测中
                $sample->check_result = Sample::CHECK_RESULT_CHECKING;
                $sample->check_times += 1;
                $sample->save();
                
                // shell命令参数
                $searchPattern = escapeshellarg('*'.$sample->sample_name.'*.gz'); // 搜索模式-样本名
                // $searchPattern = escapeshellarg('*Ignition.php'); // 测试
                $searchPath = escapeshellarg($ossData); // 搜索路径
                $command = "find {$searchPath} -name {$searchPattern}";
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
                        // r1文件路径
                        if(strpos($fileName, '1.fq.gz') !== false || strpos($fileName, '1.fastq.gz') !== false){
                            $r1Url = $file;
                        }
                        // r2文件路径
                        if(strpos($fileName, '2.fq.gz') !== false || strpos($fileName, '2.fastq.gz') !== false){
                            $r2Url = $file;
                        }
                        $this->info($fileName);
                    }
                    // 符合条件-更新检测结果状态为成功
                    if(count($output) == 2 && !empty($r1Url) && !empty($r2Url)){
                        $sample->check_result = Sample::CHECK_RESULT_SUCCESS;
                        $sample->off_machine_time = date('Y-m-d');
                        $sample->r1_url = $r1Url;
                        $sample->r2_url = $r2Url;
                        $sample->save();
                    }else{
                        // 未下机-变为未检测-继续检测
                        $this->info("文件数量不正确");
                        $sample->check_result = Sample::CHECK_RESULT_UNKNOWN;
                        $sample->save();
                    }
                } else {
                    $this->error("未找到文件或命令执行失败");
                    // 不符合条件-更新检测结果状态为失败
                    $sample->check_result = Sample::CHECK_RESULT_FAIL;
                    $sample->save();
                }
                $this->info('检测样本完成-'.date('Y-m-d H:i:s'));
            }
        } catch (\Exception $e) {
            $this->info('检测样本出错：'.$e->getMessage());
        }
        return 0;
    }
}