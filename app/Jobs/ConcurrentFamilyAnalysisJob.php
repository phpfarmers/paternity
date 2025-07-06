<?php

namespace App\Jobs;

use App\Services\FamilyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
class ConcurrentFamilyAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fatherSampleName;
    protected $fatherSampleOutputDir;
    protected $childSampleName;
    protected $childSampleOutputDir;
    protected $newr;
    protected $news;
    protected $family;

    /**
     * Create a new job instance.
     *
     * @param string $ossPath
     */
    public function __construct($fatherSampleName, $fatherSampleOutputDir, $childSampleName, $childSampleOutputDir, $newr, $news, $family)
    {
        $this->fatherSampleName = $fatherSampleName;
        $this->fatherSampleOutputDir = $fatherSampleOutputDir;
        $this->childSampleName = $childSampleName;
        $this->childSampleOutputDir = $childSampleOutputDir;
        $this->newr = $newr;
        $this->news = $news;
        $this->family = $family;
    }
    
    public function handle()
    {
        return (new FamilyService())->run(
            $this->fatherSampleName,
            $this->fatherSampleOutputDir,
            $this->childSampleName,
            $this->childSampleOutputDir,
            '',
            '',
            $this->newr,
            $this->news,
            true,
            $this->family
        );
    }
}