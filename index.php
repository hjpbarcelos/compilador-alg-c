<?php
include 'Analiser/Lexical.php';
use Analiser\Lexical;
$input = file_get_contents('php://stdin');
$lex = new Lexical($input);
$token = '';
while(!$lex->isEOF()) {
	try {
		$token = $lex->getNextToken();	
		echo $token['lexeme'] . ' - ' . $token['type'] . PHP_EOL;
	} catch (Exception $e) {
		echo $e->getMessage() . PHP_EOL;
	}
}

