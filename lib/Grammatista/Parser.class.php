<?php

abstract class GrammatistaParser implements IGrammatistaParser
{
	protected $options = array();
	
	public function __construct(array $options = array())
	{
		$this->options['comment_prefix'] = 'tc:';
		$this->options['ignore_comment'] = 'tc:ignore';
		
		$this->options = array_merge($this->options, $options);
	}
}

?>