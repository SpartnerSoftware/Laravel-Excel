<?php

namespace Maatwebsite\Excel;

interface Exporter
{
    /**
     * @param  object  $export
     * @param  string|null  $fileName
     * @param  string  $writerType
     * @param  array  $headers
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function download($export, string $fileName, ?string $writerType = null, array $headers = []);

    /**
     * @param  object  $export
     * @param  string  $filePath
     * @param  string|null  $diskName
     * @param  string  $writerType
     * @param  mixed  $diskOptions
     * @return bool
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function store($export, string $filePath, ?string $disk = null, ?string $writerType = null, $diskOptions = []);

    /**
     * @param  object  $export
     * @param  string  $filePath
     * @param  string|null  $disk
     * @param  string  $writerType
     * @param  mixed  $diskOptions
     * @return \Illuminate\Foundation\Bus\PendingDispatch
     */
    public function queue($export, string $filePath, ?string $disk = null, ?string $writerType = null, $diskOptions = []);

    /**
     * @param  object  $export
     * @param  string  $writerType
     * @return string
     */
    public function raw($export, string $writerType);
}
