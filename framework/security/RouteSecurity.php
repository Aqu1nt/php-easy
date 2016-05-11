<?php
namespace Framework\Security;

use Framework\Core\Routing\Route;

class RouteSecurity
{
    /**
     * @var RouteSecurity
     */
    private $parent;

    /**
     * @var Route
     */
    private $route;

    /**
     * @var boolean
     */
    private $authOnly;

    /**
     * @var array
     */
    private $allowedRoles = [];

    /**
     * @var array
     */
    private $requiredRoles = [];

    /**
     * Array with all permissions required to get access to
     * this route, a permission is a bean which implements Permission
     * or a simple function which can get invoked by the Injector
     * @var array
     */
    private $permissions = [];

    /**
     * RouteSecurity constructor.
     * @param null $route
     */
    public function __construct($route = null)
    {
        $this->route = $route;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function getAuthOnly()
    {
        if ($this->parent && $this->authOnly === null) {
            return $this->parent->getAuthOnly();
        }
        return $this->authOnly;
    }

    public function getAllowedRoles()
    {
        $roles = [];
        if ($this->parent) {
            foreach($this->parent->getAllowedRoles() as $role)
            {
                $roles[$role] = $role;
            }
        }
        foreach($this->allowedRoles as $role)
        {
            $roles[$role] = $role;
        }
        return $roles;
    }

    public function getRequiredRoles()
    {
        $roles = [];
        if ($this->parent) {
            foreach($this->parent->getRequiredRoles() as $role)
            {
                $roles[$role] = $role;
            }
        }
        foreach($this->requiredRoles as $role)
        {
            $roles[$role] = $role;
        }
        return $roles;
    }

    public function getPermissions()
    {
        $permissions = [];
        if ($this->parent) {
            $permissions = $this->parent->getPermissions();
        }
        return $permissions + $this->permissions;
    }

    public function parent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    public function permitAll()
    {
        $this->authOnly = false;
        return $this;
    }

    public function authenticated()
    {
        $this->authOnly = true;
        return $this;
    }

    public function permission($permission)
    {
        $this->authOnly = true;
        $this->permissions[] = $permission;
    }

    public function anyRole(... $roles)
    {
        $this->authOnly = true;
        foreach($roles as $role)
        {
            if (!in_array($role, $this->allowedRoles))
            {
                $this->allowedRoles[] = $role;
            }
        }
        return $this;
    }

    public function requireRole(... $roles)
    {
        $this->allRoles(... $roles);
    }

    public function allRoles(... $roles)
    {
        $this->authOnly = true;
        foreach($roles as $role)
        {
            if (!in_array($role, $this->requiredRoles))
            {
                $this->requiredRoles[] = $role;
            }
        }
        return $this;
    }
}