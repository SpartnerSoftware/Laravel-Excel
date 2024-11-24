<?php

namespace Maatwebsite\Excel\Concerns;

use Illuminate\Foundation\Bus\PendingDispatch;
use Maatwebsite\Excel\Exceptions\NoFilenameGivenException;
use Maatwebsite\Excel\Exceptions\NoFilePathGivenException;
use Maatwebsite\Excel\Exporter;

trait Exportable
{
    /**
     * @param  string  $fileName
     * @param  string|null  $writerType
     * @param  array  $headers
     * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     *
     * @throws NoFilenameGivenException
     */
    public function download(?string $fileName = null, ?string $writerType = null, ?array $headers = null)
    {
        $headers    = $headers ?? $this->headers ?? [];
        $fileName   = $fileName ?? $this->fileName ?? null;
        $writerType = $writerType ?? $this->writerType ?? null;

        if (null === $fileName) {
            throw new NoFilenameGivenException();
        }

        return $this->getExporter()->download($this, $fileName, $writerType, $headers);
    }

    /**
     * @param  string  $filePath
     * @param  string|null  $disk
     * @param  string|null  $writerType
     * @param  mixed  $diskOptions
     * @return bool|PendingDispatch
     *
     * @throws NoFilePathGivenException
     */
    public function store(?string $filePath = null, ?string $disk = null, ?string $writerType = null, $diskOptions = [])
    {
        $filePath = $filePath ?? $this->filePath ?? null;

        if (null === $filePath) {
            throw NoFilePathGivenException::export();
        }

        return $this->getExporter()->store(
            $this,
            $filePath,
            $disk ?? $this->disk ?? null,
            $writerType ?? $this->writerType ?? null,
            $diskOptions ?: $this->diskOptions ?? []
        );
    }

    /**
     * @param  string|null  $filePath
     * @param  string|null  $disk
     * @param  string|null  $writerType
     * @param  mixed  $diskOptions
     * @return PendingDispatch
     *
     * @throws NoFilePathGivenException
     */
    public function queue(?string $filePath = null, ?string $disk = null, ?string $writerType = null, $diskOptions = [])
    {
        $filePath = $filePath ?? $this->filePath ?? null;

        if (null === $filePath) {
            throw NoFilePathGivenException::export();
        }

        return $this->getExporter()->queue(
            $this,
            $filePath,
            $disk ?? $this->disk ?? null,
            $writerType ?? $this->writerType ?? null,
            $diskOptions ?: $this->diskOptions ?? []
        );
    }

    /**
     * @param  string|null  $writerType
     * @return string
     */
    public function raw($writerType = null)
    {
        $writerType = $writerType ?? $this->writerType ?? null;

        return $this->getExporter()->raw($this, $writerType);
    }

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @throws NoFilenameGivenException
     */
    public function toResponse($request)
    {
        return $this->download();
    }

    /**
     * @return Exporter
     */
    private function getExporter(): Exporter
    {
        return app(Exporter::class);
    }
}
