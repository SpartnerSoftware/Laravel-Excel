<?php

namespace Maatwebsite\Excel\Exceptions;

use InvalidArgumentException;
use Throwable;

class NoFilePathGivenException extends InvalidArgumentException implements LaravelExcelException
{
    /**
     * @param  string  $message
     * @param  int  $code
     * @param  Throwable|null  $previous
     */
    public function __construct(
        $message = 'A filepath needs to be passed.',
        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return NoFilePathGivenException
     */
    public static function import()
    {
        return new static('A filepath or UploadedFile needs to be passed to start the import.');
    }

    /**
     * @return NoFilePathGivenException
     */
    public static function export()
    {
        return new static('A filepath needs to be passed in order to store the export.');
    }
}
