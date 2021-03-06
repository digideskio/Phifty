<?php
namespace Phifty\Bundle;
use Phifty\Kernel;
use Universal\ClassLoader\Psr4ClassLoader;
use ReflectionObject;
use ReflectionClass;

class BundleLoader
{
    protected $kernel;

    protected $lookupDirectories = [];

    public function __construct(Kernel $kernel, array $lookupDirectories = [])
    {
        $this->lookupDirectories = $lookupDirectories;
    }

    public function getAutoloadConfig($name)
    {
        $class = "$name\\$name";

        // if we could find the class, we don't need custom class loader for this.
        if (class_exists($class, true)) {
            $refl = new ReflectionClass($class);
            $classPath = $refl->getFileName();
            $bundleDir = dirname($classPath);
            $composerFile = $bundleDir . DIRECTORY_SEPARATOR . 'composer.json';
            if (file_exists($composerFile)) {
                $composerConfig = json_decode(file_get_contents($composerFile), true);
                if (isset($composerConfig['autoload']['psr-4'])) {
                    $config = [];
                    foreach ($composerConfig['autoload']['psr-4'] as $prefix => $subpath) {
                        $config[$prefix] = $bundleDir . DIRECTORY_SEPARATOR . ltrim($subpath, DIRECTORY_SEPARATOR);
                    }
                    return $config;
                }
                return false;
            }
        }
        if ($classPath = $this->findBundleClass($name)) {
            $bundleDir = dirname($classPath);
            return [ "$name\\" => realpath($bundleDir) . DIRECTORY_SEPARATOR ];
        }
    }

    /**
     * Load bundle by bundle name
     *
     * @param string $name
     * @param array|ConfigKit\Accessor $config
     */
    public function load($name, $config)
    {
        $class = $this->loadBundleClass($name);
        return $class::getInstance($this->kernel, $config);
    }

    /**
     * Find the bundle directory location based on the lookup paths
     *
     * @param string $name
     * @return string $className
     */
    public function findBundleClass($name)
    {
        $subpath = $name . DIRECTORY_SEPARATOR . $name . '.php';
        foreach ($this->lookupDirectories as $dir) {
            $classPath = $dir . DIRECTORY_SEPARATOR . $subpath;
            if (file_exists($classPath)) {
                return realpath($classPath);
            }
        }
        return false;
    }

    /**
     * Require bundle class file and return the class name
     *
     * @param string $name
     */
    public function loadBundleClass($name)
    {
        $class = "$name\\$name";
        if (class_exists($class,true)) {
            return $class;
        }
        if ($classPath = $this->findBundleClass($name)) {
            require $classPath;
            return $class;
        }
    }

    /*
    $config = $kernel->config->get('framework','Bundles');
    $manager = new BundleManager($kernel);
    if ($config) {
        foreach ($config as $bundleName => $bundleConfig) {
            $kernel->classloader->addNamespace(array(
                $bundleName => $this->config["Paths"],
            ));
        }
    }
    */
}





