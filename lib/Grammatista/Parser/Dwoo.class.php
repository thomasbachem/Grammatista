<?php

class GrammatistaParserDwoo extends GrammatistaParser
{
	// current comment
	protected $comment = null;
	// current entity
	protected $entity = null;
	// all found items
	protected $items = array();
	// whether to skip the next item
	protected $skipNext = false;
	
	public function __construct(array $options = array())
	{
		parent::__construct($options);
		
		if(!class_exists('Dwoo')) {
			if(isset($this->options['dwoo_autoload_path'])) {
				require($this->options['dwoo_autoload_path']);
			} else {
				require('dwooAutoload.php');
			}
		}
		
		$this->dwoo = new Dwoo(isset($this->options['compile_dir']) ? $this->options['compile_dir'] : sys_get_temp_dir());
		$this->dwoo->_grammatista_parser_dwoo = $this;
		
		foreach(array_merge($this->options['runtime_plugin_dirs'], $this->options['grammatista_plugin_dirs']) as $dir) {
			$this->dwoo->getLoader()->addDirectory($dir);
		}
	}
	
	public function __destruct()
	{
		unset($this->dwoo->_grammatista_parser_dwoo);
		unset($this->dwoo);
	}
	
	public static function extractString($string)
	{
		$tokens = token_get_all('<?php ' . $string);
		if(count($tokens) == 2 && isset($tokens[1][0])) {
			if($tokens[1][0] == T_CONSTANT_ENCAPSED_STRING) {
				return eval('return ' . $string . ';');
			} elseif($tokens[1][0] == T_STRING && strtolower($tokens[1][1]) == 'null') {
				return null;
			}
		}
		
		return false;
	}
	
	// the dwoo plugins will call this when they are compiled
	// hax <:
	public function collect($info)
	{
		if(!$this->skipNext) {
			if(($info->domain === null || $info->domain === '') && $this->entity->default_domain !== null) {
				$info->domain = $this->entity->default_domain;
			}
			
			$info->comment = $this->comment;
			
			$this->items[] = $info;
		} else {
			$this->skipNext = false;
		}
	}
	
	public function collectComment(Dwoo_Compiler $compiler)
	{
		$offset = $compiler->getPointer() - 1;
		if($offset > 0) {
			// This is a somewhat lame but simple pattern that looks for a translation comment
			// that has only characters != "{" between itself and the current block, which should
			// work for most cases
			if(preg_match(sprintf('#\{\*(?!.*\{\*)\s*%s\s*\*\}[^\{]*$#', $this->options['ignore_comment']), substr($compiler->getTemplateSource(), 0, $offset), $matches)) {
				// ignore comment
				$this->skipNext = true;
				return;
			} elseif(preg_match(sprintf('#\{\*(?!.*\{\*)\s*%s\s*(.+?)\s*\*\}[^\{]*$#', $this->options['comment_prefix']), substr($compiler->getTemplateSource(), 0, $offset), $matches)) {
				// translation comment
				$this->comment = $matches[1];
				return;
			}
		}
		
		$this->comment = null;
	}
	
	public function handles(GrammatistaEntity $entity)
	{
		$retval = $entity->type == 'tpl';
		
		if($retval) {
			Grammatista::dispatchEvent('grammatista.parser.handles', array('entity' => $entity));
		}
		
		return $retval;
	}
	
	public function parse(GrammatistaEntity $entity)
	{
		$this->entity = $entity;
		
		Grammatista::dispatchEvent('grammatista.parser.parsing', array('entity' => $entity));
		
		$template = new Dwoo_Template_String($entity->content, 0);
		$template->forceCompilation();
		$this->dwoo->setTemplate($template);
		$template->getCompiledTemplate($this->dwoo);
		
		Grammatista::dispatchEvent('grammatista.parser.parsed', array('entity' => $entity));
		
		$this->entity = null;
		
		return $this->items;
	}
}

?>