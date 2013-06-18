<?php

if(!class_exists('JTokenizer') || !class_exists('JLex')) {
	throw new GrammatistaException('JTokenizer & JLex classes need to be loaded (http://timwhitlock.info/blog/2009/11/jparser-and-jtokenizer-released/).');
}

define('J_DECLARE', 999);

class GrammatistaJtokenizer extends JTokenizer {
	
	public function __construct($whitespace, $unicode) {
		parent::__construct($whitespace, $unicode);
		
		// Use our custom Jlex class
		$this->Lex = Lex::get('GrammatistaJlex');
	}
	
}

class GrammatistaJlex extends JLex {
	
	public function __construct() {
		parent::__construct();
		
		// Inject a "declare" keyword which we use to demarcate our patterns
		$this->words['declare'] = J_DECLARE;
	}
	
}

abstract class GrammatistaParserJavascript extends GrammatistaParserTokenizer {
	
	protected function getStringTokenDefinitions()
	{
		return array(J_STRING_LITERAL);
	}
	
	protected function getDeclareTokenDefinitions()
	{
		return array(J_DECLARE);
	}
	
	protected function getCommentTokenDefinitions()
	{
		return array(J_COMMENT);
	}
	
	protected function getIgnoredTokenDefinitions()
	{
		return array(J_WHITESPACE, J_LINE_TERMINATOR);
	}
	
	public function handles(GrammatistaEntity $entity)
	{
		$retval = $entity->type == 'js';
		
		if($retval) {
			return parent::handles($entity);
		}
		
		return $retval;
	}
	
	protected function getAllTokens($source)
	{
		$tokenizer = new GrammatistaJtokenizer(true, true);
		$tokens = $tokenizer->get_all_tokens($source);
		
		// Convert token data into PHP-like format
		$tokens = array_map(function($v) {
			return (is_array($v) && is_string($v[0])) ? $v[0] : $v;
		}, $tokens);
		
		return $tokens;
	}
	
}

?>