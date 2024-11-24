<?php

namespace Maatwebsite\Excel\Files;

use Illuminate\Contracts\Filesystem\Filesystem as IlluminateFilesystem;

/**
 * @method bool get(string $filename)
 * @method resource readStream(string $filename)
 * @method bool delete(string $filename)
 * @method bool exists(string $filename)
 */
class Disk
{
    /**
     * @var IlluminateFilesystem
     */
    protected $disk;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var array
     */
    protected $diskOptions;

    /**
     * @param  IlluminateFilesystem  $disk
     * @param  string|null  $name
     * @param  array  $diskOptions
     */
    public function __construct(IlluminateFilesystem $disk, ?string $name = null, array $diskOptions = [])
    {
        $this->disk        = $disk;
        $this->name        = $name;
        $this->diskOptions = $diskOptions;
    }

    /**
     * @param  string  $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->disk->{$name}(...$arguments);
    }

    /**
     * @param  string  $destination
     * @param  string|resource  $contents
     * @return bool
     */
    public function put(string $destination, $contents): bool
    {
        return $this->disk->put($destination, $contents, $this->diskOptions);
    }

    /**
     * @param  TemporaryFile  $source
     * @param  string  $destination
     * @return bool
     */
    public function copy(TemporaryFile $source, string $destination): bool
    {
        $readStream = $source->readStream();

        if (realpath($destination)) {
            $tempStream = fopen($destination, 'rb+');
            $success    = stream_copy_to_stream($readStream, $tempStream) !== false;

            if (is_resource($tempStream)) {
                fclose($tempStream);
            }
        } else {
            $success = $this->put($destination, $readStream);
        }

        if (is_resource($readStream)) {
            fclose($readStream);
        }

        return $success;
    }

    /**
     * @param  string  $filename
     */
    public function touch(string $filename)
    {
        $this->disk->put($filename, '', $this->diskOptions);
    }
}
