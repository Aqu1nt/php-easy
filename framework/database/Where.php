<?php

namespace Framework\Database;

use Framework\Di\Injector;

class Where
{
	private $query = "where";
	
	private function __construct($init)
	{
		$this->append($init);
	}

    /**
     * Append a token to the query
     * @param $query
     * @return $this
     */
	private function append($query)
	{
		$this->query .= " $query";
		return $this;
	}

    /**
     * @param $val
     * @return $this
     */
    private function appendVal($val)
    {
        $this->append(Injector::bean(IDatabase::class)->escape($val));
        return $this;
    }

	public function eq($val) { return $this->append("=")->appendVal($val); }
	public function lt($val) { return $this->append("<")->appendVal($val); }
	public function gt($val) { return $this->append(">")->appendVal($val); }
	public function leq($val) { return $this->append("<=")->appendVal($val); }
	public function geq($val) { return $this->append(">=")->appendVal($val); }
	public function is($val) { return $this->append("is")->appendVal($val); }
	public function like($val) { return $this->append("like")->appendVal($val); }
	
	
	/**
	 * This object is represented as its query value
	 */
	public function __toString()
	{
		return $this->query;
	}
	
	/**
	 * Adds generic tokens
	 * @param string $name
	 * @param array $arguments
	 * @return Where
	 */
	public function __call($name, $arguments)
	{
		$this->append($name);
        if (count($arguments) == 1) {
            $this->eq($arguments[0]);
        }
		return $this;
	}

    /**
     * Where Initialise Point
     * @param string $name
     * @param array $arguments
     * @return Where
     * @throws \Exception
     */
	public static function __callStatic($name, $arguments)
	{
        if (is_numeric($name)) {
            throw new \Exception("Cannot call Where:: with numeric value ($name)");
        }
		$where = new Where($name);
        if (count($arguments) == 1) {
            $where->eq($arguments[0]);
        }
        return $where;
	}
}