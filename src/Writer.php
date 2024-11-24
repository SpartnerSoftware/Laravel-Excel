<?php

namespace Maatwebsite\Excel;

use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\WithBackgroundColor;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Events\BeforeWriting;
use Maatwebsite\Excel\Factories\WriterFactory;
use Maatwebsite\Excel\Files\RemoteTemporaryFile;
use Maatwebsite\Excel\Files\TemporaryFile;
use Maatwebsite\Excel\Files\TemporaryFileFactory;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** @mixin Spreadsheet */
class Writer
{
    use DelegatedMacroable, HasEventBus;

    /**
     * @var Spreadsheet
     */
    protected $spreadsheet;

    /**
     * @var object
     */
    protected $exportable;

    /**
     * @var TemporaryFileFactory
     */
    protected $temporaryFileFactory;

    /**
     * @param  TemporaryFileFactory  $temporaryFileFactory
     */
    public function __construct(TemporaryFileFactory $temporaryFileFactory)
    {
        $this->temporaryFileFactory = $temporaryFileFactory;

        $this->setDefaultValueBinder();
    }

    /**
     * @param  object  $export
     * @param  string  $writerType
     * @return TemporaryFile
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function export($export, string $writerType): TemporaryFile
    {
        $this->open($export);

        $sheetExports = [$export];
        if ($export instanceof WithMultipleSheets) {
            $sheetExports = $export->sheets();
        }

        foreach ($sheetExports as $sheetExport) {
            $this->addNewSheet()->export($sheetExport);
        }

        return $this->write($export, $this->temporaryFileFactory->makeLocal(null, strtolower($writerType)), $writerType);
    }

    /**
     * @param  object  $export
     * @return $this
     */
    public function open($export)
    {
        $this->exportable = $export;

        if ($export instanceof WithEvents) {
            $this->registerListeners($export->registerEvents());
        }

        $this->exportable  = $export;
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->disconnectWorksheets();

        if ($export instanceof WithCustomValueBinder) {
            Cell::setValueBinder($export);
        }

        $this->handleDocumentProperties($export);

        if ($export instanceof WithBackgroundColor) {
            $defaultStyle    = $this->spreadsheet->getDefaultStyle();
            $backgroundColor = $export->backgroundColor();

            if (is_string($backgroundColor)) {
                $defaultStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($backgroundColor);
            }

            if (is_array($backgroundColor)) {
                $defaultStyle->applyFromArray(['fill' => $backgroundColor]);
            }

            if ($backgroundColor instanceof Color) {
                $defaultStyle->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor($backgroundColor);
            }
        }

        if ($export instanceof WithDefaultStyles) {
            $defaultStyle = $this->spreadsheet->getDefaultStyle();
            $styles       = $export->defaultStyles($defaultStyle);

            if (is_array($styles)) {
                $defaultStyle->applyFromArray($styles);
            }
        }

        $this->raise(new BeforeExport($this, $this->exportable));

        return $this;
    }

    /**
     * @param  TemporaryFile  $tempFile
     * @param  string  $writerType
     * @return Writer
     *
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function reopen(TemporaryFile $tempFile, string $writerType)
    {
        $reader            = IOFactory::createReader($writerType);
        $this->spreadsheet = $reader->load($tempFile->sync()->getLocalPath());

        return $this;
    }

    /**
     * Determine if the application is running in a serverless environment.
     *
     * @return bool
     */
    public function isRunningServerless(): bool
    {
        return isset($_ENV['AWS_LAMBDA_RUNTIME_API']);
    }

    /**
     * @param  object  $export
     * @param  TemporaryFile  $temporaryFile
     * @param  string  $writerType
     * @return TemporaryFile
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function write($export, TemporaryFile $temporaryFile, string $writerType): TemporaryFile
    {
        $this->exportable = $export;

        $this->spreadsheet->setActiveSheetIndex(0);

        $this->raise(new BeforeWriting($this, $this->exportable));

        $writer = WriterFactory::make(
            $writerType,
            $this->spreadsheet,
            $export
        );

        if ($temporaryFile instanceof RemoteTemporaryFile && !$temporaryFile->existsLocally() && !$this->isRunningServerless()) {
            $temporaryFile = resolve(TemporaryFileFactory::class)
                ->makeLocal(Arr::last(explode('/', $temporaryFile->getLocalPath())));
        }

        $writer->save(
            $temporaryFile->getLocalPath()
        );

        if ($temporaryFile instanceof RemoteTemporaryFile) {
            $temporaryFile->updateRemote();
            $temporaryFile->deleteLocalCopy();
        }

        $this->clearListeners();
        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);

        return $temporaryFile;
    }

    /**
     * @param  int|null  $sheetIndex
     * @return Sheet
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function addNewSheet(?int $sheetIndex = null)
    {
        return new Sheet($this->spreadsheet->createSheet($sheetIndex));
    }

    /**
     * @return Spreadsheet
     */
    public function getDelegate()
    {
        return $this->spreadsheet;
    }

    /**
     * @return $this
     */
    public function setDefaultValueBinder()
    {
        Cell::setValueBinder(
            app(config('excel.value_binder.default', DefaultValueBinder::class))
        );

        return $this;
    }

    /**
     * @param  int  $sheetIndex
     * @return Sheet
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getSheetByIndex(int $sheetIndex)
    {
        return new Sheet($this->getDelegate()->getSheet($sheetIndex));
    }

    /**
     * @param  string  $concern
     * @return bool
     */
    public function hasConcern($concern): bool
    {
        return $this->exportable instanceof $concern;
    }

    /**
     * @param  object  $export
     */
    protected function handleDocumentProperties($export)
    {
        $properties = config('excel.exports.properties', []);

        if ($export instanceof WithProperties) {
            $properties = array_merge($properties, $export->properties());
        }

        if ($export instanceof WithTitle) {
            $properties = array_merge($properties, ['title' => $export->title()]);
        }

        $props = $this->spreadsheet->getProperties();

        foreach (array_filter($properties) as $property => $value) {
            switch ($property) {
                case 'title':
                    $props->setTitle($value);
                    break;
                case 'description':
                    $props->setDescription($value);
                    break;
                case 'creator':
                    $props->setCreator($value);
                    break;
                case 'lastModifiedBy':
                    $props->setLastModifiedBy($value);
                    break;
                case 'subject':
                    $props->setSubject($value);
                    break;
                case 'keywords':
                    $props->setKeywords($value);
                    break;
                case 'category':
                    $props->setCategory($value);
                    break;
                case 'manager':
                    $props->setManager($value);
                    break;
                case 'company':
                    $props->setCompany($value);
                    break;
            }
        }
    }
}
