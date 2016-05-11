<?php

namespace Framework;

use DirectoryIterator;

/**
 * Class ClassLoader
 */
class ClassLoader
{
    /**
     * Array with all base paths registered for class
     * loading, extend this array with the ClassLoader::classes()
     * method
     * @var
     */
    private static $paths = [];

    /**
     * Registers the directory as class source
     * @param $directory
     */
    public static function location($directory)
    {
        ClassLoader::$paths[] = $directory;
    }

    public static function loadAllClasses()
    {
        self::loadClass(null);
    }

    /**
     * Attempts to load the given class
     * This will only succeed if the class is in a file
     * named the same as the class and below a registered
     * source directory
     * @param $class
     */
    public static function loadClass($class)
    {
        if ($class != null)
        {
            $tokens = explode("\\", $class);
            $class = $tokens[count($tokens) - 1];
        }

        foreach (ClassLoader::$paths as $path)
        {
            ClassLoader::loadClassRecursive($path, $class);
        }
    }

    /**
     * Auto loading Helper
     * @param {string} $dir
     * @param {string} $class_name
     * @return boolean if a class was loaded
     */
    private static function loadClassRecursive($dir, $class = null)
    {
        if ($class) { //Search for one class
            $name = "$dir/$class.php";
            if (file_exists($name)) {
                include_once $name;
            }
        }

        //Check if file exists
        $dir = new DirectoryIterator($dir);
        foreach ($dir as $fileinfo)
        {
            if (!$fileinfo->isDot())
            {
                $file = $fileinfo->getPathname();
                $isView = strpos($file, ".view.php") > -1;
                $isPhp = $fileinfo->getExtension() == "php";

                if (is_dir($file)) //Filter directories
                {
                    ClassLoader::loadClassRecursive($file, $class);
                }
                else if ($isPhp && !$isView && !$class) {
                    include_once $file;
                }
            }
        }
    }

    /**
     * @param $name
     * @return string
     * @throws \Exception
     */
    public static function getClassWithNamespace($name)
    {
        if (class_exists($name)) return $name;

        //Try to resolve the class without a namespace!
        foreach (get_declared_classes() as $class) {
            $reflClass = new \ReflectionClass($class);
            if ($reflClass->getShortName() == $name) return $reflClass->getName();
        }
        throw new \Exception("Class class $name does not exist!");
    }
}