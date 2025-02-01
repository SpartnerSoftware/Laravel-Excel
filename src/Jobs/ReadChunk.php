<?php

namespace Maatwebsite\Excel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\BatchableTrait;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterChunk;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Files\RemoteTemporaryFile;
use Maatwebsite\Excel\Files\TemporaryFile;
use Maatwebsite\Excel\Filters\ChunkReadFilter;
use Maatwebsite\Excel\HasEventBus;
use Maatwebsite\Excel\Imports\HeadingRowExtractor;
use Maatwebsite\Excel\Sheet;
use Maatwebsite\Excel\Transactions\TransactionHandler;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use Throwable;

class ReadChunk implements ShouldQueue
{
    use BatchableTrait, Queueable, HasEventBus, InteractsWithQueue;

    /**
     * @var int
     */
    public $timeout;

    /**
     * @var int
     */
    public $tries;

    /**
     * @var int
     */
    public $maxExceptions;

    /**
     * @var int
     */
    public $backoff;

    /**
     * @var string
     */
    public $queue;

    /**
     * @var string
     */
    public $connection;

    /**
     * @var WithChunkReading
     */
    private $import;

    /**
     * @var IReader
     */
    private $reader;

    /**
     * @var TemporaryFile
     */
    private $temporaryFile;

    /**
     * @var string
     */
    private $sheetName;

    /**
     * @var object
     */
    private $sheetImport;

    /**
     * @var int
     */
    private $startRow;

    /**
     * @var int
     */
    private $chunkSize;

    /**
     * @var string
     */
    private $uniqueId;

    /**
     * @param  WithChunkReading  $import
     * @param  IReader  $reader
     * @param  TemporaryFile  $temporaryFile
     * @param  string  $sheetName
     * @param  object  $sheetImport
     * @param  int  $startRow
     * @param  int  $chunkSize
     */
    public function __construct(WithChunkReading $import, IReader $reader, TemporaryFile $temporaryFile, string $sheetName, $sheetImport, int $startRow, int $chunkSize)
    {
        $this->import        = $import;
        $this->reader        = $reader;
        $this->temporaryFile = $temporaryFile;
        $this->sheetName     = $sheetName;
        $this->sheetImport   = $sheetImport;
        $this->startRow      = $startRow;
        $this->chunkSize     = $chunkSize;
        $this->timeout       = $import->timeout ?? null;
        $this->tries         = $import->tries ?? null;
        $this->maxExceptions = $import->maxExceptions ?? null;
        $this->backoff       = method_exists($import, 'backoff') ? $import->backoff() : ($import->backoff ?? null);
        $this->connection    = property_exists($import, 'connection') ? $import->connection : null;
        $this->queue         = property_exists($import, 'queue') ? $import->queue : null;
    }

    public function getUniqueId(): string
    {
        if (!isset($this->uniqueId)) {
            $this->uniqueId = uniqid();
            Cache::set('laravel-excel/read-chunk/' . $this->uniqueId, true);
        }

        return $this->uniqueId;
    }

    public static function isComplete(string $id): bool
    {
        return !Cache::has('laravel-excel/read-chunk/' . $id);
    }

    /**
     * Get the middleware the job should be dispatched through.
     *
     * @return array
     */
    public function middleware()
    {
        return (method_exists($this->import, 'middleware')) ? $this->import->middleware() : [];
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return (method_exists($this->import, 'retryUntil')) ? $this->import->retryUntil() : null;
    }

    /**
     * @param  TransactionHandler  $transaction
     *
     * @throws \Maatwebsite\Excel\Exceptions\SheetNotFoundException
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function handle(TransactionHandler $transaction)
    {
        // Determine if the batch has been cancelled...
        if ($this->batchCancelled()) {
            return;
        }

        if (method_exists($this->import, 'setChunkOffset')) {
            $this->import->setChunkOffset($this->startRow);
        }

        if (method_exists($this->sheetImport, 'setChunkOffset')) {
            $this->sheetImport->setChunkOffset($this->startRow);
        }

        if ($this->sheetImport instanceof WithCustomValueBinder) {
            Cell::setValueBinder($this->sheetImport);
        }

        $headingRow = HeadingRowExtractor::headingRow($this->sheetImport);

        $filter = new ChunkReadFilter(
            $headingRow,
            $this->startRow,
            $this->chunkSize,
            $this->sheetName
        );

        $this->reader->setReadFilter($filter);
        $this->reader->setReadDataOnly(config('excel.imports.read_only', true));
        $this->reader->setReadEmptyCells(!config('excel.imports.ignore_empty', false));

        $spreadsheet = $this->reader->load(
            $this->temporaryFile->sync()->getLocalPath()
        );

        $sheet = Sheet::byName(
            $spreadsheet,
            $this->sheetName
        );

        if ($sheet->getHighestRow() < $this->startRow) {
            $sheet->disconnect();

            $this->cleanUpTempFile();

            return;
        }

        $transaction(function () use ($sheet) {
            $sheet->import(
                $this->sheetImport,
                $this->startRow
            );

            $sheet->disconnect();

            $this->cleanUpTempFile();

            $sheet->raise(new AfterChunk($sheet, $this->import, $this->startRow));
        });
    }

    /**
     * @param  Throwable  $e
     */
    public function failed(Throwable $e)
    {
        $this->cleanUpTempFile(true);

        if ($this->import instanceof WithEvents) {
            $this->registerListeners($this->import->registerEvents());
            $this->raise(new ImportFailed($e));

            if (method_exists($this->import, 'failed')) {
                $this->import->failed($e);
            }
        }
    }

    private function cleanUpTempFile(bool $force = false): bool
    {
        if (!empty($this->uniqueId)) {
            Cache::delete('laravel-excel/read-chunk/' . $this->uniqueId);
        }

        if (!$force && !config('excel.temporary_files.force_resync_remote')) {
            return true;
        }

        if (!$this->temporaryFile instanceof RemoteTemporaryFile) {
            return true;
        }

        return $this->temporaryFile->deleteLocalCopy();
    }
}
