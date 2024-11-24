<?php

namespace Maatwebsite\Excel\Mixins;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Sheet;

class DownloadCollectionMixin
{
    /**
     * @return callable
     */
    public function downloadExcel()
    {
        return function (string $fileName, ?string $writerType = null, $withHeadings = false, array $responseHeaders = []) {
            $export = new class($this, $withHeadings) implements FromCollection, WithHeadings
            {
                use Exportable;

                /**
                 * @var bool
                 */
                private $withHeadings;

                /**
                 * @var Collection
                 */
                private $collection;

                /**
                 * @param  Collection  $collection
                 * @param  bool  $withHeading
                 */
                public function __construct(Collection $collection, bool $withHeading = false)
                {
                    $this->collection   = $collection->toBase();
                    $this->withHeadings = $withHeading;
                }

                /**
                 * @return Collection
                 */
                public function collection()
                {
                    return $this->collection;
                }

                /**
                 * @return array
                 */
                public function headings(): array
                {
                    if (!$this->withHeadings) {
                        return [];
                    }

                    $firstRow = $this->collection->first();

                    if ($firstRow instanceof Arrayable || \is_object($firstRow)) {
                        return array_keys(Sheet::mapArraybleRow($firstRow));
                    }

                    return $this->collection->collapse()->keys()->all();
                }
            };

            return $export->download($fileName, $writerType, $responseHeaders);
        };
    }
}
