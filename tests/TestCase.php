<?php

namespace Maatwebsite\Excel\Tests;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Testing\File;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Constraint\StringContains;

class TestCase extends OrchestraTestCase
{
    /**
     * @param  string  $filePath
     * @param  string  $writerType
     * @return \PhpOffice\PhpSpreadsheet\Spreadsheet
     *
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function read(string $filePath, string $writerType)
    {
        $reader = IOFactory::createReader($writerType);

        return $reader->load($filePath);
    }

    /**
     * @param  string  $filePath
     * @param  ?string  $filename
     * @return File
     */
    public function givenUploadedFile(string $filePath, ?string $filename = null): File
    {
        $filename = $filename ?? basename($filePath);

        // Create temporary file.
        $newFilePath = tempnam(sys_get_temp_dir(), 'import-');

        // Copy the existing file to a temporary file.
        copy($filePath, $newFilePath);

        return new File($filename, fopen($newFilePath, 'r'));
    }

    /**
     * @param  string  $filePath
     * @param  string  $writerType
     * @param  ?int  $sheetIndex
     * @return array
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function readAsArray(string $filePath, string $writerType, ?int $sheetIndex = null)
    {
        $spreadsheet = $this->read($filePath, $writerType);

        if (null === $sheetIndex) {
            $sheet = $spreadsheet->getActiveSheet();
        } else {
            $sheet = $spreadsheet->getSheet($sheetIndex);
        }

        return $sheet->toArray();
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ExcelServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('filesystems.disks.local.root', __DIR__ . '/Data/Disks/Local');
        $app['config']->set('filesystems.disks.test', [
            'driver' => 'local',
            'root'   => __DIR__ . '/Data/Disks/Test',
        ]);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('view.paths', [
            __DIR__ . '/Data/Stubs/Views',
        ]);
    }

    /**
     * @param  Job  $job
     * @param  string  $property
     * @return mixed
     */
    protected function inspectJobProperty(Job $job, string $property)
    {
        $dict  = (array) unserialize($job->payload()['data']['command']);
        $class = $job->resolveName();

        return $dict[$property] ?? $dict["\0*\0$property"] ?? $dict["\0$class\0$property"];
    }

    /**
     * @param  string  $needle
     * @param  string  $haystack
     * @param  string  $message
     */
    protected function assertStringContains(string $needle, string $haystack, string $message = '')
    {
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString($needle, $haystack, $message);
        } else {
            static::assertThat($haystack, new StringContains($needle, false), $message);
        }
    }

    /**
     * @param  string  $path
     */
    protected function assertFileMissing(string $path)
    {
        if (method_exists($this, 'assertFileDoesNotExist')) {
            $this->assertFileDoesNotExist($path);
        } else {
            $this->assertFileNotExists($path);
        }
    }

    protected function assertRegex(string $pattern, string $string)
    {
        if (method_exists($this, 'assertMatchesRegularExpression')) {
            $this->assertMatchesRegularExpression($pattern, $string);
        } else {
            $this->assertRegExp($pattern, $string);
        }
    }
}
