<?php

error_reporting(E_ALL | E_STRICT);

require('../lib/Grammatista.php');

Grammatista::registerScanner('fs', new GrammatistaScannerFilesystem(array('filesystem.path' => realpath(dirname(__FILE__) . '/test1/'))));

Grammatista::registerParser('agxml', array('class' => 'GrammatistaParserXmlAgaviValidation'));
Grammatista::registerParser('gettextphp', array('class' => 'GrammatistaParserPhpAgavi'));
Grammatista::registerParser('agsmarty', array('class' => 'GrammatistaParserPcreSmarty', 'options' => array(
	'pcre.patterns' => array(
		'/\{trans(\s+(domain=(["\']?)(?P<domain>(?(3)((?!(?<!\\\\)\3).)*|[^\s\}]+))\3|[^\s\}"\'=]+=[^\s\}"\']+|[^\s\}=]+=(["\']?)(?(6)((?!(?<!\\\\)\6).)*|[^\s\}]+)\6))*\s*\}\s*(?P<subpattern>.+?)\s*\{\/trans\}/ms' => array( // best so far! doesn't handle domains without quotation marks yet
			'/(\s*\{singular\}(?P<singular_message>.+?)\{\/singular\}|\s*\{plural\}(?P<plural_message>.+?)\{\/plural\})+\s*/s' => true,
			'/(?P<singular_message>.+)/s' => true,
		),
	)
)));

Grammatista::registerStorage('sqlite', new GrammatistaStoragePdo(array('pdo.dsn' => 'sqlite:' . dirname(__FILE__) . '/' . $_SERVER['REQUEST_TIME'] . '.sqlite')));

Grammatista::registerWriter('pot', new GrammatistaWriterFilePo(array('file.basedir' => dirname(__FILE__) . '/' . $_SERVER['REQUEST_TIME'], 'file.pattern' => '%s.pot')));

Grammatista::run();

?>