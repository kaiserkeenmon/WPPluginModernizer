<?php

/**
 * Project: WPPluginModernizer
 * File: Container.php
 * Author: WPPluginModernizer
 * Date: 2/28/24
 */

namespace WPPluginModernizer\Container;

class Container
{
    /** @var null  */
    private static $instance = null;

    /** @var array  */
    private $registrations;

    /** @var array Array to hold instantiated singleton objects */
    private $instances = []; // Explicitly declare the $instances property

    public static function getInstance($registrations = []) {
        if (self::$instance === null) {
            self::$instance = new self($registrations);
        }
        return self::$instance;
    }

    public function __construct(array $registrations)
    {
        $this->registrations = $registrations;
        add_action('plugins_loaded', [$this, 'loadChildPluginRegistrations']); // Load child plugin registrations.
    }

    /**
     * Prevent cloning and serialization.
     */
    private function __clone() {}
    public function __wakeup() {}

    /**
     * Get or make an instance of the class.
     * @param string $abstract
     * @return mixed The instance of the class.
     * @throws \Exception
     */
    public function get($abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (!isset($this->registrations[$abstract])) {
            throw new \Exception("No registration for {$abstract}.");
        }

        return $this->make($abstract);
    }

    /**
     * Build an instance of the class with optional parameters and handle singletons.
     * @param string $abstract
     * @return mixed The instance of the class.
     * @throws \Exception
     */
    protected function make($abstract)
    {
        $registration = $this->registrations[$abstract];

        if (isset($registration['singleton']) && $registration['singleton']) {
            return $this->getSingletonInstance($abstract, $registration);
        }

        return $this->buildInstance($registration);
    }

    /**
     * Build an instance of the class using the constructor parameters.
     * @param array $registration
     * @return object The instantiated class.
     * @throws \ReflectionException
     */
    protected function buildInstance($registration)
    {
        if (isset($registration['file']) && file_exists($registration['file'])) {
            require_once $registration['file'];
        }

        $reflector = new \ReflectionClass($registration['class']);
        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$registration['class']} is not instantiable.");
        }

        $constructor = $reflector->getConstructor();
        if (is_null($constructor)) {
            return new $registration['class']();
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters, $registration);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve the dependencies for the constructor parameters.
     * @param \ReflectionParameter[] $parameters
     * @param array $registration
     * @return array
     * @throws \Exception
     */
    protected function resolveDependencies($parameters, $registration)
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            $typeName = $type && !$type->isBuiltin() ? $type->getName() : null;

            if ($typeName && isset($this->registrations[$typeName])) {
                $dependencies[] = $this->get($typeName);
            } elseif (isset($registration['params'][$parameter->getName()])) {
                $dependencies[] = $registration['params'][$parameter->getName()];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve the dependency '{$parameter->getName()}' for class '{$registration['class']}'.");
            }
        }

        return $dependencies;
    }

    public function loadChildPluginRegistrations()
    {
        foreach (new \DirectoryIterator(WP_PLUGIN_DIR) as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isDir()) continue;

            // Child plugins can be identified by the presence of a 'modernize' file.
            $markerFilePath = $fileInfo->getPathname() . '/modernize';
            if (!file_exists($markerFilePath)) {
                continue; // Not a child plugin, skip it
            }

            // Include the Composer autoloader for each child plugin
            $composerAutoloadPath = $fileInfo->getPathname() . '/vendor/autoload.php';
            if (file_exists($composerAutoloadPath)) {
                require_once $composerAutoloadPath;
            }

            // Load service definitions if a registration file exists
            $registrationFilePath = $fileInfo->getPathname() . '/src/registration.php';
            if (file_exists($registrationFilePath)) {
                $serviceDefinitions = require $registrationFilePath;
                $this->registerServicesFromDefinition($serviceDefinitions);
            }
        }
    }

    /**
     * Registers services from a given definition array.
     *
     * @param array $serviceDefinitions Array of service definitions.
     */
    protected function registerServicesFromDefinition(array $serviceDefinitions)
    {
        foreach ($serviceDefinitions as $abstract => $definition) {
            $this->registrations[$abstract] = $definition;
        }
    }

    /**
     * Get or create a singleton instance of the class.
     * @param string $abstract
     * @param array $registration
     * @return mixed The singleton instance of the class.
     */
    protected function getSingletonInstance($abstract, $registration)
    {
        if (!isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $this->buildInstance($registration);
        }

        return $this->instances[$abstract];
    }
}
