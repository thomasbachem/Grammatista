<?php

abstract class GrammatistaParserPhp extends GrammatistaParserTokenizer
{
	
	protected function getStringTokenDefinitions()
	{
		return array(T_STRING, T_CONSTANT_ENCAPSED_STRING);
	}
	
	protected function getDeclareTokenDefinitions()
	{
		return array(T_DECLARE);
	}
	
	protected function getCommentTokenDefinitions()
	{
		return array(T_COMMENT);
	}
	
	protected function getIgnoredTokenDefinitions()
	{
		return array(T_WHITESPACE, T_INLINE_HTML, T_OPEN_TAG, T_CLOSE_TAG);
	}
	
	public function handles(GrammatistaEntity $entity)
	{
		$retval = $entity->type == 'php';
		
		if($retval) {
			return parent::handles($entity);
		}
		
		return $retval;
	}
	
	protected function parsePattern($pattern)
	{
		return parent::parsePattern('<?php ' . $pattern);
	}
	
	protected function getAllTokens($source)
	{
		return token_get_all($source);
	}
	
}

?>