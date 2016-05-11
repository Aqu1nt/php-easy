<?php

namespace Framework\Services;

use Framework\Di\Bean;

class Session implements Bean
{
    /**
     * Session constructor.
     */
    public function __construct()
    {
        session_start();
    }

    /**
	 * @param string $key
	 * @return string value of $key or null if non existent
	 */
	public function get($key)
	{
		if (!$this->exist($key)) return null;
		return $_SESSION[$key];
	}
	
	/**
	 * @param string $key
	 * @param mixed $value
	 * @param boolean $override
	 */
	public function set($key, $value, $override = true)
	{
		$exist = $this->exist($key);
		if (! $exist || $override) $_SESSION[$key] = $value;
	}
	
	/**
	 * @param string $key
	 * @return true if $key exists
	 */
	public function exist($key)
	{
		return isset($_SESSION[$key]);
	}

    /**
     * @param $key
     */
    public function delete($key)
    {
        unset($_SESSION[$key]);
    }

	/**
	 * Destroys the session
	 */
	public function destroy()
	{
        session_unset();
		session_destroy();
	}
}