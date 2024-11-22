<?php

namespace Maatwebsite\Excel;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

interface Importer
{
    /**
     * @param  object  $import
     * @param  string|\Symfony\Component\HttpFoundation\File\UploadedFile  $filePath
     * @param  string|null  $disk
     * @param  string|null  $readerType
     * @return Reader|\Illuminate\Foundation\Bus\PendingDispatch
     */
    public function import($import, $filePath, ?string $disk = null, ?string $readerType = null);

    /**
     * @param  object  $import
     * @param  string|\Symfony\Component\HttpFoundation\File\UploadedFile  $filePath
     * @param  string|null  $disk
     * @param  string|null  $readerType
     * @return array
     */
    public function toArray($import, $filePath, ?string $disk = null, ?string $readerType = null): array;

    /**
     * @param  object  $import
     * @param  string|\Symfony\Component\HttpFoundation\File\UploadedFile  $filePath
     * @param  string|null  $disk
     * @param  string|null  $readerType
     * @return Collection
     */
    public function toCollection($import, $filePath, ?string $disk = null, ?string $readerType = null): Collection;

    /**
     * @param  ShouldQueue  $import
     * @param  string|\Symfony\Component\HttpFoundation\File\UploadedFile  $filePath
     * @param  string|null  $disk
     * @param  string  $readerType
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public function queueImport(ShouldQueue $import, $filePath, ?string $disk = null, ?string $readerType = null);
}
