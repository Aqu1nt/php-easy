<?php
namespace Framework\Core\Routing;

use Exception;
use Framework\Core\App;
use Framework\Core\IController;
use Framework\Security\RouteSecurity;

/**
 * Router class, matches requested routes to 
 * a controller
 * 
 * @author Emil
 *
 */
class Router
{
    /**
     * Array with all active group security objects
     * @var array
     */
    private static $groupSecurities = [];

    /**
     * Array with all active tmp groups
     * @var array
     */
    private static $groups = [];

	/**
	 * Contains Route objects
	 * @var array
	 */
	private static $routes = [];
	
	/**
	 * The default controller when a route
	 * wasn't found
	 * @var IController
	 */
	private static $default;
	
	
	/**
	 * Returns the correct controller for this route
	 * @return Route
	 */
	public static function finalRoute()
	{
		//Iterate over routes
		foreach (Router::$routes as $route)
		{
			if ($route->check(App::location()))
			{
				return $route;
			}
		}
		
		return Router::$default;
	}
	
	
	/**
	 * Defines a route
	 * @param string $route
	 * @param mixed $controller
	 * @param mixed - get or post
	 * @param string $type - default or ajax
     * @return RouteSecurity
     * @throws Exception
	 */
	public static function route($route, $controller, $method = "ALL", $type = "DEFAULT")
	{
        $route = Router::applyGroup($route);
        $route = new Route($route, $controller, $method, $type);
		Router::$routes[] = $route;

        $groupSecurity = null;
        if ($groups = count(Router::$groupSecurities)) {
            $groupSecurity = Router::$groupSecurities[$groups - 1];
        }
        return $route->security()->parent($groupSecurity);
	}

    /* ***************************************************************
     ********************    Helper methods  *************************
     *****************************************************************
     */
    /**
     * @param $route
     * @param $controller
     * @param string $type
     * @return RouteSecurity
     */
    public static function get($route, $controller, $type = "DEFAULT") {
        return Router::route($route, $controller, Route::$GET, $type);
    }

    /**
     * @param $route
     * @param $controller
     * @param string $type
     * @return RouteSecurity
     */
    public static function post($route, $controller, $type = "DEFAULT") {
        return Router::route($route, $controller, Route::$POST, $type);
    }

    /**
     * @param $route
     * @param $controller
     * @param string $type
     * @return RouteSecurity
     */
    public static function put($route, $controller, $type = "DEFAULT") {
        return Router::route($route, $controller, Route::$PUT, $type);
    }

    /**
     * @param $route
     * @param $controller
     * @param string $type
     * @return RouteSecurity
     */
    public static function delete($route, $controller, $type = "DEFAULT") {
        return Router::route($route, $controller, Route::$DELETE, $type);
    }

    /**
     * @param $route
     * @param $controller
     * @param string $type
     * @return RouteSecurity
     */
    public static function options($route, $controller, $type = "DEFAULT") {
        return Router::route($route, $controller, Route::$DELETE, $type);
    }

    /**
     * Adds a prefix route to all routes which get defined
     * inside the $group function
     * @param {string} $prefix
     * @param {function} $group
     * @return RouteSecurity
     */
    public static function group($prefix, $group = null)
    {
        if (is_callable($prefix) && $group == null) {
            $group = $prefix;
            $prefix = "";
        }

        $security = new RouteSecurity();
        Router::$groupSecurities[] = $security;
        Router::$groups[] = $prefix;
        $group();
        array_pop(Router::$groups);
        array_pop(Router::$groupSecurities);
        return $security;
    }

    /**
     * Applies the current group definition
     * the the given route
     * @param $route
     * @return string
     */
    private static function applyGroup($route)
    {
        //Setup route
        if (count(self::$routes) && $route == "/") {
            $route = "";
        }

        //Setup security
        $groups = count(Router::$groupSecurities);
        if ($groups >= 2) {
            Router::$groupSecurities[$groups - 1]->parent(Router::$groupSecurities[$groups - 2]);
        }


        //Merge routes
        $withGroup = implode("", Router::$groups).$route;
        $withGroup = str_replace("//", "/", $withGroup);
        if ($withGroup != "/" && substr($withGroup, strlen($withGroup) - 1) == "/")
        {
            $withGroup = substr(0, strlen($withGroup) - 1);
        }
        return $withGroup;
    }

	/**
	 * Sets the default controller
	 * This controller is used when the requested route 
	 * is missing
	 * @param {function|string} $controllerName
     * @throws Exception
	 */
	public static function missing($controllerName)
	{
		$controller = new $controllerName();
		if (! $controller instanceof IController)
			throw new Exception("Controller is not an instance of IController");
		
		Router::$default = $controller;
	}

    /**
     * Creates a rule which redirects all calls to $from to
     * $to
     * @param $from
     * @param $to
     */
    public static function redirect($from, $to)
    {
        //Must be outside because the group info gets lost after this method
        $fromWithGroup = Router::applyGroup($from);
        $toWithGroup = Router::applyGroup($to);
        Router::route($fromWithGroup, function() use ($toWithGroup) {
            App::redirect($toWithGroup);
        });
    }

}