<?php

namespace Maatwebsite\Excel\Fakes;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Traits\Macroable;
use Maatwebsite\Excel\Exporter;
use Maatwebsite\Excel\Importer;
use Maatwebsite\Excel\Reader;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExcelFake implements Exporter, Importer
{
    use Macroable;

    /**
     * @var array
     */
    protected $downloads = [];

    /**
     * @var array
     */
    protected $stored = [];

    /**
     * @var array
     */
    protected $queued = [];

    /**
     * @var array
     */
    protected $raws = [];

    /**
     * @var array
     */
    protected $imported = [];

    /**
     * @var bool
     */
    protected $matchByRegex = false;

    /**
     * @var object|null
     */
    protected $job;

    /**
     * {@inheritdoc}
     */
    public function download($export, string $fileName, string $writerType = null, array $headers = [])
    {
        $this->downloads[$fileName] = $export;

        return new BinaryFileResponse(__DIR__ . '/fake_file');
    }

    /**
     * {@inheritdoc}
     *
     * @param  string|null  $diskName  Fallback for usage with named properties
     */
    public function store($export, string $filePath, string $disk = null, string $writerType = null, $diskOptions = [], string $diskName = null)
    {
        if ($export instanceof ShouldQueue) {
            return $this->queue($export, $filePath, $disk ?: $diskName, $writerType);
        }

        $this->stored[$disk ?: $diskName ?: 'default'][$filePath] = $export;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function queue($export, string $filePath, string $disk = null, string $writerType = null, $diskOptions = [])
    {
        Queue::fake();

        $this->stored[$disk ?? 'default'][$filePath] = $export;
        $this->queued[$disk ?? 'default'][$filePath] = $export;

        $this->job = new class
        {
            use Queueable;

            public function handle()
            {
                //
            }
        };

        Queue::push($this->job);

        return new PendingDispatch($this->job);
    }

    /**
     * @param  object  $export
     * @param  string  $writerType
     * @return string
     */
    public function raw($export, string $writerType)
    {
        $this->raws[get_class($export)] = $export;

        return 'RAW-CONTENTS';
    }

    /**
     * @param  object  $import
     * @param  string|UploadedFile  $file
     * @param  string|null  $disk
     * @param  string|null  $readerType
     * @return Reader|PendingDispatch
     */
    public function import($import, $file, string $disk = null, string $readerType = null)
    {
        if ($import instanceof ShouldQueue) {
            return $this->queueImport($import, $file, $disk, $readerType);
        }

        $filePath = ($file instanceof UploadedFile) ? $file->getClientOriginalName() : $file;

        $this->imported[$disk ?? 'default'][$filePath] = $import;

        return $this;
    }

    /**
     * @param  object  $import
     * @param  string|UploadedFile  $file
     * @param  string|null  $disk
     * @param  string|null  $readerType
     * @return array
     */
    public function toArray($import, $file, string $disk = null, string $readerType = null): array
    {
        $filePath = ($file instanceof UploadedFile) ? $file->getFilename() : $file;

        $this->imported[$disk ?? 'default'][$filePath] = $import;

        return [];
    }

    /**
     * @param  object  $import
     * @param  string|UploadedFile  $file
     * @param  string|null  $disk
     * @param  string|null  $readerType
     * @return Collection
     */
    public function toCollection($import, $file, string $disk = null, string $readerType = null): Collection
    {
        $filePath = ($file instanceof UploadedFile) ? $file->getFilename() : $file;

        $this->imported[$disk ?? 'default'][$filePath] = $import;

        return new Collection();
    }

    /**
     * @param  ShouldQueue  $import
     * @param  string|UploadedFile  $file
     * @param  string|null  $disk
     * @param  string  $readerType
     * @return PendingDispatch
     */
    public function queueImport(ShouldQueue $import, $file, string $disk = null, string $readerType = null)
    {
        Queue::fake();

        $filePath = ($file instanceof UploadedFile) ? $file->getFilename() : $file;

        $this->queued[$disk ?? 'default'][$filePath]   = $import;
        $this->imported[$disk ?? 'default'][$filePath] = $import;

        $this->job = new class
        {
            use Queueable;

            public function handle()
            {
                //
            }
        };

        Queue::push($this->job);

        return new PendingDispatch($this->job);
    }

    /**
     * When asserting downloaded, stored, queued or imported, use regular expression
     * to look for a matching file path.
     *
     * @return void
     */
    public function matchByRegex()
    {
        $this->matchByRegex = true;
    }

    /**
     * When asserting downloaded, stored, queued or imported, use regular string
     * comparison for matching file path.
     *
     * @return void
     */
    public function doNotMatchByRegex()
    {
        $this->matchByRegex = false;
    }

    /**
     * @param  string  $fileName
     * @param  callable|null  $callback
     */
    public function assertDownloaded(string $fileName, $callback = null)
    {
        $fileName = $this->assertArrayHasKey($fileName, $this->downloads, sprintf('%s is not downloaded', $fileName));

        $callback = $callback ?: function () {
            return true;
        };

        Assert::assertTrue(
            $callback($this->downloads[$fileName]),
            "The file [{$fileName}] was not downloaded with the expected data."
        );
    }

    /**
     * @param  string  $filePath
     * @param  string|callable|null  $disk
     * @param  callable|null  $callback
     */
    public function assertStored(string $filePath, $disk = null, $callback = null)
    {
        if (is_callable($disk)) {
            $callback = $disk;
            $disk     = null;
        }

        $disk         = $disk ?? 'default';
        $storedOnDisk = $this->stored[$disk] ?? [];

        $filePath = $this->assertArrayHasKey(
            $filePath,
            $storedOnDisk,
            sprintf('%s is not stored on disk %s', $filePath, $disk)
        );

        $callback = $callback ?: function () {
            return true;
        };

        Assert::assertTrue(
            $callback($storedOnDisk[$filePath]),
            "The file [{$filePath}] was not stored with the expected data."
        );
    }

    /**
     * @param  string  $filePath
     * @param  string|callable|null  $disk
     * @param  callable|null  $callback
     */
    public function assertQueued(string $filePath, $disk = null, $callback = null)
    {
        if (is_callable($disk)) {
            $callback = $disk;
            $disk     = null;
        }

        $disk          = $disk ?? 'default';
        $queuedForDisk = $this->queued[$disk] ?? [];

        $filePath = $this->assertArrayHasKey(
            $filePath,
            $queuedForDisk,
            sprintf('%s is not queued for export on disk %s', $filePath, $disk)
        );

        $callback = $callback ?: function () {
            return true;
        };

        Assert::assertTrue(
            $callback($queuedForDisk[$filePath]),
            "The file [{$filePath}] was not stored with the expected data."
        );
    }

    public function assertQueuedWithChain($chain): void
    {
        Queue::assertPushedWithChain(get_class($this->job), $chain);
    }

    /**
     * @param  string  $classname
     * @param  callable|null  $callback
     */
    public function assertExportedInRaw(string $classname, $callback = null)
    {
        Assert::assertArrayHasKey($classname, $this->raws, sprintf('%s is not exported in raw', $classname));

        $callback = $callback ?: function () {
            return true;
        };

        Assert::assertTrue(
            $callback($this->raws[$classname]),
            "The [{$classname}] export was not exported in raw with the expected data."
        );
    }

    /**
     * @param  string  $filePath
     * @param  string|callable|null  $disk
     * @param  callable|null  $callback
     */
    public function assertImported(string $filePath, $disk = null, $callback = null)
    {
        if (is_callable($disk)) {
            $callback = $disk;
            $disk     = null;
        }

        $disk           = $disk ?? 'default';
        $importedOnDisk = $this->imported[$disk] ?? [];

        $filePath = $this->assertArrayHasKey(
            $filePath,
            $importedOnDisk,
            sprintf('%s is not stored on disk %s', $filePath, $disk)
        );

        $callback = $callback ?: function () {
            return true;
        };

        Assert::assertTrue(
            $callback($importedOnDisk[$filePath]),
            "The file [{$filePath}] was not imported with the expected data."
        );
    }

    /**
     * Asserts that an array has a specified key and returns the key if successful.
     *
     * @see matchByRegex for more information about file path matching
     *
     * @param  string  $key
     * @param  array  $array
     * @param  string  $message
     * @return string
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     * @throws Exception
     */
    protected function assertArrayHasKey(string $key, array $disk, string $message = ''): string
    {
        if ($this->matchByRegex) {
            $files   = array_keys($disk);
            $results = preg_grep($key, $files);
            Assert::assertGreaterThan(0, count($results), $message);
            Assert::assertEquals(1, count($results), "More than one result matches the file name expression '$key'.");

            return array_values($results)[0];
        }
        Assert::assertArrayHasKey($key, $disk, $message);

        return $key;
    }
}
