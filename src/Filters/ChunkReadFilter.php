<?php

namespace Maatwebsite\Excel\Filters;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class ChunkReadFilter implements IReadFilter
{
    /**
     * @var int
     */
    private $headingRow;

    /**
     * @var int
     */
    private $startRow;

    /**
     * @var int
     */
    private $endRow;

    /**
     * @var string
     */
    private $worksheetName;

    /**
     * @param  int  $headingRow
     * @param  int  $startRow
     * @param  int  $chunkSize
     * @param  string  $worksheetName
     */
    public function __construct(int $headingRow, int $startRow, int $chunkSize, string $worksheetName)
    {
        $this->headingRow    = $headingRow;
        $this->startRow      = $startRow;
        $this->endRow        = $startRow + $chunkSize;
        $this->worksheetName = $worksheetName;
    }

    /**
     * @param  string  $column
     * @param  int  $row
     * @param  string  $worksheetName
     * @return bool
     */
    public function readCell($column, $row, $worksheetName = ''): bool
    {
        //  Only read the heading row, and the rows that are configured in $this->_startRow and $this->_endRow
        return ($worksheetName === $this->worksheetName || $worksheetName === '')
            && ($row === $this->headingRow || ($row >= $this->startRow && $row < $this->endRow));
    }
}
