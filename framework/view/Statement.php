<?php
namespace Framework\View;

use Exception;

/**
 * Wrapper for a templating statement
 * @author Emil
 *
 */
class Statement
{
	
	public static $statements = array(
			"extends" => "@extends",
			"section" => "@section",
			"end" => "@end",
			"show" => "@show",
			"echo" => "@echo",
			"parent" => "@parent",
			"include" => "@include",
			"if" => "@if",
			"elseif" => "@elseif",
			"else" => "@else",
			"endif" => "@endif",
			"for" => "@for",
			"endfor" => "@endfor", 
			"out" => "{{",
			"endout" => "}}",
			"outif" => "{?",
			"endoutif" => "?}",
			"exe" => "<?",
			"endexe" => "?>",
			"component" => "@component"
	);
	
	/**
	 * Returns the statement string for the name
	 * @param string $name
     * @return string
	 */
	public static function st($name)
	{
		if (!isset(Statement::$statements[$name])) return null;
		return Statement::$statements[$name];
	}

    /**
     * Creates the next statement found in the text
     * @param string $text
     * @param array $search
     * @param int $searchStart
     * @return \Framework\View\Statement or false
     * @throws Exception
     */
	public static function next($text, $search = null, $searchStart = 0)
	{
		/**------------------------
		 * Prepare parameters
		 * ------------------------
		 */
		if ($search === null) $search = Statement::$statements;
		if (is_string($search)) $search = [$search];
		
		$keys = array_keys($search);
		for ($i = 0; $i < count($search); $i ++)
			$search[$keys[$i]] = preg_quote($search[$keys[$i]]);
		
		$regex = '/'.implode("|", $search).'/';
		
		$subText = substr($text, $searchStart);
		preg_match($regex, $subText, $matches, PREG_OFFSET_CAPTURE);
		if(count($matches) === 0) return false;
		
		/**-------------------------
		 * Fill statement
		 * -------------------------
		 */
		$statement = new Statement();
		$statement->startIndex = $matches[0][1] + $searchStart;
		$statement->statement = $matches[0][0];
		
		//Check if char after statement is " " or "\n" or "\t" or "("
		$next = $statement->startIndex + strlen($statement->statement);
		if ($next < strlen($text))
		{
			$nextChar = $text[$next];
			if ($nextChar !== " " && $nextChar !== "\n" && $nextChar !== "\t" && $nextChar !== "(")
				return Statement::next($text, $search, $next);
		}
		
		//Statements that don't require quotes at the end
		$nonQuotes = [
				Statement::st("if"),
				Statement::st("elseif"),
				Statement::st("else"),
				Statement::st("endif"),
				Statement::st("for")
		];
		$ignoreQuotes = array_search($statement->statement, $nonQuotes) !== false;
		
		/**-----------------------------
		 * Check if statement has braces
		 * -----------------------------
		 */
		$statementEnd = $statement->startIndex + strlen($statement->statement);
		if (strlen($text) > $statementEnd && 
				$text[$statementEnd] === "(") //Check if "(" follows the statement
		{
			//Go through text char by char
			$builder = "";
			$quoteOpen = false;
			$lastChar = "";
			$escape = "\\";
			$braceCount = 0;
			for ($i = $statementEnd + 1; $i < strlen($text); $i++)
			{
				$char = $text[$i];
				if ($char == $escape && $lastChar !== $escape) 
				{
					//Just save escape char
				}
				//Escaped
				else if ($lastChar === "\\" && ($quoteOpen || $ignoreQuotes)) 
				{
					$builder .= $char; // Do not check escaped character
				}
				//Unescaped Quotes
				else if (!$ignoreQuotes && ($char === "'" || $char === "\"")) 
				{
					$quoteOpen = !$quoteOpen;
				}
				//Parameter end
				else if ($char === "," && !$quoteOpen)
				{
					$statement->arguments[] = $builder;
					$builder = "";
				}
				//Arguments end
				else if ($char === ")" && !$quoteOpen && $braceCount == 0)
				{
					$statement->arguments[] = $builder;
					$statement->endIndex = $i + 1;
					$statement->fullStatement = 
						substr($text, $statement->startIndex, $statement->endIndex - $statement->startIndex);
					break;
				}
				//Default
				else if($ignoreQuotes || $quoteOpen)
				{
					if ($ignoreQuotes && $lastChar)
					{
						if ($char == "(") $braceCount ++;
						else if ($char == ")") $braceCount --;
					}
					$builder .= $char;
				}
				//Invalid character
				else if($char !== " ")
				{ 
					throw new Exception("Invalid character outside of quotes at position $i, char='$char'");
				}
				$lastChar = $char;
			}
		}
		else //Braceless statement
		{
			$statement->endIndex = $statement->startIndex + strlen($statement->statement);
			$statement->fullStatement = $statement->statement;
		}
					
		return $statement;
	}
	
	
	/** -------------------------------------------
	 * 			Object fields
	 * -------------------------------------------
	 */
	public $arguments = [];
	public $startIndex;
	public $endIndex;
	public $statement;
	public $fullStatement;
	
	/**
	 * @return the amount of arguments
	 */
	public function count()
	{
		return count($this->arguments);
	}
}