<?php

namespace Maatwebsite\Excel\Files;

use Illuminate\Contracts\Filesystem\Factory;

class Filesystem
{
    /**
     * @var Factory
     */
    private $filesystem;

    /**
     * @param  Factory  $filesystem
     */
    public function __construct(Factory $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param  string|null  $disk
     * @param  array  $diskOptions
     * @return Disk
     */
    public function disk(?string $disk = null, array $diskOptions = []): Disk
    {
        return new Disk(
            $this->filesystem->disk($disk),
            $disk,
            $diskOptions
        );
    }
}
