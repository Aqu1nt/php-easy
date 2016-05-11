<?php
namespace Framework\Security;

use Framework\Core\App;
use Framework\Core\Exception\HttpException;
use Framework\Core\Filter;
use Framework\ClassLoader;
use Framework\Di\Injector;

class SecurityFilter implements Filter
{
    /*
     * Only create this filter if we got a running Authentication,
     * it doesn't make much sense otherwise
     */
    public static $conditionalOnBean = Authentication::class;

    /**
     * @var Authentication
     */
    private $auth;

    /**
     * @var RouteSecurity
     */
    private $routeSecurity;

    /**
     * SecurityFilter constructor.
     * @param Authentication $authentication
     * @param RouteSecurity $routeSecurity
     */
    public function __construct(Authentication $authentication, RouteSecurity $routeSecurity)
    {
        $this->auth = $authentication;
        $this->routeSecurity = $routeSecurity;
    }

    /**
     * Runs this filter, if the function returns
     * anything other than null the filter chain
     * will break and the return value will get used
     */
    public function filter()
    {
        //Check if logged in
        if (($this->routeSecurity->getAuthOnly()) && !$this->auth->loggedIn()) {
            throw new HttpException("User must be logged in order to access ".App::location(), 401);
        }

        //Check if at least 1 allowed role
        //This rule only applies if at least 1 allowed role is specified
        $allowedRoles = array_values($this->routeSecurity->getAllowedRoles());
        $hasRole = $this->auth->anyRole(... $allowedRoles);
        if (!$hasRole && count($this->routeSecurity->getAllowedRoles())) {
            throw new HttpException("User does not have any allowed role(".implode(", ", $allowedRoles).") to access ".App::location(), 401);
        }

        //Check if user has all required roles
        $requiredRoles = array_values($this->routeSecurity->getRequiredRoles());
        if (!$this->auth->allRoles(... $requiredRoles))
        {
            throw new HttpException("User does not have all required roles(".implode(", ", $requiredRoles).") to acces ".App::location(), 401);
        }

        //Check if all required permissions are ok
        $hasPermission = true;
        $permissions = $this->routeSecurity->getPermissions();
        foreach ($permissions as $permission)
        {
            if (is_callable($permission) && !Injector::injectFunction($permission))
            {
                $hasPermission = false;
            }
            else if (is_string($permission) && strpos($permission, "@"))
            {
                if (!Injector::runStringMethod($permission))
                {
                    $hasPermission = false;
                }
            }
            else
            {
                var_dump($permission);
                throw new HttpException("Illegal permission type!");
            }
        }

        if (!$hasPermission)
        {
            throw new HttpException("The user doesn't have all required permissions to view ".App::location(), 401);
        }
    }
}