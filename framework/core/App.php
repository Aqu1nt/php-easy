<?php
namespace Framework\Core;

use Framework\ClassLoader;
use Framework\Core\Exception\HttpException;
use Framework\Core\Routing\Router;
use Framework\Services\Application;
use Framework\Di\Injector;
use Framework\Services\Session;
use Framework\View\View;

/**----------------------------------------
 * App manager
 * ----------------------------------------
 */
class App
{
    /**
     * Session key
     * @var string
     */
	private static $PARAMS = "framework-params";


	/**
	 * All args from the route
	 * example:
	 * 
	 * route -> /foo/{id}
	 * path -> /foo/32
	 * App::$args = ["id" => 32]
	 * 
	 * @var array
	 */
	private static $args = [];
	
    /**
	 * Default error handler
	 * @var {function}
	 */
	private static $onError = null;

	 /**
	  * Starts the controller
	  * The results will be stored
	  */
	 public static function start()
	 {
        try {
            //Setup the injector
            Injector::init();


            //Initialize the application
            $application = Injector::bean(Application::class);
            $application->initialize();

            //Bind previously set parameters
            $session = Injector::bean(Session::class);
            if ($session->exist(self::$PARAMS)) {
                View::bind($session->get(self::$PARAMS));
                $session->delete(self::$PARAMS);
            }

            //The output
            $out = null;

            //Find the Route
            $route = Router::finalRoute();
            if (!$route) {
                throw new \Exception("No route found for request: ".App::location());
            }
            Injector::registerBean($route->security()); //Register the security so we can inject it later

            //Run filters
            $filters = Injector::beans(Filter::class);
            foreach ($filters as $filter) {
                if ($out = $filter->filter()) {
                    break;
                }
            }

            //Run the controller
            if ($out == null)
            {
                $controller = $route->controller();
                if ($controller === null){
                    App::error("No controller found for ".App::location(), 404);
                }
                $out = App::controller($controller);
            }

            //Output the result
            self::out($out);
        }
        catch (HttpException $httpEx)
        {
            App::status($httpEx->status);
            self::out($httpEx);
        }
        finally
        {
            Injector::destroyBeans();
        }
	 }

    /**
     * @param $out
     */
    private static function out($out)
    {
        if ($out) {
            if (is_string($out))
            {
                echo $out;
            }
            else if ($out instanceof Response) {
                App::status($out->status);
                echo json_encode($out->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            else if ($out instanceof HttpException)
            {
				App::status($out->status);
				if (View::viewExists($out->status)) //404.view.php
				{
					echo View::create($out->status, [ "error" => $out ]);
				} else {
					$ex = ["type" => get_class($out)];
					$reflClass = new \ReflectionClass($out);
					$props = $reflClass->getProperties(\ReflectionProperty::IS_PUBLIC);
					foreach($props as $prop) {
						$ex[$prop->getName()] = $prop->getValue($out);
					}
					echo json_encode($ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
				}
            }
            else
            {
                echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }
    }

    /**
     * Runs the controller
     * @param $controller
     * @return mixed
     * @throws \Exception
     */
     private static function controller($controller)
     {
         if (is_callable($controller)) {
             return Injector::injectFunction(new \ReflectionFunction($controller));
         }
         else if (is_string($controller))
         {
            if (strpos($controller, "@")) {
                return Injector::runStringMethod($controller);
            } else {
                $ctrl = Injector::newInstance(ClassLoader::getClassWithNamespace($controller));
                if (!$ctrl instanceof IController) {
                    throw new \Exception("$controller does not implements IController!");
                }
                return $ctrl->control();
            }
         }
     }

	 /**
	  * ALWAYS USE THIS INSTEAD OF die() or exit()
	  * Stops the Application, closes resources
	  */
	 public static function destroy()
	 {
        Injector::destroyBeans();
	 	die();
	 }
	 
	 /**
	  * Defines the default error handler, the function must have
	  * 1 parameter, the error message
	  * @param {function} $fn
	  */
	 public static function onError($fn)
	 {
	 	App::$onError = $fn;
	 }
	 
	 /**
	  * Call this when an error occurs
	  * @param {function} $message
      * @param int $code
	  */
	 public static function error($message, $code = 200)
	 {
	 	if (App::$onError != null)
	 	{
	 		$handler = App::$onError;
            if (is_callable($handler))
            {
                $handler($message);
            }
	 	}
	 	else 
	 	{
            App::status($code);
	 		echo "An error occured: $message";
	 		App::destroy();
	 	}
	 }

    /**
     * Sets the status of the response
     * @param $code
     */
    public static function status($code)
    {
        header('X-PHP-Response-Code: '.$code, true, $code);
    }

	 /**
	  * Function to fetch an arg from the request path
	  * @param string $key
	  * @return string the value of the key or null if it doesn't exist
	  */
	 public static function arg($key)
	 {
	 	if (!isset(App::$args[$key])) return null;
	 	return urldecode(App::$args[$key]);
	 }
	 
	 /**
	  * Returns either a GET or a POST parameter, based on the request type
	  * @param string $key or null
      * @return mixed
	  */
	 public static function param($key)
	 {
	 	if (App::method() === "GET") return App::get($key);
	 	if (App::method() === "POST") return App::post($key);
	 	return null;
	 }
	 
	 /**
	  * @return the request method
	  */
	 public static function method()
	 {
	 	return $_SERVER['REQUEST_METHOD'];
	 }
	 
	 /**
	  * Wrapper for $_GET
	  * @param unknown $key
	  * @return GET parameter or null
	  */
	 public static function get($key)
	 {
	 	if (isset($_GET[$key])) return $_GET[$key];
	 	return null;
	 }
	 
	 /**
	  * Wrapper for	$_POST
	  * @param unknown $key
	  * @return POST parameter or null
	  */
	 public static function post($key)
	 {
	 	if (isset($_POST[$key])) return $_POST[$key];
	 	return null;
	 }
	 
	 
	 /**
	  * @param array $args
	  */
	 public static function args($args)
	 {
		App::$args = $args;	 	
	 }

    /**
     * Redirects the browser to this url
     * @param {string} $to
     * @param array $params
     * @param boolean $permanent
     * @throws \Exception
     */
	 public static function redirect($to, $params = [], $permanent = true)
	 {
         Injector::bean(Session::class)->set(self::$PARAMS, $params);
         header('Location: ' . $to, true, ($permanent === true) ? 301 : 302);
         App::destroy();
	 }

    /**
     * @param array $params
     */
    public static function reload($params = [])
    {
        self::redirect(self::location(), $params);
    }

	 /**
	  * @return bool true if the request is an ajax request
	  */
	 public static function ajax()
	 {
	 	return (isset($headers['X-Requested-With']) && $headers['X-Requested-With'] == 'XMLHttpRequest');
	 }
	 
	 /**
	  * @return string the location of the request, always in lower case!
      * @return string
	  */
	 public static function location()
	 {
	 	return $_SERVER['REQUEST_URI'];
	 }
}