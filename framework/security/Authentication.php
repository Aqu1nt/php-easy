<?php

namespace Framework\Security;

use Framework\Database\Model;
use Framework\Di\Bean;
use Exception;
use Framework\Services\IUserService;
use Framework\Services\Session;

class Authentication implements Bean
{
    //Only create this bean if we got an IUserService implementation
    public static $conditionalOnBean = IUserService::class;

    //Keys
	private static $LOGGEDIN_KEY = "loggedIn";
	private static $USER_KEY = "user";

    /**
     * The user service implementation must be
     * registered under "UserService"
     * @var IUserService
     */
    private $userService;

    /**
     * @var Session
     */
    private $session;

    public function __construct(Session $session, IUserService $userService)
    {
        $this->userService = $userService;
        $this->session = $session;
        if (!$userService) {
            throw new Exception("No IUserService implementation with name UserService available");
        }
    }

    /**
	 * Tries to login the user
	 * @param string $username
	 * @param string $password
	 * @return true if the login was succesful
     * @throws Exception if no UserService is declared
	 */
	public function login($username, $password)
	{
        //Use the provided userservice to perform a login
        $user = $this->userService->login($username, $password);

		$this->session->set(Authentication::$USER_KEY, $user);
        $this->session->set(Authentication::$LOGGEDIN_KEY, $user !== null);
		return $this->loggedIn();
	}

	/**
	 * Logs the user out
	 */
	public function logout()
	{
        $this->session->delete(self::$USER_KEY);
        $this->session->delete(self::$LOGGEDIN_KEY);
	}
	
	/**
	 * Return if the user is currently logged in
	 * @return boolean
	 */
	public function loggedIn()
	{
		if (!$this->session->exist(Authentication::$LOGGEDIN_KEY))
		{
            $this->session->set(Authentication::$LOGGEDIN_KEY, false);
		}
		return $this->session->get(Authentication::$LOGGEDIN_KEY);
	}
	
	/**
	 * Returns the currently logged in user
     * @return Model
	 */
	public function user()
	{
		return $this->session->get(Authentication::$USER_KEY);
	}

    /**
     * @param string $role
     * @return true if the currently loggedin user has the requested role, false
     *  if not or if no user is logged in
     */
    public function hasRole($role)
    {
        return in_array($role, $this->roles());
    }

    /**
     * @param array ...$roles
     * @return true if the currently logged in user owns any of those roles
     */
    public function anyRole(... $roles)
    {
        foreach ($roles as $role)
        {
            if ($this->hasRole($role))
            {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array ...$roles
     * @return true if the currently logged in user owns all of the requested roles
     */
    public function allRoles(... $roles)
    {
        foreach ($roles as $role)
        {
            if (!$this->hasRole($role))
            {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns all roles the current user has or an empty array
     * if no user is logged in
     * @return array|mixed
     */
    public function roles()
    {
        $user = $this->user();
        if (!$user) return [];
        return $this->userService->roles($user);
    }
}