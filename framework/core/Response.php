<?php
namespace Framework\Core;

use Framework\Di\Bean;

class Response implements Bean {
    public $data;
    public $status;

    public function __construct($data = null, $status = 200)
    {
        $this->with($data, $status);
    }

    public function with($data = null, $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
        return $this;
    }

    public function __toString()
    {
        return json_encode($this->data);
    }

    public static function create($data = null, $status = 200)
    {
        return new Response($data, $status);
    }
}