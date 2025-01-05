<?php

namespace Maatwebsite\Excel\Tests;

use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Files\RemoteTemporaryFile;
use Maatwebsite\Excel\Files\TemporaryFile;
use Maatwebsite\Excel\Jobs\AfterImportJob;
use Maatwebsite\Excel\Jobs\ReadChunk;
use Maatwebsite\Excel\SettingsProvider;
use Maatwebsite\Excel\Tests\Data\Stubs\AfterQueueImportJob;
use Maatwebsite\Excel\Tests\Data\Stubs\QueuedImport;
use Maatwebsite\Excel\Tests\Data\Stubs\QueuedImportWithFailure;
use Maatwebsite\Excel\Tests\Data\Stubs\QueuedImportWithMiddleware;
use Maatwebsite\Excel\Tests\Data\Stubs\QueuedImportWithRetryUntil;
use Maatwebsite\Excel\Tests\Data\Stubs\ShouldBatchImport;
use Throwable;

class QueuedImportTest extends TestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testing']);
        $this->loadMigrationsFrom(__DIR__ . '/Data/Stubs/Database/Migrations');
    }

    public function test_cannot_queue_import_that_does_not_implement_should_queue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Importable should implement ShouldQueue to be queued.');

        $import = new class
        {
            use Importable;
        };

        $import->queue('import-batches.xlsx');
    }

    public function test_can_queue_an_import()
    {
        $import = new QueuedImport();

        $chain = $import->queue('import-batches.xlsx')->chain([
            new AfterQueueImportJob(5000),
        ]);

        $this->assertInstanceOf(PendingDispatch::class, $chain);
    }

    public function test_can_batch_an_import()
    {
        if (!class_exists(\Illuminate\Bus\Batchable::class)) {
            $this->markTestSkipped('Batch jobs are not supported in this version of Laravel.');
        }

        $import = new ShouldBatchImport();

        $batch = $import->queue('import-batches.xlsx')->name('batch-import-name');

        $this->assertInstanceOf(PendingBatch::class, $batch);
        $this->assertEquals('batch-import-name', $batch->name);
        $this->assertCount(1, $batch->jobs);
    }

    public function test_can_queue_an_import_with_batch_cache_and_file_store()
    {
        config()->set('queue.default', 'sync');
        config()->set('excel.cache.driver', 'batch');
        config()->set('excel.cache.illuminate.store', 'file');
        config()->set('excel.cache.batch.memory_limit', 80);

        // Reset the cache settings
        $this->app->make(SettingsProvider::class)->provide();

        $import = new QueuedImport();

        $chain = $import->queue('import-batches.xlsx');

        $this->assertInstanceOf(PendingDispatch::class, $chain);
    }

    public function test_can_queue_import_with_remote_temp_disk()
    {
        config()->set('excel.temporary_files.remote_disk', 'test');

        // Delete the local temp file before each read chunk job
        // to simulate using a shared remote disk, without
        // having a dependency on a local temp file.
        Queue::before(function (JobProcessing $event) {
            if ($event->job->resolveName() === ReadChunk::class) {
                /** @var TemporaryFile $tempFile */
                $tempFile = $this->inspectJobProperty($event->job, 'temporaryFile');

                $this->assertInstanceOf(RemoteTemporaryFile::class, $tempFile);

                // Should exist remote
                $this->assertTrue(
                    $tempFile->exists()
                );

                $this->assertTrue(
                    unlink($tempFile->getLocalPath())
                );
            }
        });

        $import = new QueuedImport();

        $chain = $import->queue('import-batches.xlsx')->chain([
            new AfterQueueImportJob(5000),
        ]);

        $this->assertInstanceOf(PendingDispatch::class, $chain);
    }

    public function test_can_keep_extension_for_temp_file_on_remote_disk()
    {
        config()->set('excel.temporary_files.remote_disk', 'test');

        Queue::before(function (JobProcessing $event) {
            if ($event->job->resolveName() === ReadChunk::class) {
                /** @var TemporaryFile $tempFile */
                $tempFile = $this->inspectJobProperty($event->job, 'temporaryFile');

                $this->assertStringContains('.xlsx', $tempFile->getLocalPath());
            }
        });
        (new QueuedImport())->queue('import-batches.xlsx');
    }

    public function test_can_queue_import_with_remote_temp_disk_and_prefix()
    {
        config()->set('excel.temporary_files.remote_disk', 'test');
        config()->set('excel.temporary_files.remote_prefix', 'tmp/');

        $import = new QueuedImport();

        $chain = $import->queue('import-batches.xlsx')->chain([
            new AfterQueueImportJob(5000),
        ]);

        $this->assertInstanceOf(PendingDispatch::class, $chain);
    }

    public function test_can_automatically_delete_temp_file_on_failure_when_using_remote_disk()
    {
        config()->set('excel.temporary_files.remote_disk', 'test');
        $tempFile = '';

        Queue::exceptionOccurred(function (JobExceptionOccurred $event) use (&$tempFile) {
            if ($event->job->resolveName() === ReadChunk::class) {
                $tempFile = $this->inspectJobProperty($event->job, 'temporaryFile');
            }
        });

        try {
            (new QueuedImportWithFailure())->queue('import-batches.xlsx');
        } catch (Throwable $e) {
            $this->assertEquals('Something went wrong in the chunk', $e->getMessage());
        }

        $this->assertFalse($tempFile->existsLocally());
        $this->assertTrue($tempFile->exists());
    }

    public function test_cannot_automatically_delete_temp_file_on_failure_when_using_local_disk()
    {
        $tempFile = '';

        Queue::exceptionOccurred(function (JobExceptionOccurred $event) use (&$tempFile) {
            if ($event->job->resolveName() === ReadChunk::class) {
                $tempFile = $this->inspectJobProperty($event->job, 'temporaryFile');
            }
        });

        try {
            (new QueuedImportWithFailure())->queue('import-batches.xlsx');
        } catch (Throwable $e) {
            $this->assertEquals('Something went wrong in the chunk', $e->getMessage());
        }

        $this->assertTrue($tempFile->exists());
    }

    public function test_can_force_remote_download_and_deletion_for_each_chunk_on_queue()
    {
        config()->set('excel.temporary_files.remote_disk', 'test');
        config()->set('excel.temporary_files.force_resync_remote', true);
        Bus::fake([AfterImportJob::class]);

        Queue::after(function (JobProcessed $event) {
            if ($event->job->resolveName() === ReadChunk::class) {
                $tempFile = $this->inspectJobProperty($event->job, 'temporaryFile');

                // Should not exist locally after each chunk
                $this->assertFalse(
                    $tempFile->existsLocally()
                );
            }
        });

        (new QueuedImport())->queue('import-batches.xlsx');
    }

    public function test_can_define_middleware_method_on_queued_import()
    {
        try {
            (new QueuedImportWithMiddleware())->queue('import-batches.xlsx');
        } catch (Throwable $e) {
            $this->assertEquals('Job reached middleware method', $e->getMessage());
        }
    }

    public function test_can_define_retry_until_method_on_queued_import()
    {
        try {
            (new QueuedImportWithRetryUntil())->queue('import-batches.xlsx');
        } catch (Throwable $e) {
            $this->assertEquals('Job reached retryUntil method', $e->getMessage());
        }
    }

    public function test_can_define_max_exceptions_property_on_queued_import()
    {
        $maxExceptionsCount = 0;

        Queue::exceptionOccurred(function (JobExceptionOccurred $event) use (&$maxExceptionsCount) {
            if ($event->job->resolveName() === ReadChunk::class) {
                $maxExceptionsCount = $this->inspectJobProperty($event->job, 'maxExceptions');
            }
        });

        try {
            $import                = new QueuedImportWithFailure();
            $import->maxExceptions = 3;
            $import->queue('import-batches.xlsx');
        } catch (Throwable $e) {
            $this->assertEquals('Something went wrong in the chunk', $e->getMessage());
        }

        $this->assertEquals(3, $maxExceptionsCount);
    }
}
