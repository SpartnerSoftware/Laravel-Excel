<?php

namespace Maatwebsite\Excel\Filters;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class LimitFilter implements IReadFilter
{
    /**
     * @var int
     */
    private $startRow;

    /**
     * @var int
     */
    private $endRow;

    /**
     * @param  int  $startRow
     * @param  int  $limit
     */
    public function __construct(int $startRow, int $limit)
    {
        $this->startRow = $startRow;
        $this->endRow   = $startRow + $limit;
    }

    /**
     * @param  string  $column
     * @param  int  $row
     * @param  string  $worksheetName
     * @return bool
     */
    public function readCell($column, $row, $worksheetName = ''): bool
    {
        return $row >= $this->startRow && $row < $this->endRow;
    }
}
