<?php

namespace Maatwebsite\Excel;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\FromIterator;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnLimit;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithFormatData;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProgressBar;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Exceptions\ConcernConflictException;
use Maatwebsite\Excel\Exceptions\RowSkippedException;
use Maatwebsite\Excel\Exceptions\SheetNotFoundException;
use Maatwebsite\Excel\Files\TemporaryFileFactory;
use Maatwebsite\Excel\Helpers\ArrayHelper;
use Maatwebsite\Excel\Helpers\CellHelper;
use Maatwebsite\Excel\Imports\EndRowFinder;
use Maatwebsite\Excel\Imports\HeadingRowExtractor;
use Maatwebsite\Excel\Imports\ModelImporter;
use Maatwebsite\Excel\Validators\RowValidator;
use PhpOffice\PhpSpreadsheet\Cell\Cell as SpreadsheetCell;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Html;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** @mixin Worksheet */
class Sheet
{
    use DelegatedMacroable, HasEventBus;

    /**
     * @var int
     */
    protected $chunkSize;

    /**
     * @var TemporaryFileFactory
     */
    protected $temporaryFileFactory;

    /**
     * @var object
     */
    protected $exportable;

    /**
     * @var Worksheet
     */
    private $worksheet;

    /**
     * @param  Worksheet  $worksheet
     */
    public function __construct(Worksheet $worksheet)
    {
        $this->worksheet            = $worksheet;
        $this->chunkSize            = config('excel.exports.chunk_size', 100);
        $this->temporaryFileFactory = app(TemporaryFileFactory::class);
    }

    /**
     * @param  Spreadsheet  $spreadsheet
     * @param  string|int  $index
     * @return Sheet
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws SheetNotFoundException
     */
    public static function make(Spreadsheet $spreadsheet, $index)
    {
        if (is_numeric($index)) {
            return self::byIndex($spreadsheet, $index);
        }

        return self::byName($spreadsheet, $index);
    }

    /**
     * @param  Spreadsheet  $spreadsheet
     * @param  int  $index
     * @return Sheet
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws SheetNotFoundException
     */
    public static function byIndex(Spreadsheet $spreadsheet, int $index): Sheet
    {
        if (!isset($spreadsheet->getAllSheets()[$index])) {
            throw SheetNotFoundException::byIndex($index, $spreadsheet->getSheetCount());
        }

        return new static($spreadsheet->getSheet($index));
    }

    /**
     * @param  Spreadsheet  $spreadsheet
     * @param  string  $name
     * @return Sheet
     *
     * @throws SheetNotFoundException
     */
    public static function byName(Spreadsheet $spreadsheet, string $name): Sheet
    {
        if (!$spreadsheet->sheetNameExists($name)) {
            throw SheetNotFoundException::byName($name);
        }

        return new static($spreadsheet->getSheetByName($name));
    }

    /**
     * @param  object  $sheetExport
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function open($sheetExport)
    {
        $this->exportable = $sheetExport;

        if ($sheetExport instanceof WithCustomValueBinder) {
            SpreadsheetCell::setValueBinder($sheetExport);
        }

        if ($sheetExport instanceof WithEvents) {
            $this->registerListeners($sheetExport->registerEvents());
        }

        $this->raise(new BeforeSheet($this, $this->exportable));

        if ($sheetExport instanceof WithTitle) {
            $title = $sheetExport->title();

            $title = str_replace(['*', ':', '/', '\\', '?', '[', ']'], '', $title);
            if (StringHelper::countCharacters($title) > Worksheet::SHEET_TITLE_MAXIMUM_LENGTH) {
                $title = StringHelper::substring($title, 0, Worksheet::SHEET_TITLE_MAXIMUM_LENGTH);
            }

            $this->worksheet->setTitle($title);
        }

        if (($sheetExport instanceof FromQuery || $sheetExport instanceof FromCollection || $sheetExport instanceof FromArray) && $sheetExport instanceof FromView) {
            throw ConcernConflictException::queryOrCollectionAndView();
        }

        if (!$sheetExport instanceof FromView && $sheetExport instanceof WithHeadings) {
            if ($sheetExport instanceof WithCustomStartCell) {
                $startCell = $sheetExport->startCell();
            }

            $this->append(
                ArrayHelper::ensureMultipleRows($sheetExport->headings()),
                $startCell ?? null,
                $this->hasStrictNullComparison($sheetExport)
            );
        }
    }

    /**
     * @param  object  $sheetExport
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function export($sheetExport)
    {
        $this->open($sheetExport);

        if ($sheetExport instanceof FromView) {
            $this->fromView($sheetExport);
        } else {
            if ($sheetExport instanceof FromQuery) {
                $this->fromQuery($sheetExport, $this->worksheet);
            }

            if ($sheetExport instanceof FromCollection) {
                $this->fromCollection($sheetExport);
            }

            if ($sheetExport instanceof FromArray) {
                $this->fromArray($sheetExport);
            }

            if ($sheetExport instanceof FromIterator) {
                $this->fromIterator($sheetExport);
            }

            if ($sheetExport instanceof FromGenerator) {
                $this->fromGenerator($sheetExport);
            }
        }

        $this->close($sheetExport);
    }

    /**
     * @param  object  $import
     * @param  int  $startRow
     */
    public function import($import, int $startRow = 1)
    {
        if ($import instanceof WithEvents) {
            $this->registerListeners($import->registerEvents());
        }

        $this->raise(new BeforeSheet($this, $import));

        if ($import instanceof WithProgressBar && !$import instanceof WithChunkReading) {
            $import->getConsoleOutput()->progressStart($this->worksheet->getHighestRow());
        }

        $calculatesFormulas = $import instanceof WithCalculatedFormulas;
        $formatData         = $import instanceof WithFormatData;

        if ($import instanceof WithMappedCells) {
            app(MappedReader::class)->map($import, $this->worksheet);
        } else {
            if ($import instanceof ToModel) {
                app(ModelImporter::class)->import($this->worksheet, $import, $startRow);
            }

            if ($import instanceof ToCollection) {
                $rows = $this->toCollection($import, $startRow, null, $calculatesFormulas, $formatData);

                if ($import instanceof WithValidation) {
                    $rows = $this->validated($import, $startRow, $rows);
                }

                $import->collection($rows);
            }

            if ($import instanceof ToArray) {
                $rows = $this->toArray($import, $startRow, null, $calculatesFormulas, $formatData);

                if ($import instanceof WithValidation) {
                    $rows = $this->validated($import, $startRow, $rows);
                }

                $import->array($rows);
            }
        }

        if ($import instanceof OnEachRow) {
            $headingRow          = HeadingRowExtractor::extract($this->worksheet, $import);
            $headerIsGrouped     = HeadingRowExtractor::extractGrouping($headingRow, $import);
            $endColumn           = $import instanceof WithColumnLimit ? $import->endColumn() : null;
            $preparationCallback = $this->getPreparationCallback($import);

            foreach ($this->worksheet->getRowIterator()->resetStart($startRow ?? 1) as $row) {
                $sheetRow = new Row($row, $headingRow, $headerIsGrouped);

                if ($import instanceof WithValidation) {
                    $sheetRow->setPreparationCallback($preparationCallback);
                }

                $rowArray                    = $sheetRow->toArray(null, $import instanceof WithCalculatedFormulas, $import instanceof WithFormatData, $endColumn);
                $rowIsEmptyAccordingToImport = $import instanceof SkipsEmptyRows && method_exists($import, 'isEmptyWhen') && $import->isEmptyWhen($rowArray);
                if (!$import instanceof SkipsEmptyRows || ($import instanceof SkipsEmptyRows && (!$rowIsEmptyAccordingToImport && !$sheetRow->isEmpty($calculatesFormulas)))) {
                    if ($import instanceof WithValidation) {
                        $toValidate = [$sheetRow->getIndex() => $rowArray];

                        try {
                            app(RowValidator::class)->validate($toValidate, $import);
                            $import->onRow($sheetRow);
                        } catch (RowSkippedException $e) {
                        }
                    } else {
                        $import->onRow($sheetRow);
                    }
                }

                if ($import instanceof WithProgressBar) {
                    $import->getConsoleOutput()->progressAdvance();
                }
            }
        }

        $this->raise(new AfterSheet($this, $import));

        if ($import instanceof WithProgressBar && !$import instanceof WithChunkReading) {
            $import->getConsoleOutput()->progressFinish();
        }
    }

    /**
     * @param  object  $import
     * @param  int|null  $startRow
     * @param  null  $nullValue
     * @param  bool  $calculateFormulas
     * @param  bool  $formatData
     * @return array
     */
    public function toArray($import, int $startRow = null, $nullValue = null, $calculateFormulas = false, $formatData = false)
    {
        if ($startRow > $this->worksheet->getHighestRow()) {
            return [];
        }

        $endRow          = EndRowFinder::find($import, $startRow, $this->worksheet->getHighestRow());
        $headingRow      = HeadingRowExtractor::extract($this->worksheet, $import);
        $headerIsGrouped = HeadingRowExtractor::extractGrouping($headingRow, $import);
        $endColumn       = $import instanceof WithColumnLimit ? $import->endColumn() : null;

        $rows = [];
        foreach ($this->worksheet->getRowIterator($startRow, $endRow) as $index => $row) {
            $row = new Row($row, $headingRow, $headerIsGrouped);

            if ($import instanceof SkipsEmptyRows && $row->isEmpty($calculateFormulas, $endColumn)) {
                continue;
            }

            $row = $row->toArray($nullValue, $calculateFormulas, $formatData, $endColumn);

            if ($import && method_exists($import, 'isEmptyWhen') && $import->isEmptyWhen($row)) {
                continue;
            }

            if ($import instanceof WithMapping) {
                $row = $import->map($row);
            }

            if ($import instanceof WithValidation && method_exists($import, 'prepareForValidation')) {
                $row = $import->prepareForValidation($row, $index);
            }

            $rows[] = $row;

            if ($import instanceof WithProgressBar) {
                $import->getConsoleOutput()->progressAdvance();
            }
        }

        return $rows;
    }

    /**
     * @param  object  $import
     * @param  int|null  $startRow
     * @param  null  $nullValue
     * @param  bool  $calculateFormulas
     * @param  bool  $formatData
     * @return Collection
     */
    public function toCollection($import, int $startRow = null, $nullValue = null, $calculateFormulas = false, $formatData = false): Collection
    {
        $rows = $this->toArray($import, $startRow, $nullValue, $calculateFormulas, $formatData);

        return new Collection(array_map(function (array $row) {
            return new Collection($row);
        }, $rows));
    }

    /**
     * @param  object  $sheetExport
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function close($sheetExport)
    {
        if ($sheetExport instanceof WithCharts) {
            $this->addCharts($sheetExport->charts());
        }

        if ($sheetExport instanceof WithDrawings) {
            $this->addDrawings($sheetExport->drawings());
        }

        $this->exportable = $sheetExport;

        if ($sheetExport instanceof WithColumnFormatting) {
            foreach ($sheetExport->columnFormats() as $column => $format) {
                $this->formatColumn($column, $format);
            }
        }

        if ($sheetExport instanceof ShouldAutoSize) {
            $this->autoSize();
        }

        if ($sheetExport instanceof WithColumnWidths) {
            foreach ($sheetExport->columnWidths() as $column => $width) {
                $this->worksheet->getColumnDimension($column)->setAutoSize(false)->setWidth($width);
            }
        }

        if ($sheetExport instanceof WithStyles) {
            $styles = $sheetExport->styles($this->worksheet);
            if (is_array($styles)) {
                foreach ($styles as $coordinate => $coordinateStyles) {
                    if (is_numeric($coordinate)) {
                        $coordinate = 'A' . $coordinate . ':' . $this->worksheet->getHighestColumn($coordinate) . $coordinate;
                    }

                    $this->worksheet->getStyle($coordinate)->applyFromArray($coordinateStyles);
                }
            }
        }

        $this->raise(new AfterSheet($this, $this->exportable));

        $this->clearListeners();
    }

    /**
     * @param  FromView  $sheetExport
     * @param  int|null  $sheetIndex
     *
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function fromView(FromView $sheetExport, $sheetIndex = null)
    {
        $temporaryFile = $this->temporaryFileFactory->makeLocal(null, 'html');
        $temporaryFile->put($sheetExport->view()->render());

        $spreadsheet = $this->worksheet->getParent();

        /** @var Html $reader */
        $reader = IOFactory::createReader('Html');

        // If no sheetIndex given, insert content into the last sheet
        $reader->setSheetIndex($sheetIndex ?? $spreadsheet->getSheetCount() - 1);
        $reader->loadIntoExisting($temporaryFile->getLocalPath(), $spreadsheet);

        $temporaryFile->delete();
    }

    /**
     * @param  FromQuery  $sheetExport
     * @param  Worksheet  $worksheet
     */
    public function fromQuery(FromQuery $sheetExport, Worksheet $worksheet)
    {
        if ($sheetExport->query() instanceof \Laravel\Scout\Builder) {
            $this->fromScout($sheetExport, $worksheet);

            return;
        }

        $sheetExport->query()->chunk($this->getChunkSize($sheetExport), function ($chunk) use ($sheetExport) {
            $this->appendRows($chunk, $sheetExport);
        });
    }

    /**
     * @param  FromQuery  $sheetExport
     * @param  Worksheet  $worksheet
     */
    public function fromScout(FromQuery $sheetExport, Worksheet $worksheet)
    {
        $scout     = $sheetExport->query();
        $chunkSize = $this->getChunkSize($sheetExport);

        $chunk = $scout->paginate($chunkSize);
        // Append first page
        $this->appendRows($chunk->items(), $sheetExport);

        // Append rest of pages
        for ($page = 2; $page <= $chunk->lastPage(); $page++) {
            $this->appendRows($scout->paginate($chunkSize, 'page', $page)->items(), $sheetExport);
        }
    }

    /**
     * @param  FromCollection  $sheetExport
     */
    public function fromCollection(FromCollection $sheetExport)
    {
        $this->appendRows($sheetExport->collection()->all(), $sheetExport);
    }

    /**
     * @param  FromArray  $sheetExport
     */
    public function fromArray(FromArray $sheetExport)
    {
        $this->appendRows($sheetExport->array(), $sheetExport);
    }

    /**
     * @param  FromIterator  $sheetExport
     */
    public function fromIterator(FromIterator $sheetExport)
    {
        $iterator = class_exists(LazyCollection::class) ? new LazyCollection(function () use ($sheetExport) {
            foreach ($sheetExport->iterator() as $row) {
                yield $row;
            }
        }) : $sheetExport->iterator();

        $this->appendRows($iterator, $sheetExport);
    }

    /**
     * @param  FromGenerator  $sheetExport
     */
    public function fromGenerator(FromGenerator $sheetExport)
    {
        $generator = class_exists(LazyCollection::class) ? new LazyCollection(function () use ($sheetExport) {
            foreach ($sheetExport->generator() as $row) {
                yield $row;
            }
        }) : $sheetExport->generator();

        $this->appendRows($generator, $sheetExport);
    }

    /**
     * @param  array  $rows
     * @param  string|null  $startCell
     * @param  bool  $strictNullComparison
     */
    public function append(array $rows, string $startCell = null, bool $strictNullComparison = false)
    {
        if (!$startCell) {
            $startCell = 'A1';
        }

        if ($this->hasRows()) {
            $startCell = CellHelper::getColumnFromCoordinate($startCell) . ($this->worksheet->getHighestRow() + 1);
        }

        $this->worksheet->fromArray($rows, null, $startCell, $strictNullComparison);
    }

    public function autoSize()
    {
        foreach ($this->buildColumnRange('A', $this->worksheet->getHighestDataColumn()) as $col) {
            $dimension = $this->worksheet->getColumnDimension($col);

            // Only auto-size columns that have not have an explicit width.
            if ($dimension->getWidth() == -1) {
                $dimension->setAutoSize(true);
            }
        }
    }

    /**
     * @param  string  $column
     * @param  string  $format
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function formatColumn(string $column, string $format)
    {
        // If the column is a range, we wouldn't need to calculate the range.
        if (stripos($column, ':') !== false) {
            $this->worksheet
                ->getStyle($column)
                ->getNumberFormat()
                ->setFormatCode($format);
        } else {
            $this->worksheet
                ->getStyle($column . '1:' . $column . $this->worksheet->getHighestRow())
                ->getNumberFormat()
                ->setFormatCode($format);
        }
    }

    /**
     * @param  int  $chunkSize
     * @return Sheet
     */
    public function chunkSize(int $chunkSize)
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    /**
     * @return Worksheet
     */
    public function getDelegate()
    {
        return $this->worksheet;
    }

    /**
     * @param  Chart|Chart[]  $charts
     */
    public function addCharts($charts)
    {
        $charts = \is_array($charts) ? $charts : [$charts];

        foreach ($charts as $chart) {
            $this->worksheet->addChart($chart);
        }
    }

    /**
     * @param  BaseDrawing|BaseDrawing[]  $drawings
     */
    public function addDrawings($drawings)
    {
        $drawings = \is_array($drawings) ? $drawings : [$drawings];

        foreach ($drawings as $drawing) {
            $drawing->setWorksheet($this->worksheet);
        }
    }

    /**
     * @param  string  $concern
     * @return string
     */
    public function hasConcern(string $concern): string
    {
        return $this->exportable instanceof $concern;
    }

    /**
     * @param  iterable  $rows
     * @param  object  $sheetExport
     */
    public function appendRows($rows, $sheetExport)
    {
        if (method_exists($sheetExport, 'prepareRows')) {
            $rows = $sheetExport->prepareRows($rows);
        }

        $rows = $rows instanceof LazyCollection ? $rows : new Collection($rows);

        $rows->flatMap(function ($row) use ($sheetExport) {
            if ($sheetExport instanceof WithMapping) {
                $row = $sheetExport->map($row);
            }

            if ($sheetExport instanceof WithCustomValueBinder) {
                SpreadsheetCell::setValueBinder($sheetExport);
            }

            return ArrayHelper::ensureMultipleRows(
                static::mapArraybleRow($row)
            );
        })->chunk(1000)->each(function ($rows) use ($sheetExport) {
            $this->append(
                $rows->toArray(),
                $sheetExport instanceof WithCustomStartCell ? $sheetExport->startCell() : null,
                $this->hasStrictNullComparison($sheetExport)
            );
        });
    }

    /**
     * @param  mixed  $row
     * @return array
     */
    public static function mapArraybleRow($row): array
    {
        // When dealing with eloquent models, we'll skip the relations
        // as we won't be able to display them anyway.
        if (is_object($row) && method_exists($row, 'attributesToArray')) {
            return $row->attributesToArray();
        }

        if ($row instanceof Arrayable) {
            return $row->toArray();
        }

        // Convert StdObjects to arrays
        if (is_object($row)) {
            return json_decode(json_encode($row), true);
        }

        return $row;
    }

    /**
     * @param  $sheetImport
     * @return int
     */
    public function getStartRow($sheetImport): int
    {
        return HeadingRowExtractor::determineStartRow($sheetImport);
    }

    /**
     * Disconnect the sheet.
     */
    public function disconnect()
    {
        $this->worksheet->disconnectCells();
        unset($this->worksheet);
    }

    /**
     * @return Collection|array
     */
    protected function validated(WithValidation $import, int $startRow, $rows)
    {
        $toValidate = (new Collection($rows))->mapWithKeys(function ($row, $index) use ($startRow) {
            return [($startRow + $index) => $row];
        });

        try {
            app(RowValidator::class)->validate($toValidate->toArray(), $import);
        } catch (RowSkippedException $e) {
            foreach ($e->skippedRows() as $row) {
                unset($rows[$row - $startRow]);
            }
        }

        return $rows;
    }

    /**
     * @param  string  $lower
     * @param  string  $upper
     * @return \Generator
     */
    protected function buildColumnRange(string $lower, string $upper)
    {
        $upper++;
        for ($i = $lower; $i !== $upper; $i++) {
            yield $i;
        }
    }

    /**
     * @return bool
     */
    private function hasRows(): bool
    {
        $startCell = 'A1';
        if ($this->exportable instanceof WithCustomStartCell) {
            $startCell = $this->exportable->startCell();
        }

        return $this->worksheet->cellExists($startCell);
    }

    /**
     * @param  object  $sheetExport
     * @return bool
     */
    private function hasStrictNullComparison($sheetExport): bool
    {
        if ($sheetExport instanceof WithStrictNullComparison) {
            return true;
        }

        return config('excel.exports.strict_null_comparison', false);
    }

    /**
     * @param  object|WithCustomChunkSize  $export
     * @return int
     */
    private function getChunkSize($export): int
    {
        if ($export instanceof WithCustomChunkSize) {
            return $export->chunkSize();
        }

        return $this->chunkSize;
    }

    /**
     * @param  object|WithValidation  $import
     * @return Closure|null
     */
    private function getPreparationCallback($import)
    {
        if (!$import instanceof WithValidation || !method_exists($import, 'prepareForValidation')) {
            return null;
        }

        return function (array $data, int $index) use ($import) {
            return $import->prepareForValidation($data, $index);
        };
    }
}
