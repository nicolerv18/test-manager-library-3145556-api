<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\TestCase;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use ReflectionClass;

final class ArchitectureTest extends TestCase
{
    public function test_domain_does_not_import_application(): void
    {
        $domainClasses = $this->getClassesInNamespace('App\\Domain');
        $appClasses = $this->getClassesInNamespace('App\\Application');

        foreach ($domainClasses as $class) {
            $ref = new ReflectionClass($class);
            $file = (new ReflectionClass($class))->getFileName();

            // Read file and check for forbidden imports
            $content = file_get_contents($file);
            $this->assertDoesntMatchPattern('/use App\\\\Application\\\\/', $content, "Domain class $class should not import Application layer");
            $this->assertDoesntMatchPattern('/use App\\\\Adapters\\\\/', $content, "Domain class $class should not import Adapters layer");
        }
    }

    public function test_application_does_not_import_adapters(): void
    {
        $appClasses = $this->getClassesInNamespace('App\\Application');
        $adapterClasses = $this->getClassesInNamespace('App\\Adapters');

        foreach ($appClasses as $class) {
            $ref = new ReflectionClass($class);
            $file = $ref->getFileName();

            $content = file_get_contents($file);
            $this->assertDoesntMatchPattern('/use App\\\\Adapters\\\\/', $content, "Application class $class should not import Adapters layer");
        }
    }

    public function test_bootstrap_has_single_composition_point(): void
    {
        $providerFile = app_path('Providers/PortBindingsServiceProvider.php');
        $this->assertFileExists($providerFile, 'PortBindingsServiceProvider.php should exist');

        $content = file_get_contents($providerFile);
        $this->assertStringContainsString('bind', $content, 'Provider should bind ports to adapters');
        $this->assertStringContainsString('singleton', $content, 'Provider should singleton security adapters');
    }

    /** @return class-string[] */
    private function getClassesInNamespace(string $namespace): array
    {
        $classes = [];
        $dir = str_replace('\\', '/', $namespace) . '/';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path())
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace(app_path() . '/', '', $file->getPathname());
            if (str_starts_with($path, $dir)) {
                $className = $namespace . '\\' . basename($file->getFilename(), '.php');
                if (class_exists($className)) {
                    $classes[] = $className;
                }
            }
        }

        return $classes;
    }
}
