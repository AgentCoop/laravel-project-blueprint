<?php

namespace App\Component\Package;

use App\Services\Base\PhpParser;
use App\Services\Filesystem as Filesystem;

use App\Console\Commands\Support\PackageBuilder;

class Manager
{
    const SERVICE_PROVIDERS_DUMP_FILENAME = 'framework/cache/service-providers.php';

    const CORE_MODULE_NAME = 'phpcrystal';

    const LOCAL_ENV = 'local';
    const PRODUCTION_ENV = 'prod';

    private $modules = [];

    /**
     * @return void
     */
    private function scanModules()
    {
        Filesystem\Finder::findByFilename(base_path() . '/modules', 'manifest.php', function($manifest) {
            $this->modules[] = new Module\Module(Module\Manifest::createFromFile($manifest), dirname($manifest));
        })
            ->setMaxDepth(1)
            ->run();
    }

    /**
     * @return void
     */
    private function dumpRoutingMap()
    {
        $timestamp = date('Y-m-d h:i:s');
        $mapContent = <<<DOC
<?php
//
//  Auto-generated on $timestamp, DO NOT modify this file     
//

DOC;
        foreach ($this->getModules(true) as $module) {
            $manifest = $module->getManifest();
            $subdomain = $manifest->getRouterSubDomain();
            $prefix = $manifest->getRouterUriPrefix();
            $record = sprintf('Route::middleware(%s)',
                PhpParser::toPhpArray($manifest->getRouterMiddlewares()));

            if ($subdomain) {
                $record .= "->domain('$subdomain')";
            }

            if ($prefix) {
                $record .= "->prefix('$prefix')";
            }

            $record .= sprintf('->group(function() { require %s; })',
                sprintf('base_path(\'routes/%s\')', basename($module->getRoutesDumpFilename())));

            $record .= ";\n";
            $mapContent .= $record;
        }

        file_put_contents(base_path('routes/map.php'), $mapContent);
    }

    /**
     *
    */
    public function __construct()
    {
        clearstatcache();

        // Find all modules in ./modules dir
        $this->scanModules();

        // Add the core module
        $coreModule = new Module\Module(Module\Manifest::createFromFile(app_path('manifest.php')),
            app_path(), self::CORE_MODULE_NAME);

        $this->addModule($coreModule);
    }

    /**
     * @return \App\Component\Package\Module\Module
     *
     * @throws \RuntimeException
    */
    public function getModuleByName($name)
    {
        foreach ($this->getModules() as $module) {
            if ($name == $module->getName()) {
                return $module;
            }
        }

        throw new \RuntimeException(sprintf('Failed to find package module %s', $name));
    }

    private function packageControllers($env)
    {
        foreach ($this->getModules(true) as $module) {
            $module->buildControllers($env);
        }

        $this->dumpRoutingMap();
    }

    private function packageServices($env)
    {
        $providersDump = storage_path(self::SERVICE_PROVIDERS_DUMP_FILENAME);
        Filesystem\Aux::phpAutogenerated($providersDump);

        foreach ($this->getModules() as $module) {
            $module->buildServices($env);
        }

        Filesystem\Aux::append($providersDump, Module\Module::generateTaggedServices());
    }

    private function packageAll($env)
    {
        $this->packageControllers($env);
        $this->packageServices($env);
    }

    /**
     *
     */
    public function build($env = self::LOCAL_ENV, $target = null) : void
    {
        switch ($target) {
            case PackageBuilder::TARGET_CONTROLLERS:
                $this->packageControllers($env);
                break;
            case PackageBuilder::TARGET_SERVICES:
                $this->packageServices($env);
                break;
            default:
                $this->packageAll($env);
        }
    }

    /**
     * @return array
     */
    public function getModules($appOnly = false) : array
    {
        if ($appOnly) {
            $appModules = [];

            foreach ($this->modules as $module) {
                if ($module->getName() == self::CORE_MODULE_NAME) {
                    continue;
                }

                $appModules[] = $module;
            }

            return $appModules;
        } else {
            return $this->modules;
        }
    }

    /**
     * @return $this
    */
    public function addModule(Module\Module $newModule) : self
    {
        foreach ($this->modules as $module) {
            if ($module->getName() == $newModule->getName()) {
                throw new \RuntimeException(sprintf('A module name must be unique, failed to load %s module',
                    $newModule->getName()));
            }
        }

        $this->modules[] = $newModule;

        return $this;
    }
}