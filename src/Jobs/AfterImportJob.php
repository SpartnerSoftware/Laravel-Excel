<?php

namespace Maatwebsite\Excel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\BatchableTrait;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\HasEventBus;
use Maatwebsite\Excel\Reader;
use Throwable;

class AfterImportJob implements ShouldQueue
{
    use BatchableTrait, HasEventBus, InteractsWithQueue, Queueable, Dispatchable;

    /**
     * @var WithEvents
     */
    private $import;

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var iterable
     */
    private $dependencyIds = [];

    private $interval = 60;

    /**
     * @param  object  $import
     * @param  Reader  $reader
     */
    public function __construct($import, Reader $reader)
    {
        $this->import = $import;
        $this->reader = $reader;
    }

    public function setInterval(int $interval)
    {
        $this->interval = $interval;
    }

    public function setDependencies(Collection $jobs)
    {
        $this->dependencyIds = $jobs->map(function (ReadChunk $job) {
            return $job->getUniqueId();
        })->all();
    }

    public function handle()
    {
        // Determine if the batch has been cancelled...
        if ($this->batchCancelled()) {
            return;
        }

        foreach ($this->dependencyIds as $id) {
            if (!ReadChunk::isComplete($id)) {
                // Until there is no jobs left to run we put this job back into the queue every minute
                // Note: this will do nothing in a SyncQueue but that's desired, because in a SyncQueue jobs run in order
                $this->release($this->interval);

                return;
            }
        }

        if ($this->import instanceof ShouldQueue && $this->import instanceof WithEvents) {
            $this->reader->clearListeners();
            $this->reader->registerListeners($this->import->registerEvents());
        }

        $this->reader->afterImport($this->import);
    }

    /**
     * @param  Throwable  $e
     */
    public function failed(Throwable $e)
    {
        if ($this->import instanceof WithEvents) {
            $this->registerListeners($this->import->registerEvents());
            $this->raise(new ImportFailed($e));

            if (method_exists($this->import, 'failed')) {
                $this->import->failed($e);
            }
        }
    }
}
