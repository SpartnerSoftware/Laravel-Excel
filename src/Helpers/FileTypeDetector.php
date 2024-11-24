<?php

namespace Maatwebsite\Excel\Helpers;

use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileTypeDetector
{
    /**
     * @param  $filePath
     * @param  string|null  $type
     * @return string|null
     *
     * @throws NoTypeDetectedException
     */
    public static function detect($filePath, ?string $type = null)
    {
        if (null !== $type) {
            return $type;
        }

        if (!$filePath instanceof UploadedFile) {
            $pathInfo  = pathinfo($filePath);
            $extension = $pathInfo['extension'] ?? '';
        } else {
            $extension = $filePath->getClientOriginalExtension();
        }

        if (null === $type && trim($extension) === '') {
            throw new NoTypeDetectedException();
        }

        return config('excel.extension_detector.' . strtolower($extension));
    }

    /**
     * @param  string  $filePath
     * @param  string|null  $type
     * @return string
     *
     * @throws NoTypeDetectedException
     */
    public static function detectStrict(string $filePath, ?string $type = null): string
    {
        $type = static::detect($filePath, $type);

        if (!$type) {
            throw new NoTypeDetectedException();
        }

        return $type;
    }
}
