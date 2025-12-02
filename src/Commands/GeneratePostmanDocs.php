<?php

namespace dogukantokgoz\Route2Postman\Commands;

use Illuminate\Console\Command;
use dogukantokgoz\Route2Postman\Contracts\RouteAnalyzerInterface;
use dogukantokgoz\Route2Postman\Collections\CollectionExporter;

class GeneratePostmanDocs extends Command
{
    protected $signature = 'route:export';
    protected $description = 'Export Laravel routes as Postman collection';

    public function handle(
        RouteAnalyzerInterface $analyzer,
        CollectionExporter $exporter,
    ) {
        $routes = $analyzer->analyzeRoutes();
        $collection = $exporter->formatAsPostmanCollection($routes);
        $path = $exporter->exportToFile($collection);

        $this->info("Postman collection generated at: {$path}");
    }
}
