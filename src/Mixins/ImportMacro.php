<?php

namespace Maatwebsite\Excel\Mixins;

use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ImportMacro
{
    public function __invoke()
    {
        return function (string $filename, ?string $disk = null, ?string $readerType = null) {
            $import = new class(get_class($this->getModel())) implements ToModel, WithHeadingRow
            {
                use Importable;

                /**
                 * @var string
                 */
                private $model;

                /**
                 * @param  string  $model
                 */
                public function __construct(string $model)
                {
                    $this->model = $model;
                }

                /**
                 * @param  array  $row
                 * @return Model|Model[]|null
                 */
                public function model(array $row)
                {
                    return (new $this->model)->fill($row);
                }
            };

            return $import->import($filename, $disk, $readerType);
        };
    }
}
