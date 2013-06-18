<?php

abstract class GrammatistaParserTokenizer extends GrammatistaParser
{
	const TOKEN_PLACEHOLDER = 'placeholder';
	
	const PATTERN_TYPE_SINGULAR = 'singular';
	const PATTERN_TYPE_PLURAL   = 'plural';
	const PATTERN_TYPE_WARNING  = 'warning';
	
	protected $patterns = array();
	
	abstract protected function getStringTokenDefinitions();
	
	abstract protected function getDeclareTokenDefinitions();
	
	abstract protected function getCommentTokenDefinitions();
	
	abstract protected function getIgnoredTokenDefinitions();
	
	public function __construct(array $options = array())
	{
		parent::__construct($options);
		$this->parsePatterns();
	}
	
	public function handles(GrammatistaEntity $entity)
	{
		// By default, handle everything
		Grammatista::dispatchEvent('grammatista.parser.handles', array('entity' => $entity));
		return true;
	}
	
	protected function parsePatterns() {
		foreach($this->options['tokenizer.patterns'] as $pattern => $info) {
			$this->patterns[$pattern] = $info + array(
				'tokens' => $this->parsePattern($pattern),
				'warn' => false,
			);
		}
	}
	
	protected function parsePattern($pattern)
	{
		$tokens = $this->tokenize($pattern);
		
		$retval = array();
		
		for($i = 0; $i < count($tokens); $i++) {
			if(
				is_array($tokens[$i]) && 
				in_array($tokens[$i][0], $this->getDeclareTokenDefinitions()) && 
				isset($tokens[$i+1]) && $tokens[$i+1] === '(' && 
				isset($tokens[$i+2]) && is_array($tokens[$i+2]) && in_array($tokens[$i+2][0], $this->getStringTokenDefinitions()) && 
				isset($tokens[$i+3]) && $tokens[$i+3] === ')' 
			) {
				// Use decodeToken() to strip possible string demarcation characters
				$retval[] = array(0 => self::TOKEN_PLACEHOLDER, 1 => $this->decodeToken($tokens[$i+2]), 2 => $tokens[$i+2][2]);
				$i += 3;
			} else {
				$retval[] = $tokens[$i];
			}
		}
		
		return $retval;
	}
	
	protected abstract function getAllTokens($source);
	
	protected function tokenize($source)
	{
		$tokens = array();
		
		$i = 0;
		foreach($this->getAllTokens($source) as $token) {
			$i++;
			if(is_array($token)) {
				if(in_array($token[0], $this->getCommentTokenDefinitions())) {
					if(preg_match('/^((\/\*+)|#+|\/{2,})\s*(' . preg_quote($this->options['comment_prefix']) . '\s*.+)\s*(?(2)\*\/)$/ms', $token[1], $matches)) {
						// translation comment
						$token[1] = trim($matches[3]);
					} else {
						continue;
					}
				} elseif(in_array($token[0], $this->getIgnoredTokenDefinitions())) {
					continue;
				}
			} else {
				// no array, which means it's a simple character token, such as comma or braces. we combine a sequence of those into one item, because that potentially makes it easier to detect certain imbalances
				if(isset($tokens[$i-1]) && !is_array($tokens[$i-1])) {
					$tokens[--$i] .= $token;
				}
			}
			$tokens[] = $token;
		}
		
		return $tokens;
	}
	
	protected function decodeToken(array $token)
	{
		if(in_array($token[0], $this->getStringTokenDefinitions())) {
			// Use a simple eval() statement to remove string demarcation,
			// suppress E_NOTICE errors here
			$errorLevel = error_reporting();
			error_reporting($errorLevel & ~E_NOTICE);
			$ret = eval('return ' . $token[1] . ';');
			error_reporting($errorLevel);
			return $ret;
		} else {
			return var_export($token[1], true);
		}
	}
	
	protected function findBalance(array $tokens, $index, $lastInPattern = false)
	{
		$balance = 0;
		
		for($i = $index; $i < count($tokens); $i++) {
			if($lastInPattern && ($tokens[$i] == ',' || $tokens[$i] == ')')) {
				break;
			}
			if($tokens[$i] == ',' && $balance == 0) {
				break;
			}
			
			switch($tokens[$i]) {
				case '{':
				case ';':
					break;
				case '(':
					$balance++;
					break;
				case ')':
					$balance--;
					break;
			}
			
			if($balance < 0) {
				$balance = 0;
				break;
			}
		}
		
		// var_dump('<imbalance>', $imbalance, $i, $index, '</imbalance>');
		
		if($balance != 0) {
			throw new GrammatistaException('Unbalanced expression');
		} else {
			return $i - $index;
		}
	}
	
	protected function compareToken(array $tokens, $index)
	{
		foreach($this->patterns as $string => $pattern) {
			$i = $index;
			
			for($j = 0; $j < count($pattern['tokens']); $j++) {
				
				if(is_array($pattern['tokens'][$j]) && $pattern['tokens'][$j][0] == self::TOKEN_PLACEHOLDER) {
					// var_dump('comparing:', $tokens[$i + $j], $pattern['tokens'][$j]);
					// var_dump('placeholder "' . $pattern['tokens'][$j][1] . '"! woot! checking balance...');
					// a placeholder. continue until we have a balanced set of parentheses
					try {
						$skip = $this->findBalance($tokens, $i + $j, $j == (count($pattern['tokens']) - 1));
					} catch(GrammatistaException $e) {
						return false;
						var_dump('imbalance. aborting...');
						// TODO: handle this. doesn't ever happen so far.
					}
					// if($skip > 1 || !is_array($tokens[$i + $j]) || !in_array($tokens[$i + $j][0], array(T_CONSTANT_ENCAPSED_STRING, T_DNUMBER, T_LNUMBER))) {
					// if($skip > 1 || !is_array($tokens[$i + $j]) || (is_array($pattern['placeholders'][$pattern['tokens'][$j][1]]) && !in_array($tokens[$i + $j][0], $pattern['placeholders'][$pattern['tokens'][$j][1]]))) {
					// 	var_dump("no encapsed string. aborting:", $tokens[$i + $j], $skip);
					// 	continue 2;
					// }
					// var_dump('next:', $tokens[$i + $j + $skip], $pattern['tokens'][$j + $skip]);
					$i += $skip - 1;
					continue;
					// $j++;
					// var_dump($skip);
					// var_dump($tokens[$i + $j], $tokens[$i + $j + $skip]);
				}
				
				if(!isset($tokens[$i + $j])) {
					continue 2;
				}
				if(gettype($tokens[$i + $j]) != gettype($pattern['tokens'][$j])) {
					continue 2;
				}
				if(!is_array($tokens[$i + $j]) && $tokens[$i + $j] != $pattern['tokens'][$j]) {
					continue 2;
				}
				if(is_array($tokens[$i + $j]) && $tokens[$i + $j][0] != $pattern['tokens'][$j][0]) {
					continue 2;
				}
				if(is_array($tokens[$i + $j]) && $tokens[$i + $j][1] != $pattern['tokens'][$j][1]) {
					continue 2;
				}
			}
			
			// we must have a statement before the return, otherwise, PHP will segfault - see http://bugs.php.net/bug.php?id=44913
			return $string = $string;
		}
		
		return false;
	}
	
	protected function extractInfo(GrammatistaEntity $entity, array $tokens, $index, $pattern)
	{
		$info = array();
		$i = $index;
		
		for($j = 0; $j < count($pattern['tokens']); $j++) {
			if(is_array($pattern['tokens'][$j]) && $pattern['tokens'][$j][0] == self::TOKEN_PLACEHOLDER) {
				// var_dump('placeholder "' . $pattern['tokens'][$j][1] . '"! woot! checking balance...');
				// a placeholder. continue until we have a balanced set of parentheses
				try {
					$skip = $this->findBalance($tokens, $i + $j, $j == (count($pattern['tokens']) - 1));
				} catch(GrammatistaException $e) {
					var_dump('imbalance. aborting...');
					// TODO: handle this. doesn't ever happen so far.
				}
				// var_dump($tokens[$i + $j], $tokens[$i + $j + $skip]);
				$valid = true;
				
				if($pattern['placeholders'][$pattern['tokens'][$j][1]] === null) {
					// okay, so the argument is valid, but we need to skip it! (e.g. an amount where anything, like $count, is allowed)
					// nothing to do here then
					// var_dump('skipping', $tokens[$i + $j], $skip);
				} elseif($skip == 1 && is_array($pattern['placeholders'][$pattern['tokens'][$j][1]]) && in_array($tokens[$i + $j][0], $pattern['placeholders'][$pattern['tokens'][$j][1]])) {
					// something to extract
					$info[$pattern['tokens'][$j][1]] = $this->decodeToken($tokens[$i + $j]);
					// var_dump('extracting', $tokens[$i + $j], $info[$pattern['tokens'][$j][1]]);
				} else {
					// that didn't go so well...
					$valid = false;
					break;
				}
				// var_dump($skip);
				// if($skip > 1 || !is_array($tokens[$i + $j]) || (is_array($pattern['placeholders'][$pattern['tokens'][$j][1]]) && !in_array($tokens[$i + $j][0], $pattern['placeholders'][$pattern['tokens'][$j][1]]))) {
				// 	// todo: throw warning
				// 	$valid = false;
				// 	break;
				// 	// die('omg');
				// } elseif($pattern['placeholders'][$pattern['tokens'][$j][1]] !== null) {
				// 	// okay, so the argument is valid, but we need to skip it! (e.g. an amount where anything, like $count, is allowed)
				// 	$info[$pattern['tokens'][$j][1]] = $this->decodeToken($tokens[$i + $j]);
				// }
				$i += $skip - 1;
				// var_dump($skip);
			}
		}
		
		// set a default domain
		// patterns that have 'warn' will still fail
		if((!isset($info['domain']) || $info['domain'] === '') && isset($entity->default_domain)) {
			$info['domain'] = $entity->default_domain;
		}
		
		$info = array(
			'singular_message' => isset($info['singular_message']) ? $info['singular_message'] : null,
			'plural_message' => isset($info['plural_message']) ? $info['plural_message'] : null,
			'line' => (int)$tokens[$index][2],
			'domain' => isset($info['domain']) ? $info['domain'] : null,
		);
		
		if($valid && !$pattern['warn']) {
			return new GrammatistaTranslatable($info);
		} else {
			return new GrammatistaWarning($info);
		}
	}
	
	public function parse(GrammatistaEntity $entity)
	{
		Grammatista::dispatchEvent('grammatista.parser.parsing', array('entity' => $entity));
		
		$retval = array();
		
		$tokens = $this->tokenize($entity->content);
		
		$lastComment = null;
		
		for($i = 0; $i < count($tokens); $i++) {
			
			if(isset($tokens[$i][0]) && in_array($tokens[$i][0], $this->getCommentTokenDefinitions())) {
				if(preg_match('/^' . preg_quote($this->options['comment_prefix']) . '\s*(.+)$/', $tokens[$i][1], $matches)) {
					// translation comment? remember it
					$lastComment = $matches[1];
				}
			}
			
			// check if token (and those following) match one of the patterns
			if(($pattern = $this->compareToken($tokens, $i)) !== false) {
				$info = $this->extractInfo($entity, $tokens, $i, $this->patterns[$pattern]);
				$info->comment = $lastComment ? $lastComment : null;
				
				$retval[] = $info;
				
				// $retval[] = new GrammatistaTranslatable(array(
				// 	'singular_message' => $info['singular_message'],
				// 	'plural_message' => isset($info['plural_message']) ? $info['plural_message'] : null,
				// 	'line' => $info['line'] ? (int)$info['line'] : null,
				// 	'comment' => $lastComment ? $lastComment : null,
				// 	'domain' => $info['domain'],
				// ));
				
				// and reset the last comment
				$lastComment = null;
			}
			// // check if token (and those following) match one of the patterns
			// if(($info = $this->compareAndExtract($tokens, $i)) !== null) {
			// 	$retval[] = new GrammatistaTranslatable(array(
			// 		'singular_message' => $info['singular_message'],
			// 		'plural_message' => isset($info['plural_message']) ? $info['plural_message'] : null,
			// 		'line' => $info['line'] ? (int)$info['line'] : null,
			// 		'comment' => $lastComment ? $lastComment : null,
			// 		'domain' => $info['domain'],
			// 	));
			// 	
			// 	// and reset the last comment
			// 	$lastComment = null;
			// }
		}
		
		Grammatista::dispatchEvent('grammatista.parser.parsed', array('entity' => $entity));
		
		return $retval;
	}
}

?>