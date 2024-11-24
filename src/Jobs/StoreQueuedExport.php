<?php

namespace Maatwebsite\Excel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Files\Filesystem;
use Maatwebsite\Excel\Files\TemporaryFile;

class StoreQueuedExport implements ShouldQueue
{
    use Queueable;

    /**
     * @var string
     */
    private $filePath;

    /**
     * @var string|null
     */
    private $disk;

    /**
     * @var TemporaryFile
     */
    private $temporaryFile;
    /**
     * @var array|string
     */
    private $diskOptions;

    /**
     * @param  TemporaryFile  $temporaryFile
     * @param  string  $filePath
     * @param  string|null  $disk
     * @param  array|string  $diskOptions
     */
    public function __construct(TemporaryFile $temporaryFile, string $filePath, ?string $disk = null, $diskOptions = [])
    {
        $this->disk          = $disk;
        $this->filePath      = $filePath;
        $this->temporaryFile = $temporaryFile;
        $this->diskOptions   = $diskOptions;
    }

    /**
     * @param  Filesystem  $filesystem
     */
    public function handle(Filesystem $filesystem)
    {
        $filesystem->disk($this->disk, $this->diskOptions)->copy(
            $this->temporaryFile,
            $this->filePath
        );

        $this->temporaryFile->delete();
    }
}
