<?php
namespace Framework\Core\Exception;

class HttpException extends \Exception
{
    public $status;
    public $message;

    public function __construct($message, $status = 500)
    {
        $this->message = $message;
        $this->status = $status;
    }
}