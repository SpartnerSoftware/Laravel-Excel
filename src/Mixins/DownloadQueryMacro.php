<?php

namespace Maatwebsite\Excel\Mixins;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Sheet;

class DownloadQueryMacro
{
    public function __invoke()
    {
        return function (string $fileName, ?string $writerType = null, $withHeadings = false) {
            $export = new class($this, $withHeadings) implements FromQuery, WithHeadings
            {
                use Exportable;

                /**
                 * @var bool
                 */
                private $withHeadings;

                /**
                 * @var Builder
                 */
                private $query;

                /**
                 * @param  $query
                 * @param  bool  $withHeadings
                 */
                public function __construct($query, bool $withHeadings = false)
                {
                    $this->query        = $query;
                    $this->withHeadings = $withHeadings;
                }

                /**
                 * @return Builder
                 */
                public function query()
                {
                    return $this->query;
                }

                /**
                 * @return array
                 */
                public function headings(): array
                {
                    if (!$this->withHeadings) {
                        return [];
                    }

                    $firstRow = (clone $this->query)->first();

                    if ($firstRow) {
                        return array_keys(Sheet::mapArraybleRow($firstRow));
                    }

                    return [];
                }
            };

            return $export->download($fileName, $writerType);
        };
    }
}
