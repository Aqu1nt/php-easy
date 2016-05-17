<?php
namespace Framework\Core\Exception;

use Framework\Core\App;

class ResourceNotFoundException extends HttpException
{
    public $resource;

    public function __construct()
    {
        parent::__construct("Resource not found!", 404);
        $this->resource = App::location();
    }
}