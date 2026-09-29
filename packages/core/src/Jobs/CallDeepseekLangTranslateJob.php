<?php

namespace Baracod\Larastarterkit\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CallDeepseekLangTranslateJob implements ShouldQueue
{
    use Queueable;

    protected $functionToCall;

    /**
     * Create a new job instance.
     */
    public function __construct($functionToCall)
    {
        $this->functionToCall = $functionToCall;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        call_user_func($this->functionToCall);
    }
}
