<?php
namespace Framework\View;

use Exception;

class ViewSection
{
	private $name;
	private $content;
	private $view;
	
	public function __construct($name, $content, View $view) 
	{
		$this->name = $name;
		$this->content = $content;
		$this->view = $view;
	}
		
	
	/**
	 * Renders the section, resolves @parent statements
	 * returns the combined content
	 */
	public function render()
	{
		//Replace parent statements
		if (strpos($this->content, Statement::st("parent")))
		{
			$parentSection = $this->view->parentSection($this->name);
			if (!$parentSection)
				throw new Exception("No parent section found for $this->name");
			
			return str_replace(
					Statement::st("parent"), 
					$parentSection->render(), 
					$this->content);
		}
		
		return $this->content;
	}
}
?>