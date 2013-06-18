<?php

class GrammatistaWriterFilePo extends GrammatistaWriterFile
{
	protected $entries = array();
	
	public function __construct(array $options = array())
	{
		if(!isset($options['file.pattern'])) {
			$this->options['file.pattern'] = '%s.pot';
		}
		
		parent::__construct($options);
		
		$headers = array();
		$headers[] = 'msgid ""';
		$headers[] = 'msgstr ""';
		$headers[] = '"MIME-Version: 1.0\\n"';
		$headers[] = '"Content-Type: text/plain; charset=utf-8\\n"';
		$headers[] = '"Content-Transfer-Encoding: 8bit\\n"';
		
		fwrite($this->fp, implode("\n", $headers) . "\n\n");
	}
	
	/**
	 * Writes to file
	 */
	public function __destruct()
	{
		foreach($this->entries as $entry) {
			parent::writeTranslatable($entry['translatable']);
		}
		
		parent::__destruct();
	}
	
	/**
	 * Queues up all entries to be able to process duplicates etc.
	 */
	public function writeTranslatable(GrammatistaTranslatable $translatable)
	{
		if(!isset($this->entries[$translatable->singular_message])) {
			$this->entries[$translatable->singular_message] = array(
				'extracted-comments' => array(),
				'references'         => array(),
				'translatable'       => $translatable,
			);
		}
		
		$entry =& $this->entries[$translatable->singular_message];
		if($translatable->comment !== null && !in_array($translatable->comment, $entry['extracted-comments'])) {
			$entry['extracted-comments'][] = $translatable->comment;
		}
		$entry['references'][] = $translatable->item_name . ':' . $translatable->line;
		
		Grammatista::dispatchEvent('grammatista.writer.written');
	}
	
	protected function escapeString($string)
	{
		$parts = preg_split('/\\n/', $string);
		
		foreach($parts as &$part) {
			$part = addcslashes($part, "\\\0\n\r\t\"");
		}
		
		$retval = join('\\n"' . "\n" . '"', $parts);
		
		if(count($parts) > 1) {
			$retval = "\"\n\"" . $retval;
		}
		
		return $retval;
	}
	
	protected function formatOutput(GrammatistaTranslatable $translatable)
	{
		$lines = array();
		
		$entry = $this->entries[$translatable->singular_message];
		
		foreach($entry['extracted-comments'] as $comment) {
			$lines[] = '#. ' . preg_replace('/\s+/m', ' ', $comment);
		}
		
		foreach($entry['references'] as $reference) {
			$lines[] = '#: ' . $reference;
		}
		
		$lines[] = sprintf('msgid "%s"', $this->escapeString($translatable->singular_message));
		if($translatable->plural_message !== null) {
			$lines[] = sprintf('msgid_plural "%s"', $this->escapeString($translatable->plural_message));
		}
		
		if($translatable->plural_message !== null) {
			$lines[] = 'msgstr[0] ""';
			$lines[] = 'msgstr[1] ""';
		} else {
			$lines[] = 'msgstr ""';
		}
		
		return implode("\n", $lines) . "\n\n";
	}
}

?>