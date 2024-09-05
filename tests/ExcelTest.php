<?php

namespace Maatwebsite\Excel\Tests;

use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Maatwebsite\Excel\Importer;
use Maatwebsite\Excel\Tests\Data\Stubs\EmptyExport;
use Maatwebsite\Excel\Tests\Helpers\FileHelper;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelTest extends TestCase
{
    /**
     * @var Excel
     */
    protected $SUT;

    protected function setUp(): void
    {
        parent::setUp();

        $this->SUT = $this->app->make(Excel::class);
    }

    public function test_can_download_an_export_object_with_facade()
    {
        $export = new EmptyExport();

        $response = ExcelFacade::download($export, 'filename.xlsx');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertEquals('attachment; filename=filename.xlsx', str_replace('"', '', $response->headers->get('Content-Disposition')));
    }

    public function test_can_download_an_export_object()
    {
        $export = new EmptyExport();

        $response = $this->SUT->download($export, 'filename.xlsx');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertEquals('attachment; filename=filename.xlsx', str_replace('"', '', $response->headers->get('Content-Disposition')));
    }

    public function test_can_store_an_export_object_on_default_disk()
    {
        $export = new EmptyExport;
        $name   = 'filename.xlsx';
        $path   = FileHelper::absolutePath($name, 'local');

        @unlink($path);

        $this->assertFileMissing($path);

        $response = $this->SUT->store($export, $name);

        $this->assertTrue($response);
        $this->assertFileExists($path);
    }

    public function test_can_store_an_export_object_on_another_disk()
    {
        $export = new EmptyExport;
        $name   = 'filename.xlsx';
        $path   = FileHelper::absolutePath($name, 'test');

        @unlink($path);

        $this->assertFileMissing($path);

        $response = $this->SUT->store($export, $name, 'test');

        $this->assertTrue($response);
        $this->assertFileExists($path);
    }

    public function test_can_store_csv_export_with_default_settings()
    {
        $export = new EmptyExport;
        $name   = 'filename.csv';
        $path   = FileHelper::absolutePath($name, 'local');

        @unlink($path);

        $this->assertFileMissing($path);

        $response = $this->SUT->store($export, $name);

        $this->assertTrue($response);
        $this->assertFileExists($path);
    }

    public function test_can_get_raw_export_contents()
    {
        $export = new EmptyExport;

        $response = $this->SUT->raw($export, Excel::XLSX);

        $this->assertNotEmpty($response);
    }

    public function test_can_store_tsv_export_with_default_settings()
    {
        $export = new EmptyExport;
        $name   = 'filename.tsv';
        $path   = FileHelper::absolutePath($name, 'local');

        @unlink($path);

        $this->assertFileMissing($path);

        $response = $this->SUT->store($export, $name);

        $this->assertTrue($response);
        $this->assertFileExists($path);
    }

    public function test_can_store_csv_export_with_custom_settings()
    {
        $export = new class implements WithEvents, FromCollection, WithCustomCsvSettings
        {
            use RegistersEventListeners;

            /**
             * @return Collection
             */
            public function collection()
            {
                return collect([
                    ['A1', 'B1'],
                    ['A2', 'B2'],
                ]);
            }

            /**
             * @return array
             */
            public function getCsvSettings(): array
            {
                return [
                    'line_ending'            => PHP_EOL,
                    'enclosure'              => '"',
                    'delimiter'              => ';',
                    'include_separator_line' => true,
                    'excel_compatibility'    => false,
                ];
            }
        };

        $this->SUT->store($export, 'filename.csv');

        $contents = file_get_contents(__DIR__ . '/Data/Disks/Local/filename.csv');

        $this->assertStringContains('sep=;', $contents);
        $this->assertStringContains('"A1";"B1"', $contents);
        $this->assertStringContains('"A2";"B2"', $contents);
    }

    public function test_cannot_use_from_collection_and_from_view_on_same_export()
    {
        $this->expectException(\Maatwebsite\Excel\Exceptions\ConcernConflictException::class);
        $this->expectExceptionMessage('Cannot use FromQuery, FromArray or FromCollection and FromView on the same sheet');

        $export = new class implements FromCollection, FromView
        {
            use Exportable;

            /**
             * @return Collection
             */
            public function collection()
            {
                return collect();
            }

            /**
             * @return View
             */
            public function view(): View
            {
                return view('users');
            }
        };

        $export->download('filename.csv');
    }

    public function test_can_import_a_simple_xlsx_file_to_array()
    {
        $import = new class
        {
            use Importable;
        };

        $this->assertEquals([
            [
                ['test', 'test'],
                ['test', 'test'],
            ],
        ], $import->toArray('import.xlsx'));
    }

    public function test_can_import_a_simple_xlsx_file_to_collection()
    {
        $import = new class
        {
            use Importable;
        };

        $this->assertEquals(new Collection([
            new Collection([
                new Collection(['test', 'test']),
                new Collection(['test', 'test']),
            ]),
        ]), $import->toCollection('import.xlsx'));
    }

    public function test_can_import_a_simple_xlsx_file_to_collection_with_local_disk()
    {
        $import = new class
        {
            use Importable;
        };

        $contents = file_get_contents(__DIR__ . '/Data/Disks/Local/import.xlsx');

        $this->assertEquals(
            new Collection([
                new Collection([
                    new Collection(['test', 'test']),
                    new Collection(['test', 'test']),
                ]),
            ]),
            $import->toCollection(UploadedFile::fake()->createWithContent('import.xlsx', $contents), 'local')
        );
    }

    public function test_can_import_a_simple_xlsx_file_to_collection_without_import_object()
    {
        $this->assertEquals(new Collection([
            new Collection([
                new Collection(['test', 'test']),
                new Collection(['test', 'test']),
            ]),
        ]), ExcelFacade::toCollection(null, 'import.xlsx'));
    }

    public function test_can_import_a_simple_xlsx_file()
    {
        $import = new class implements ToArray
        {
            /**
             * @param  array  $array
             */
            public function array(array $array)
            {
                Assert::assertEquals([
                    ['test', 'test'],
                    ['test', 'test'],
                ], $array);
            }
        };

        $imported = $this->SUT->import($import, 'import.xlsx');

        $this->assertInstanceOf(Importer::class, $imported);
    }

    public function test_can_import_a_tsv_file()
    {
        $import = new class implements ToArray, WithCustomCsvSettings
        {
            /**
             * @param  array  $array
             */
            public function array(array $array)
            {
                Assert::assertEquals([
                    'tconst',
                    'titleType',
                    'primaryTitle',
                    'originalTitle',
                    'isAdult',
                    'startYear',
                    'endYear',
                    'runtimeMinutes',
                    'genres',
                ], $array[0]);
            }

            /**
             * @return array
             */
            public function getCsvSettings(): array
            {
                return [
                    'delimiter' => "\t",
                ];
            }
        };

        $imported = $this->SUT->import($import, 'import-titles.tsv');

        $this->assertInstanceOf(Importer::class, $imported);
    }

    public function test_can_chain_imports()
    {
        $import1 = new class implements ToArray
        {
            /**
             * @param  array  $array
             */
            public function array(array $array)
            {
                Assert::assertEquals([
                    ['test', 'test'],
                    ['test', 'test'],
                ], $array);
            }
        };

        $import2 = new class implements ToArray
        {
            /**
             * @param  array  $array
             */
            public function array(array $array)
            {
                Assert::assertEquals([
                    ['test', 'test'],
                    ['test', 'test'],
                ], $array);
            }
        };

        $imported = $this->SUT
            ->import($import1, 'import.xlsx')
            ->import($import2, 'import.xlsx');

        $this->assertInstanceOf(Importer::class, $imported);
    }

    public function test_can_import_a_simple_xlsx_file_from_uploaded_file()
    {
        $import = new class implements ToArray
        {
            /**
             * @param  array  $array
             */
            public function array(array $array)
            {
                Assert::assertEquals([
                    ['test', 'test'],
                    ['test', 'test'],
                ], $array);
            }
        };

        $this->SUT->import($import, $this->givenUploadedFile(__DIR__ . '/Data/Disks/Local/import.xlsx'));
    }

    public function test_can_import_a_simple_xlsx_file_from_real_path()
    {
        $import = new class implements ToArray
        {
            /**
             * @param  array  $array
             */
            public function array(array $array)
            {
                Assert::assertEquals([
                    ['test', 'test'],
                    ['test', 'test'],
                ], $array);
            }
        };

        $this->SUT->import($import, __DIR__ . '/Data/Disks/Local/import.xlsx');
    }

    public function test_import_will_throw_error_when_no_reader_type_could_be_detected_when_no_extension()
    {
        $this->expectException(\Maatwebsite\Excel\Exceptions\NoTypeDetectedException::class);

        $import = new class implements ToArray
        {
            /**
             * @param  array  $array
             */
            public function array(array $array)
            {
                Assert::assertEquals([
                    ['test', 'test'],
                    ['test', 'test'],
                ], $array);
            }
        };

        $this->SUT->import($import, UploadedFile::fake()->create('import'));
    }

    public function test_import_will_throw_error_when_no_reader_type_could_be_detected_with_unknown_extension()
    {
        $this->expectException(\Maatwebsite\Excel\Exceptions\NoTypeDetectedException::class);

        $import = new class implements ToArray
        {
            /**
             * @param  array  $array
             */
            public function array(array $array)
            {
                //
            }
        };

        $this->SUT->import($import, 'unknown-reader-type.zip');
    }

    public function test_can_import_without_extension_with_explicit_reader_type()
    {
        $import = new class implements ToArray
        {
            /**
             * @param  array  $array
             */
            public function array(array $array)
            {
                Assert::assertEquals([
                    ['test', 'test'],
                    ['test', 'test'],
                ], $array);
            }
        };

        $this->SUT->import(
            $import,
            $this->givenUploadedFile(__DIR__ . '/Data/Disks/Local/import.xlsx', 'import'),
            null,
            Excel::XLSX
        );
    }
}
