<?php

namespace Framework\Database;

class Order
{
	private $query = "order by ";
	
	private function __construct($by)
	{
		$this->query .= " $by";
	}
	
	public function asc()
	{
		$this->query .= " asc";
		return $this;
	}
	
	public function desc()
	{
		$this->query .= " desc";
		return $this;
	}
	
	public function __toString()
	{
		return $this->query;
	}
	
	public static function by($by)
	{
		return new Order($by);
	}
}