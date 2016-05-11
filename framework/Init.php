<?php

use Framework\ClassLoader;

//The config and the classloader are the only files we
//need to import manually
include_once "Config.php";
include_once "ClassLoader.php";

//Forward auto load requests to the class loader
function __autoload($className) {
    ClassLoader::loadClass($className);
}

//Register the framework as classes root
$documentRoot = $_SERVER["DOCUMENT_ROOT"];
$initFile = __DIR__;
$frameworkPath = str_replace($documentRoot, "", $initFile);
ClassLoader::location($frameworkPath);