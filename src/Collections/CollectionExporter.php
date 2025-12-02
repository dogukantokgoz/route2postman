<?php

namespace dogukantokgoz\Route2Postman\Collections;

use Illuminate\Support\Facades\Storage;

class CollectionExporter
{
    public function __construct(
        protected Builder $builder,
        protected array $config,
    ) {
    }

    public function formatAsPostmanCollection(array $routes): array
    {
        return $this->builder->buildCollection($routes);
    }

    public function exportToFile(array $collection): string
    {
        $driver = data_get($this->config, 'export.storage_driver');
        $path = data_get($this->config, 'export.output_path');
        $filename = data_get($this->config, 'export.file_name');

        $disk = Storage::build([
            'driver' => $driver,
            'root' => $path,
        ]);

        $contents = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $disk->put($filename, $contents);

        return $disk->path($filename);
    }
}
