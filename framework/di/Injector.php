<?php

namespace Framework\Di;

use Framework\Core\App;
use Framework\ClassLoader;

/**
 * The Injector class is responsible to run a method and injecting
 * the required parameters
 */
class Injector
{
    /**
     * Array containing all instantiated services
     * @var array
     */
    private static $beans = [];

    /**
     * Array with all registered bean classes
     * @var array.<\ReflectionClass>
     */
    private static $beanClasses;

    /**
     * Loads all classes and singletons
     */
    public static function init()
    {
        ClassLoader::loadAllClasses();
        self::initSingletons();
    }

    /**
     * Injects all dependencies into the method
     * @param mixed $method
     * @param null $instance
     * @return mixed
     */
    public static function injectMethod($method, $instance = null)
    {
        if (is_string($method)) {
            $method = new \ReflectionMethod($instance, $method);
        }

        $method->setAccessible(true);
        $params = self::params($method->getParameters());
        return $method->invokeArgs($instance, $params);
    }

    /**
     * Injects all dependencies into the method
     * @param mixed $method
     * @param null $instance
     * @return mixed
     */
    public static function injectMethodIfExists($method, $instance = null)
    {
        try
        {
            return self::injectMethod($method, $instance);
        }
        catch (\ReflectionException $e)
        {
            //OK
            return null;
        }
    }

    /**
     * Injects all dependencies into the function
     * @param mixed $function
     * @return mixed
     */
    public static function injectFunction($function)
    {
        if (!($function instanceof \ReflectionFunction)) {
            $function = new \ReflectionFunction($function);
        }

        $params = self::params($function->getParameters());
        return $function->invokeArgs($params);
    }

    /**
     * Registers the service under $class
     * @param $bean
     */
    public static function registerBean($bean)
    {
        self::$beans[] = $bean;
    }

    /**
     * @param \ReflectionParameter $field
     * @return mixed
     */
    public static function object(\ReflectionParameter $field)
    {
        $name = $field->getName();

        $object = App::param($name); //Parameter
        if ($object === null) $object = App::arg($name); //Path variable
        if ($object === null) { //Try to inject the service
            $class = $field->getClass();
            if ($class) {
                $object = self::bean($class);
            }
        }
        return $object;
    }

    /**
     * @param $class
     * @return mixed
     */
    public static function newInstance($class)
    {
        if (!($class instanceof \ReflectionClass)) {
            $class = new \ReflectionClass($class);
        }

        if ($constructor = $class->getConstructor()) {
            $params = self::params($constructor->getParameters());
            $instance = $class->newInstanceArgs($params);
        } else {
            $instance = $class->newInstance();
        }

        //Inject inject(...) method
        self::injectInto($instance);

        return $instance;
    }

    /**
     * Injects all dependencies into the "inject" method if
     * available
     * @param $object
     * @return mixed
     */
    public static function injectInto($object)
    {
        return self::injectMethodIfExists("inject", $object);
    }

    /**
     * Returns all beans available for the given class
     * @param $class
     * @return array
     */
    public static function beans($class)
    {
        $beans = [];
        $classes = self::loadBeanClasses($class);
        foreach ($classes as $c) {
            $beans[] = self::bean($c);
        }
        return $beans;
    }

    /**
     * Creates a new bean from the given class or returns
     * an existing matching bean
     * @param mixed $class
     * @return mixed
     * @throws \Exception
     */
    public static function bean($class)
    {
        if (!($class instanceof \ReflectionClass)) {
            $class = new \ReflectionClass($class);
        }

        $service = null;

        //Attempt to find existing object
        foreach (self::$beans as $s) {
            if (self::matches($class, new \ReflectionClass($s))) {
                if ($service != null) {
                    $s1n = (new \ReflectionClass($service))->getName();
                    $s2n = (new \ReflectionClass($s))->getName();
                    throw new \Exception("Multiple beans found for class ".$class->getName()."!($s1n, $s2n)");
                }

                $service = $s;
            }
        }

        //Create new instance
        if ($service == null) {
            $beanClass = self::getBeanClass($class);
            $service = self::newInstance($beanClass);
            self::$beans[] = $service;
        }

        return $service;
    }

    /**
     * Returns if a bean for the requested class is available
     * @param $requested
     * @return true if a matching bean class was found
     */
    public static function hasBean($requested)
    {
        try {
            self::getBeanClass($requested);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Finds a class which is either the class itself, a subclass or
     * implements the interface
     * @param \ReflectionClass $requested
     * @return \ReflectionClass the class wich fits as dependency
     * @throws \Exception
     */
    private static function getBeanClass($requested)
    {
        if (!($requested instanceof \ReflectionClass)) {
            $requested = new \ReflectionClass($requested);
        }

        if (self::$beanClasses == null) {
           self::$beanClasses = self::loadBeanClasses(Bean::class);
        }

        $beans = [];
        foreach (self::$beanClasses as $bean) {
            if  (self::matches($requested, $bean)) {
                $beans[] = $bean;
            }
        }
        if (count($beans) == 0) {
            throw new \Exception("No declared bean class found for class ".$requested->getName());
        }
        if (count($beans) > 1) {
            $names = "";
            foreach ($beans as $bean) $names .= " ".$bean->getName();
            throw new \Exception("Multiple bean classes found which are eligible for class ".$requested->getName().": $names");
        }
        return $beans[0];
    }

    /**
     * Returns true if $other is either the requested class
     * or a subclass (implements)
     * @param \ReflectionClass $requested
     * @param \ReflectionClass $other
     * @return true if the classes match
     */
    private static function matches($requested, $other)
    {
        $conditions = self::conditions($other);
        $isSame = $requested == $other;
        $extends = !$requested->isInterface() && $other->isSubclassOf($requested);
        $implements = $requested->isInterface() && $other->implementsInterface($requested);
        return ($isSame || $extends || $implements) && !$other->isAbstract() && $conditions;
    }

    /**
     * @param \ReflectionClass $class
     * @return bool
     */
    private static $checkCounter = 0;
    private static $checking = [];
    private static function conditions(\ReflectionClass $class)
    {
        /**
         * Condition where we only create a bean if
         * we find another bean which is matching the bean
         * specified in the static property, this property must
         * be public!
         */
        if ($class->hasProperty("conditionalOnBean")) {
            //Prevent infinite rescursion
            if (end(self::$checking) == $class) return true;
            if (count(self::$checking) != self::$checkCounter)
            {
                self::$checking[] = $class;
            }
            else
            {
                $bean = $class->getStaticPropertyValue("conditionalOnBean");
                self::$checkCounter ++;
                $has = self::hasBean($bean);
                self::$checkCounter --;
                array_pop(self::$checking);
                return $has;
            }
        }

        /**
         * Evaluates if the property resolves to true
         * When true the bean will get created
         */
        if ($class->hasProperty("conditionalOnProperty"))
        {
            $prop = $class->getStaticPropertyValue("conditionalOnProperty");
            return $prop;
        }

        return true;
    }

    /**
     * Loads all classes that matches the given class/interface
     * and returns them as an array (\ReflectionClass)
     * @param mixed $requested
     * @return array
     */
    public static function loadBeanClasses($requested)
    {
        if (!($requested instanceof \ReflectionClass)) {
            $requested = new \ReflectionClass($requested);
        }

        $beanClasses = [];
        foreach (get_declared_classes() as $class) {
            $class = new \ReflectionClass($class);
            if (self::matches($requested, $class)) {
                $beanClasses[] = $class;
            }
        }

        self::orderBeanClasses($beanClasses);

        return $beanClasses;
    }

    /**
     * makes use of public static $order = 0
     *
     * Orders the classes by their "order" attribute
     * Lowest order will appear at the end of the array, so the
     * higher the order is, the earlier they will get put into
     * the array
     *
     * Classes without the static order property will get
     * a default order of 0
     *
     * @param $classes
     * @return array
     */
    public static function orderBeanClasses(&$classes)
    {
        $order = function($class){
            if ($class->hasProperty("order")) {
                return $class->getStaticPropertyValue("order");
            }
            return 0;
        };
        usort($classes, function($a, $b) use ($order) {
            return $order($b) - $order($a);
        });
    }

    /**
     * Extracts the array that should get injected
     * @param \ReflectionParameter[] $fields
     * @return array
     */
    private static function params(array $fields)
    {
        $params = [];
        for ($i = 0; $i < count($fields); $i++)
        {
            $param = $fields[$i];
            $params[] = self::object($param);
        }
        return $params;
    }

    /**
     * Initialises all beans which implement the Singleton interface
     */
    public static function initSingletons()
    {
        $singletons = self::loadBeanClasses(Singleton::class);
        foreach($singletons as $singleton)
        {
            self::bean($singleton);
        }
    }

    /**
     * Destroys all beans aka calls the destroy() method
     * on all LifecycleBeans
     */
    public static function destroyBeans()
    {
        foreach (self::$beans as $bean) {
            $class = new \ReflectionClass($bean);
            if ($class->implementsInterface(LifecycleBean::class)) {
                $bean->destroy();
            }
        }
    }

    /**
     * @param string $call
     * @return mixed
     * @throws \Exception
     */
    public static function runStringMethod($call)
    {
        if (!strpos($call, "@")) {
            throw new \Exception($call." does not contain @!");
        }

        $name = explode("@", $call)[0];
        $method = explode("@", $call)[1];
        $instance = Injector::newInstance(ClassLoader::getClassWithNamespace($name));
        return Injector::injectMethod($method, $instance);
    }
}