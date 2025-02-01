<?php

namespace Maatwebsite\Excel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Maatwebsite\Excel\Concerns\BatchableTrait;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Files\TemporaryFile;
use Maatwebsite\Excel\Writer;

class CloseSheet implements ShouldQueue
{
    use BatchableTrait, Queueable, Dispatchable, ProxyFailures, InteractsWithQueue;

    /**
     * @var object
     */
    private $sheetExport;

    /**
     * @var string
     */
    private $temporaryFile;

    /**
     * @var string
     */
    private $writerType;

    /**
     * @var int
     */
    private $sheetIndex;

    /**
     * @param  object  $sheetExport
     * @param  TemporaryFile  $temporaryFile
     * @param  string  $writerType
     * @param  int  $sheetIndex
     */
    public function __construct($sheetExport, TemporaryFile $temporaryFile, string $writerType, int $sheetIndex)
    {
        $this->sheetExport   = $sheetExport;
        $this->temporaryFile = $temporaryFile;
        $this->writerType    = $writerType;
        $this->sheetIndex    = $sheetIndex;
    }

    /**
     * @param  Writer  $writer
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function handle(Writer $writer)
    {
        // Determine if the batch has been cancelled...
        if ($this->batchCancelled()) {
            return;
        }

        $writer = $writer->reopen(
            $this->temporaryFile,
            $this->writerType
        );

        $sheet = $writer->getSheetByIndex($this->sheetIndex);

        if ($this->sheetExport instanceof WithEvents) {
            $sheet->registerListeners($this->sheetExport->registerEvents());
        }

        $sheet->close($this->sheetExport);

        $writer->write(
            $this->sheetExport,
            $this->temporaryFile,
            $this->writerType
        );
    }
}
