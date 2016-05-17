<?php
namespace Framework\View;

use Exception;
use Framework\ClassLoader;
use Framework\Di\Injector;

class View
{
	/**---------------------------------------------------
	 * 
	 * ************		STATIC METHODS 		**************
	 * 
	 * ---------------------------------------------------
	 */
	
	/**
	 * Variables defined by the controller used
	 * to build the view 
	 * @var array
	 */
	private static $vars = [];
	
	/**
	 * This is the root view
	 * @var View
	 */
	private static $root;
	
	/**
	 * Here are all @for iterations stored
	 * @var array
	 */
	private static $for = [];

    /**
     * Properties returned from @controller
     * calls
     * @var array
     */
    private static $ctrlProps = [];

	/**
	 * Processes a given view template
	 * 
	 * The template must be located in {root}/php/view and named {view}.view.php
	 * 
	 * @param string $name
	 * @param array $var
     * @return string
	 */
	public static function create($name, $var = [])
	{
		//convert to path
		$name = str_replace(".", "/", $name);
		
		$file = View::viewPath($name);
		
		View::$vars = View::$vars + $var;
		View::$root = new View($file);
		return View::$root->render();
	}

    /**
     * @param array $vars
     */
    public static function bind($vars = [])
    {
        self::$vars = self::$vars + $vars;
    }

	public static function viewExists($name)
	{
		try {
			self::viewPath($name);
			return true;
		} catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * Builds the path of a .view.php file
	 * Throws an exception if the file wasn't found
	 * @param string $name
	 * @return string path to view file
     * @throws Exception
	 */
	private static function viewPath($name)
	{
		global $view;
		$name = str_replace(".", "/", $name);
		$file = $view."/".$name.".view.php";
		if (! file_exists($file)) throw new Exception("View $file doesn't exist!");
		return $file;
	}

	/**
	 * Returns a substring from start to end
	 * @param string $text
	 * @param int $start
	 * @param int $end
	 * @return substring of text ranging from start to end
	 */
	private static function substr($text, $start, $end = null)
	{
		if ($end === null) $end = strlen($text);
		return substr($text, $start, $end - $start);
	}
	
	/**
	 * Removes the part of the text from start to end
	 * @param int $start
	 * @param int $end
	 * @param replacement, text to insert between $start and $end
	 */
    private static function removeText($start, $end, &$base, $replacement = "")
	{
		$before = substr($base, 0, $start);
		$after = substr($base, $end, strlen($base) - $end);
		$base = "$before$replacement$after";
	}
	
	
	/**---------------------------------------------------
	 *
	 * ************			OBJECT	 		**************
	 *
	 * ---------------------------------------------------
	 */
	
	/**
	 * File content of .view.php file
	 * @var string
	 */
	private $view;
	
	/**
	 * All sections defined in this view file
	 * @var ViewSection[]
	 */
	private $sections = [];
	
	/**
	 * Parent of this view
	 * @var View
	 */
	private $parent;

	/**
	 * Creates a new view from a {name}.view.php file
	 * @param string $file
	 */
	public function __construct($file)
	{
		$this->view = file_get_contents($file);
		
		$this->doIncludes($this->view);
		
		$this->doExtends();
		
		$this->loadSections($this->view);
	}
	
	/**
	 * @param string $name
	 * @return {ViewSection} the view named $name, or the parents section if none available, null if no section found
	 */
	public function section($name)
	{
		if (isset($this->sections[$name])) return $this->sections[$name];
		else return $this->parentSection($name);
	}
	
	/**
	 * @param string $name
	 * @return ViewSection section($name) of this views parent or false if none was found
	 */
	public function parentSection($name)
	{
		if ($this->parent !== null)
		{
			return $this->parent->section($name);
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Merges all includes into the $view
	 * @text 
	 */
	private function doIncludes(&$text)
	{
		/**------------------------------------
		 * Loop through @include
		 * ------------------------------------
		 */
		$offset = 0;
		while ($st = Statement::next($text, Statement::st("include"), $offset))
		{
			$offset = $st->endIndex;
			
			//Open file
			$name = $st->arguments[0];
			$file = View::viewPath($name);
			
			$content = file_get_contents($file);
			
			//recursive include
			$this->doIncludes($content);
			
			View::removeText($st->startIndex, $st->endIndex, $text, $content);
		}
	}
	
	/**
	 * Checks for parent views and adds a $parent if found
	 */
	private function doExtends()
	{
		//Get statement
		$statement = Statement::next($this->view, Statement::st("extends"));
		if (!$statement) return;
		
		$name = $statement->arguments[0];
		$file = View::viewPath($name);
		$this->parent = new View($file);
		$this->removeText($statement->startIndex, $statement->endIndex, $this->view);
	}


    /**
     * Finds all sections in this view and adds them to
     * the $sections array
     *
     * Also removes all @section elements from the $view
     *
     * If a section ends with @show then this method replaces the
     * @section with a @echo(sectionName)
     * @param &$view
     * @throws Exception
     */
	private function loadSections(&$view)
	{
		/**------------------------------------
		 * Loop through all @section
		 * ------------------------------------
		 */
		$section = Statement::st("section");
		$offset = 0;
		while ($st = Statement::next($view, $section, $offset))
		{
			//I don't know why, but endIndex doesn't work right here
			$offset = $st->startIndex;
			
			
			/**------------------------------------
			 * 	Check section format
			 * ------------------------------------
			 */
			//@section("...", "...")
			if ($st->count() == 2)
			{
				$name = $st->arguments[0];
				$content = $st->arguments[1];
				
				$this->sections[$name] = new ViewSection($name, $content, $this);
				$this->removeText($st->startIndex, $st->endIndex, $view);
			}
			//@section("...")
			else if ($st->count() == 1)
			{
				$name = $st->arguments[0];
				
				//get finisher
				$show = Statement::st("show");
				$end = Statement::st("end");
				
				//Find correct finisher!
				$openSections = 1;
				$subOffset = $st->endIndex;
				while ($finish = Statement::next($view, [$show, $end, $section], $subOffset))
				{
					$subOffset = $finish->endIndex;
					
					if ($finish->statement === $section && $finish->count() == 1) $openSections ++;
					else if ($finish->statement === $end || $finish->statement === $show)
					{
						$openSections --;
						
						//Right section ending found
						if ($openSections === 0) break;
					}
				}
				if (!$finish)
					throw new Exception("No $show or $end found to close $st->fullStatement");
				
				//Fetch content
				$content = View::substr($view, $st->endIndex, $finish->startIndex - 1);
				
				//Recursive children section loading 
				$this->loadSections($content);
				
				$viewSection = new ViewSection($name, $content, $this);
				$this->sections[$name] = $viewSection;
				$replacer = $finish->statement === $show ? Statement::st("echo").'("'.$name.'")' : "";
				$this->removeText($st->startIndex, $finish->endIndex, $view, $replacer);
			}
			else
			{
				throw new Exception("$section must have 1 or 2 arguments");
			}
		}
	}
	
	
	/**
	 * Renders the complete view, if this view has a parent
	 * it will forward the render to it's parent
	 * @return string the view as string
	 */
	public function render()
	{
		//Forward the render command to parent
		if ($this->parent) return $this->parent->render();
		
		//Build the complete layout first
		$this->resolveEchos($this->view);
		
		//Now Execute the whole logic shit
		$this->view = $this->executeStatements($this->view);
		
		/**
		 * Then, insert the data into the layout
		 */
		return $this->echoLayout($this->view);
	}

	/**
	 * Executes @echo and @if statements on the view
	 * @param string $view
     * @param $additionalVars
     * @return mixed the view
     * @throws Exception
	 */
	private function executeStatements($view, $additionalVars = [])
	{
		$offset = 0;
		while ($st = Statement::next($view, Statement::$statements, $offset))
		{
			$offset = $st->endIndex;
			
			switch($st->statement)
			{
                /**----------------------------------------
                 * 					CONTROLLER
                 * ----------------------------------------
                 */
                case Statement::st("component"):
                    $call = $st->arguments[0];
                    if (strpos($call, "@"))
                    {
                        $props = Injector::runStringMethod($call);
                    } else {
                        $props = (array) Injector::newInstance(ClassLoader::getClassWithNamespace($call));
                    }
                    $additionalVars += $props;
                    self::$ctrlProps[] = $props;
                    $i = count(self::$ctrlProps) - 1;

                    //We must inject this setter in order to use the props in {{ }}
                    //Statements!
                    $propsSetter = "<? extract(self::\$ctrlProps[$i]) ?>";
                    $this->removeText($st->startIndex, $offset, $view, $propsSetter);
                    $offset = $st->startIndex + strlen($propsSetter);
                    break;

				/**----------------------------------------
				 * 					IF
				 * ----------------------------------------
				 */
				case Statement::st("if"):
					$if = $st;
					$innerOffset = $if->startIndex; 
					$elseFound = false; //If an else was already found in this statement
					$ifCounter = -1; //Count inner ifs
					
					$success = false; //If any statement was a sucess
					$bodyStart = 0;
					$body = null;
					
					$search = [
							Statement::st("if"),
							Statement::st("elseif"),
							Statement::st("else"),
							Statement::st("endif")
					];
					
					//Iterate over if statements
					while ($ifSt = Statement::next($view, $search, $innerOffset))
					{
						$innerOffset = $ifSt->endIndex;
						
						//Jump over inner ifs
						if ($ifSt->statement == Statement::st("if")) $ifCounter ++;
						
						//Only execute current if
						if ($ifCounter == 0)
						{
							//Check if statements are true
							if ($ifSt->statement == Statement::st("if") || 
								$ifSt->statement == Statement::st("elseif"))
							{
								if (!$success) 
								{
									$success = $this->doIf($ifSt->arguments[0], $additionalVars);
									if ($success)
									{
										$bodyStart = $ifSt->endIndex;
									}
								}
							}
							//Else is always sucess
							if ($ifSt->statement == Statement::st("else"))
							{
								if ($elseFound)
									throw new Exception("More than 1 @else found!");
								
								$elseFound = true;
								if (!$success)
								{
									$success = true;
									$bodyStart = $ifSt->endIndex;
								}
							}
							
							//If last statement was the sucess
							if ($success && $bodyStart < $ifSt->endIndex && $body == null)
							{
								$body = View::substr($view, $bodyStart, $ifSt->startIndex);
								$body = $this->executeStatements($body, $additionalVars);
							}
							
							//End if
							if ($ifSt->statement == Statement::st("endif"))
							{
								$this->removeText($if->startIndex, $ifSt->endIndex, $view, $body);
																	
								//Correct offset
								$offset = $if->startIndex + strlen($body);
								break;
							}
						}
						
						//This must be at he end!
						if ($ifSt->statement == Statement::st("endif")) $ifCounter --;
					}
					break;
				
				/**----------------------------------------
				 * 					FOR
				 * ----------------------------------------
				 */
				case Statement::st("for"):
                    extract(View::$vars);
                    extract($additionalVars);
					
					$_for = $st;
					
					//Search correct end
					$_innerOffset = $_for->startIndex;
					$_counter = 0;
					$_finish = null;
					
					while ($_forSt = Statement::next($view, [Statement::st("for"), Statement::st("endfor")], $_innerOffset))
					{
						$_innerOffset = $_forSt->endIndex;
						
						if ($_forSt->statement == Statement::st("for")) $_counter ++;
						if ($_forSt->statement == Statement::st("endfor")) $_counter --;
						
						if ($_counter == 0) 
						{
							$_finish = $_forSt;
							break;
						}
						
					}
					
					if (! $_finish)
						throw new Exception("No ".Statement::st("endfor")." found");
					
					$_body = View::substr($view, $_for->endIndex, $_finish->startIndex);
					
					//Fetch loop arguments
					$_list = null;
					$_var = null;
					$_as = false;
					$_tokens = explode(" ",trim($_for->arguments[0]));
					foreach ($_tokens as $_token)
					{
						if (!$_token) continue;
						
						if ($_token[0] !== "$" && $_token[0] !== "[" && $_token !== "as")
							throw new Exception("Invalid token in for loop: '$_token'");
						
						if (!$_list) $_list = substr($_token, 1);
						else if (!$_as && $_token == "as") $_as = true;
						else $_var = substr($_token, 1);
					}
					
					//Compile loop to executable code
					$_compiled = "";
					eval('foreach ($'.$_list.' as $'.$_var.')
					{
						self::$for[] = $$_var;
						$_compiled .= "<? $$_var = self::\$for[".(count(self::$for)-1)."]; ?>";
						$_compiled .= $this->executeStatements($_body, array_merge($additionalVars, [$_var => $$_var]));
					}');
					
					$this->removeText($_for->startIndex, $_finish->endIndex, $view, $_compiled);
					$offset = $_for->startIndex + strlen($_compiled);
					
					break;
			}
		}
		
		return $view;
	}
	
	/**
	 * Processes @if / @elseif / @else statements
	 * @param unknown $statement
	 * @return if the statement is true
	 */
	private function doIf($statement, $additionalVars = [])
	{
		extract(View::$vars);
        extract($additionalVars);
		$_php = "return ($statement);";
        $result = (bool) eval($_php);
		return $result;
	}

    /**
     * Resolve @echo statements recursive
     * If the @echo statement produces a new @echo statement it will resolve them also
     * @param $view
     * @return string , the view with all echos resolved
     * @throws Exception
     */
	private function resolveEchos(&$view)
	{
		//Find next @echo
		$offset = 0;
		while ($st = Statement::next($view, Statement::st("echo"), $offset))
		{
			$offset = $st->endIndex;
			
			
			//Fetch arguments
			$name = $st->arguments[0];
			$default = $st->count() >= 2 ? $st->arguments[1] : null;
			
			$section = View::$root->section($name);
			if (!$section && $default === null) throw new Exception("No section or default value for '$name'!");
			else if (!$section && $default !== null)
			{
				$resolved = $default;
			}
			else
			{
				$resolved = $section->render();
				$this->resolveEchos($resolved);
			}
				
			View::removeText($st->startIndex, $st->endIndex, $view, $resolved);
		}
	}


    /**
     * Echo the data into the layout structure
     * @param $_view
     * @return string
     * @throws Exception
     */
	private function echoLayout($_view)
	{
        $page = "";

		//Set local vars
		extract(View::$vars);
		
		$searches = [
				Statement::st("out"),
				Statement::st("outif"),
				Statement::st("exe"),
		];



		$_offset = 0;
		while ($_st = Statement::next($_view, $searches, $_offset))
		{
			$page .= View::substr($_view, $_offset, $_st->startIndex);
			$_offset = $_st->endIndex;

			//Compile {{ ... }} statements to executable code
			if ($_st->statement == Statement::st("out"))
			{
				$_ending = strpos($_view, Statement::st("endout"), $_offset);
				
				if (!$_ending)
					throw new Exception("No }} for opened {{ found");
				
				$_args = View::substr($_view, $_st->startIndex + 2, $_ending);
				
				//Regex that matches spaces that are NOT between " or '
				//FROM http://stackoverflow.com/questions/28584839/preg-split-by-space-and-tab-outside-quotes
				$_tokens = preg_split('~(?:\'[^\']*\'|"[^"]*"|\\([^\\)]*\\))(*SKIP)(*F)|\\h+~', $_args);
				
				$_or = false;
				$_fail = false;
				$_first = true;
				$_success = false;
				foreach ($_tokens as $_token)
				{
					if ($_token === "") continue; 
					
					if ($_token === "or")
					{
						if (!$_fail)
							throw new Exception("'or' cannot occur before any statements");
						else {
							$_fail = false;
							$_or = true;
						}
					}
					else
					{
						if (!$_first && !$_or)
							throw new Exception("Please chain statements with 'or'");
						
						//Reset or
						$_or = false;
							
						//Try to execute the code
						try
						{
							//If token is a variable, check if it's defined
							if ($_token[0] === "$")
							{
								$_name = explode("->", substr($_token, 1))[0];
								if (!isset($$_name)) 
								{
									throw new Exception();
								}
							}
							$_result = eval("return $_token;");
						} catch (Exception $e)
						{
							$_result = null;
						}
						
						//Compile this token to an echo
						if ($_result !== null)
						{
                            $page .= htmlspecialchars($_result);
							$_success = true;
							$_offset = $_ending + 2;
							break;
						}
						else
						{
							$_fail = true;
						}
					}
					
					$_first = false;
				}
			
				//If no var was defined
				if (!$_success)
				{
					$_offset = $_ending + 2;
// 					throw new Exception("No statement in {{ $_args }} returns a value not null!");
				}
			}
		
			//Compile {? ... ?} statements to executble code
			else if($_st->statement == Statement::st("outif"))
			{
				$_ending = strpos($_view, Statement::st("endoutif"), $_offset);
				
				if (!$_ending)
					throw new Exception("No ?} for opened {? found");
						
				$_if = View::substr($_view, $_st->startIndex + 2, $_ending);

                $_if = str_replace(" or ", " : ", $_if);
                if (!strpos($_if, " : ")) $_if .= " : \"\""; //Add else extension if not found
                $_if = str_replace("=>", "?", $_if);

				$_php = "\$page .= htmlspecialchars($_if)";
				$_php .= ";";
				
				eval($_php);
				
				$_offset = $_ending + 2;
			}
			
			//Process < ? .. ? >
			else if($_st->statement == Statement::st("exe"))
			{
				$_ending = strpos($_view, Statement::st("endexe"), $_st->startIndex);
				
				if (!$_ending)
					throw new Exception("No ?> for opened <? found");
				
				$_php = View::substr($_view, $_st->startIndex + 2, $_ending).";";
				$page .= eval($_php);
				
				$_offset = $_ending + 2;
			}
		}
		//Last piece of layout
		$page .= View::substr($_view, $_offset);
        return $page;
	}
}