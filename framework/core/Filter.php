<?php
namespace Framework\Core;

use Framework\Di\Bean;

interface Filter extends Bean {

    /**
     * Runs this filter, if the function returns
     * anything other than null the filter chain
     * will break and the return value will get used
     */
    public function filter();
}