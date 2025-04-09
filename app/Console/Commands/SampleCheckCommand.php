<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\SampleController;
use App\Models\Sample;

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
        $samples = Sample::where('check_result', Sample::CHECK_RESULT_UNKNOWN)->limit(10)->get();
        if ($samples->isEmpty()) {
            $this->info('没有要检测的样本');
            return 0;
        }
        foreach ($samples as $sample) {
            
        }
        $this->info('检测样本完成');
        return 0;
    }
}