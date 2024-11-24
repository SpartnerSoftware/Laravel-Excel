<?php

namespace Maatwebsite\Excel\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class QueueImport implements ShouldQueue
{
    use ExtendedQueueable, Dispatchable;

    /**
     * @var int
     */
    public $tries;

    /**
     * @var int
     */
    public $timeout;

    /**
     * @param  ShouldQueue  $import
     */
    public function __construct(?ShouldQueue $import = null)
    {
        if ($import) {
            $this->timeout = $import->timeout ?? null;
            $this->tries   = $import->tries ?? null;
        }
    }

    public function handle()
    {
        //
    }
}
