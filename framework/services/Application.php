<?php

namespace Framework\Services;

use Framework\Di\Bean;

/**
 * Defines the starting point of an application
 * Interface Application
 */
interface Application extends Bean
{
    public function initialize();
}