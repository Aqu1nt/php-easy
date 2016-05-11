<?php

namespace Framework\Core\Routing;
use Framework\Core\App;
use Framework\Core\IController;
use Framework\Security\RouteSecurity;

/**
 * Wrapper for a route 
 * 
 * @author Emil
 *
 */
class Route
{
	//Request methods
	public static $ALL = "ALL";
	public static $GET = "GET";
	public static $POST = "POST";
    public static $PUT = "PUT";
    public static $DELETE = "DELETE";
    public static $OPTIONS = "OPTIONS";

    //Request types
	public static $DEFAULT = "DEFAULT";
	public static $AJAX = "AJAX";
	
	
	/**
	 * @var string $route
	 */
	private $route;
	
	/**
	 * The controller assigned to this route
	 * @var IController
	 */
	private $controller;
	
	/**
	 * The method this route is mapped to
	 * @var string
	 */
	private $method;
	
	/**
	 * The type of the request, aka default or ajax
	 */
	private $type;

    /**
     * The security for this route
     * @var RouteSecurity
     */
    private $routeSecurity;

    /**
     * @param string $route
     * @param $controller
     * @param string $method - default Route::$ALL
     * @param string $type
     */
	public function __construct($route, $controller, $method = "ALL", $type = "DEFAULT")
	{
		$this->route = Route::normalize($route);
		$this->controller = $controller;
		$this->method = $method;
		$this->type = $type;
        $this->routeSecurity = new RouteSecurity($this);
	}
	
	/**
	 * @param string $path
	 * @return boolean true if the route matches the path and the method
	 */
	public function check($path)
	{
		//Check method
		if ($this->method !== Route::$ALL)
		{
			if ($this->method !== App::method()) return false;
		}
		
		//Check type
		if ($this->type !== Route::$ALL)
		{
			if (App::ajax() && $this->type !== Route::$AJAX) return false;
		}
		
		
		$path = Route::normalize($path);
		
		//Handle /{id} routes
		if ($path === "/" && $this->route !== "/") return false;
	
		//Handle / routes
		if ($path !== "/" && $this->route === "/") return false;
		
		//Check direct match aka '/' === '/'
		if ($path === $this->route) return true;
		
		//Do tokenized check
		$pathTokens = explode("/", $path);
		$routeTokens = explode("/", $this->route);
		
		if (count($pathTokens) === count($routeTokens))
		{
			$args = [];
			for ($i = 0, $len = count($routeTokens); $i < $len; $i++)
			{
				$routeToken = $routeTokens[$i];
				$pathToken = $pathTokens[$i];
				if (strlen($routeToken) === 0) continue;
				
				if ($routeToken[0] == "{" && $routeToken[strlen($routeToken) - 1] == "}") //Token is {...}
				{
					$variable = substr($routeToken, 1, strlen($routeToken) - 2);

                    //Check for regexes (seperated by ':' )
                    if (strpos($routeToken, ":")) {
                        //Regex found
                        $tokens = explode(':', $variable);
                        $variable = $tokens[0];
                        $regex = $tokens[1];
                        if (!preg_match("($regex)", $pathToken)) {
                            return false;
                        }
                    }

					$args[$variable] = $pathToken;
				}
				else if ($routeToken !== $pathToken) //Standard string token
				{
					//Token not matching
					return false;
				}
			}
			//Everything OK
			App::args($args);
			return true;
		}
		//Count not equal
		return false;		
	}
	
	/**
	 * @return IController controller
	 */
	public function controller()
	{
		return $this->controller;
	}

    /**
     * @return RouteSecurity
     */
	public function security()
    {
        return $this->routeSecurity;
    }

	/**
	 * Adds a leading slash to the route if needed and removes the query
	 * @param string $route
     * @return string
	 */
	public static function normalize($route)
	{
		if (substr($route, 0, 1) != "/") $route = "/$route";
		
		$index = strpos($route, "?");
		if ($index > -1) $route = substr($route, 0, $index);
		
		return $route;
	}
}