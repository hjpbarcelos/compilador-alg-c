<?php
namespace Analiser;

require_once 'LexicalException.php';

class Lexical {
	/* Palavras reservadas */
	const T_RES_WORD 		= 'T_RES_WORD';
	/* Símbolos da linguagem */
	const T_SYMBOL 			= 'T_SYMBOL';
	/* Números reais */
	const T_REAL 			= 'T_REAL';
	/* Inteiros em representação binária */
	const T_INTEGER_BIN		= 'T_INTEGER_BIN';
	/* Inteiros em representação octal */
	const T_INTEGER_OCT		= 'T_INTEGER_OCT';
	/* Inteiros em representação decimal */
	const T_INTEGER_DEC		= 'T_INTEGER_DEC';
	/* Inteiros em representação hexadecimal */
	const T_INTEGER_HEX		= 'T_INTEGER_HEX';
	/* Strings */
	const T_STRING 			= 'T_STRING';
	/* Comentários curtos (de 1 linha) */
	const T_SHORT_COMMENT 	= 'T_SHORT_COMMENT';
	/* Comentários multilinha */
	const T_COMMENT 		= 'T_COMMENT';
	/* Identificadores */
	const T_IDENTIFIER 		= 'T_IDENTIFIER';
	/* Espaços em branco */
	const T_WHITESPACE 		= 'T_WHITESPACE';

	// ------------------------------- Static ------------------------------- //	

	/**
	 * Armazena as palavras-chaves da linguagem.
	 *
	 * @var array
	 */
	private static $reservedWords = array(
		// Fluxo do programa
		'programa', 'inicio', 'fim',
		// Declaração de variáveis
		'var', 'real', 'inteiro', 'literal', 'logico', 'matriz', 'fim-var',
		// Valores lógicos
		'verdadeiro', 'falso',
		// Operadores lógicos
		'ou', 'e', 'xou', 'nao',
		// Entrada e saída
		'le', 'escreve',
		// Controle de fluxo
		'enquanto', 'faca', 'fim-enquanto',
		'se', 'entao', 'senao', 'fim-se',
		'para', 'de', 'ate', 'passo', 'fim-para',
		'funcao', 'retorne', 'fim-funcao'
	);

	/**
	 * Armazena os símbolos da linguagem.
	 * Os símbolo .., &&, ||, <>, <=, >=, :=
	 * DEVEM vir antes de ., &, |, <, <, >, : respectivamente.
	 *
	 * @var array
	 */
	private static $symbols = array(
		// Atribuição
		':=',
		// Lógicos
		'||', '|', '&&', '&', '^', '~',
		// Comparação
		'=', '<>', '>=', '<=', '>', '<',
		// Aritméticos
		'+', '-', '*', '/', '%',
		// Vetores, expressões e funções
		'[', ']', '(', ')',
		// Declarações
		':', ',', '..', '.', ';'
	);

	/**
	 * Armazena as expressões regulares de cada tipo de tokens.
	 *
	 * @var array
	 */
	private static $tokenTypesRegExps = array();

	// -------------------------------/Static ------------------------------- //

	/**
	 * Armazena o programa de entrada.
	 *
	 * @var string
	 */
	private $inputProgram;

	/**
	 * Armazena os tokens já identificados pelo analisador.
	 *
	 * @var array
	 */
	private $tokens = array();

	/**
	 * Armazena a posição do cursor no programa de entrada,
	 * permitindo tratar a string como um vetor de caracteres.
	 *
	 * @var int
	 */
	private $cursor = 0;

	/**
	 * Armazena a linha atual no programa de entrada.
	 *
	 * @var int
	 */
	private $line = 1;

	/**
	 * Armazena a coluna atual no programa de entrada.
	 *
	 * @var int
	 */
	private $col = 1;

	/**
	 * Armazena o tamanho da tabulação no arquivo-fonte.
	 * É útil para determinar com precisão algum caractere não reconhecido.
	 *
	 * @var int
	 */
	private $tabSize = 4;

	/**
	 * Cria um analizador léxico a partir do programa de entrada.
	 */
	public function __construct($program) {
		/* rtrim irá remover espaços, tabulações e
		 * quebras de linha do fim do programa.
		 */
		$this->inputProgram = rtrim($program);
	}

	/**
	 * Retorna o tamanho das tabulações informado pelo programador.
	 *
	 * @var int
	 */
	public function getTabSize() {
		return $this->tabSize;
	}

	/**
	 * Seta o tamanho das tabulações.
	 * Útil ao abrir o código fonte num editor que permite variar esse tamanho.
	 *
	 * @return void
	 */
	public function setTabSize($n) {
		if($n <= 0) {
			throw new UnexpectedValueException('Tab size must be > 0, '
				. $n . ' given.');
		}
		$this->tabSize = $n;
	}

	/**
	 * Verifica se o programa de entrada chegou ao fim.
	 *
	 * @return bool
	 */
	public function isEOF() {
		return $this->cursor >= strlen($this->inputProgram);
	}

	/**
	 * Retorna a linha atual no programa-fonte.
	 *
	 * @return int
	 */
	public function getLine() {
		return $this->line;
	}

	/**
	 * Retorna a coluna atual no programa-fonte.
	 *
	 * @return int
	 */
	public function getCol() {
		return $this->col;
	}

	/**
	 * Retorna a posição atual do cursor no programa-fonte.
	 *
	 * @return int
	 */
	public function getCursor() {
		return $this->cursor;
	}

	/**
	 * Retorna o total de tokens já identificados.
	 *
	 * @return int
	 */
	public function getTokenCount() {
		return count($this->tokens);
	}

	/**
	 * Retorna todos os tokens já identificados.
	 *
	 * @return array
	 */
	public function getTokens() {
		return $this->tokens;
	}

	/**
	 * Busca e retorna o próximo token.
	 * O token é representado por um array da forma:
	 * array (
	 * 		lexeme 	=> parte literal do token
	 *		type 	=> tipo do token (ver constantes da classe)
	 *		line	=> a linha onde o token ocorreu
	 *		col		=> a coluna onde o token ocorreu
	 * )
	 *
	 * @return array
	 * @throw Exception : quando um token não for reconhecido ou
	 *					  quando chegamos ao fim do programa-fonte
	 */
	public function getNextToken() {
		// Evita que acessemos posições inválidas dentro da string
		if($this->isEOF()) {
			throw new Exception('Reached end of source-program');
		}

		// Para o compilador, não importam os comentários, logo, ignoramo-los
		$ignore = $this->passThroughComment();
		if($ignore !== null) {
			return $this->getNextToken();
		}

		// A mesma coisa serve para os espaços em branco
		$ignore = $this->passThroughWhitespace();
		if($ignore !== null) {
			return $this->getNextToken();
		}

		/* A ordem de busca é bem importante. Ela pode ser:
		 * Palavra Reservada > Símbolo > Real > Inteiro > String > Identificador
		 *
		 * É preciso tomar cuidado com a ordem de:
		 * 	-> Palavra Reservada x Identificador (Palavra Reservada ANTES)
		 * 	-> Real x Inteiro (Real ANTES)
		 *
		 * Por que isso?
		 * 'se' é uma palavra reservada, mas também é um identificador válido.
		 *
		 * Ao tentar reconhecer 3.4, se procurarmos por um número inteiro antes,
		 * encontraremos 3, depois o símbolo . e depois outro inteiro 4.
		 *
		 * Entre os restantes, a ordem não importa.
		 */
		$token = $this->getReservedWord();
		if($token !== null) {
			return ($this->token[] = $token);
		}

		$token = $this->getSymbol();
		if($token !== null) {
			return ($this->token[] = $token);
		}

		$token = $this->getReal();
		if($token !== null) {
			return ($this->token[] = $token);
		}

		$token = $this->getInteger();
		if($token !== null) {
			return ($this->token[] = $token);
		}

		$token = $this->getString();
		if($token !== null) {
			return ($this->token[] = $token);
		}

		$token = $this->getIdentifier();
		if($token !== null)  {
			return ($this->token[] = $token);
		}

		// Caso nenhum token seja reconhecido...
		throw new LexicalException(sprintf("Unrecognized character %s at l:%d#c:%d",
					$this->inputProgram[$this->cursor++], $this->line, $this->col));
	}

	/**
	 * Procura por espaços em branco (nrt e espaço), que serão ignorados.
	 *
	 * @return array|null
	 */
	private function passThroughWhitespace() {
		if(!isset(self::$tokenTypesRegExps[self::T_WHITESPACE])) {
			// s em uma ER significa [nrt ]
			self::$tokenTypesRegExps[self::T_WHITESPACE] = '#(\s+)#';
		}
		return $this->getLanguageToken(self::T_WHITESPACE);
	}

	/**
	 * Procura por palavras reservadas.
	 *
	 * @return array|null
	 */
	private function getReservedWord() {
		if(!isset(self::$tokenTypesRegExps[self::T_RES_WORD])) {
			/* É importante que as palavras-chaves sejam bem delimitadas.
			 * Por quê?
			 *
			 * Imagine uma variável 'seuNome'. Ao tentar encontrar tokens,
			 * como a procura por palavras-chave precede a busca por
			 * identificadores, o analisador retornará:
			 * 		-> se - T_RES_WORD
			 *		-> uNome - T_INDENTIFIER
			 *
			 * Precisamos garantir que para uma palavra-chave ser reconhecida,
			 * ela seja a "palavra inteira". Para isso, utilizamos o delimitador
			 * 'b' (boundary) ao início e ao fim da ER de busca.
			 *
			 * Exemplo:
			 *	bsolb:
			 *		-> Casa com sol
			 *		-> Não casa com girassol, parassol, etc...
			 *		-> Casa com 'sol' com guarda-sol, isso por que a definição
			 * de b é "qualquer caractere que não seja [0-9A-Za-z_]"
			 */
			self::$tokenTypesRegExps[self::T_RES_WORD] = $this->generateRegExp(self::$reservedWords, '\b');
		}
		return $this->getLanguageToken(self::T_RES_WORD);
	}

	/**
	 * Procura por símbolos da linguagem.
	 *
	 * @return array|null
	 */
	private function getSymbol() {
		if(!isset(self::$tokenTypesRegExps[self::T_SYMBOL])) {
			self::$tokenTypesRegExps[self::T_SYMBOL] = $this->generateRegExp(self::$symbols);
		}
		return $this->getLanguageToken(self::T_SYMBOL);
	}

	/**
	 * Gera uma expressão regular a partir de um array
	 * fazendo a disjunção (|) entre os elementos.
	 *
	 * @param array $list array de termos para a ER.
	 * @param string $delimiter delimitador para os termos, comumente, 'b'
	 *
	 * @return array|null
	 */
	private function generateRegExp(array $list, $delimiter = '') {
		foreach($list as &$each) {
			$each = $delimiter . preg_quote($each, '#') . $delimiter;
		}
		return '#(' . join('|', $list) . ')#';
	}

	/**
	 * Procura por números reais.
	 *
	 * @return array|null
	 */
	private function getReal() {
		if(!isset(self::$tokenTypesRegExps[self::T_REAL])) {
			self::$tokenTypesRegExps[self::T_REAL] = '#([0-9]*\.[0-9]+)#';
		}
		return $this->getLanguageToken(self::T_REAL);
	}

	/**
	 * Procura por números inteiros.
	 *
	 * @return array|null
	 */
	private function getInteger() {
		if(!isset(self::$tokenTypesRegExps[self::T_INTEGER_BIN])) {
			$binInt = '#(?:0(?:b|B)[01]+)#';
			$octInt = '#(?:0[0-7]+)#';
			$decInt = '#(?:[0-9]+)#';
			$hexInt = '#(?:0(?:x|X)[0-9a-fA-F]+)#';

			self::$tokenTypesRegExps[self::T_INTEGER_BIN] = $binInt;
			self::$tokenTypesRegExps[self::T_INTEGER_OCT] = $octInt;
			self::$tokenTypesRegExps[self::T_INTEGER_DEC] = $decInt;
			self::$tokenTypesRegExps[self::T_INTEGER_HEX] = $hexInt;
		}
		
		// Numeros decimais devem ser a última busca.
		return $this->getLanguageToken(self::T_INTEGER_BIN)
			?: $this->getLanguageToken(self::T_INTEGER_OCT)
			?: $this->getLanguageToken(self::T_INTEGER_HEX)
			?: $this->getLanguageToken(self::T_INTEGER_DEC);
	}

	/**
	 * Procura por strings.
	 *
	 * @return array|null
	 */
	private function getString() {
		if(!isset(self::$tokenTypesRegExps[self::T_STRING])) {
			self::$tokenTypesRegExps[self::T_STRING] = '#(\"[^\n]*\")#';
		}
		return $this->getLanguageToken(self::T_STRING);
	}

	/**
	 * Procura por comentários, que serão ignorados.
	 *
	 * @return array|null
	 */
	private function passThroughComment() {
		$sc = $this->getShortComment();
		$c = $this->getComment();
		if(is_array($c) && is_array($sc)) {
			return array_merge($sc, $c);
		} else if(is_array($c)) {
			return $c;
		} else if(is_array($sc)) {
			return $sc;
		} else {
			return null;
		}
	}

	/**
	 * Procura por comentários curtos, do tipo:
	 * 		'// Comentário'
	 *
	 * @return array|null
	 */
	private function getShortComment() {
		if(!isset(self::$tokenTypesRegExps[self::T_SHORT_COMMENT])) {
			self::$tokenTypesRegExps[self::T_SHORT_COMMENT] = '#(//[^\n]*)#';
		}
		return $this->getLanguageToken(self::T_SHORT_COMMENT);
	}

	/**
	 * Procura por comentários multilinha, do tipo:
	 * 		/ * Comentário * /
	 *
	 * @return array|null
	 */
	private function getComment() {
		if(!isset(self::$tokenTypesRegExps[self::T_COMMENT])) {
			self::$tokenTypesRegExps[self::T_COMMENT] = '#(/\*([\s]|.)*\*/)#';
		}
		return $this->getLanguageToken(self::T_COMMENT);
	}

	/**
	 * Procura por identificadores (nomes de variáveis ou funções).
	 *
	 * @return array|null
	 */
	private function getIdentifier() {
		if(!isset(self::$tokenTypesRegExps[self::T_IDENTIFIER])) {
			self::$tokenTypesRegExps[self::T_IDENTIFIER] = '#([a-zA-Z_][a-zA-Z0-9_]*)#';
		}
		return $this->getLanguageToken(self::T_IDENTIFIER);
	}

	/**
	 * Faz o "trabalho" sujo de procurar um token do tipo $type.
	 *
	 * @param $type : o tipo de token para buscar (veja as constantes de classe)
	 * @return null | array;
	 */
	private function getLanguageToken($type) {
		// Escolhe a expressão regular requerida
		$regExp = self::$tokenTypesRegExps[$type];

		/* Pega uma parte do programa principal para análise,
		 * a partir do cursor, para não procurar tokens já identificados.
		 */
		$slice = substr($this->inputProgram, $this->cursor);

		$matches;

		// Usamos PREG_OFFSET_CAPTURE para saber a posição onde o token ocorre.
		if(preg_match($regExp, $slice, $matches, PREG_OFFSET_CAPTURE)) {
			/* Caso a expressão case com alguma parte do programa,
			 * o índice 0 do array $matches contém a string casada.
			 *
			 * Dentro de $matches[0], o índice [1] representa a posição
			 * identificada por PREG_OFFSET_CAPTURE...
			 */
			$pos = $matches[0][1];

			/* Só prosseguiremos com a análise caso a ER case com o começo do
			 * restante do programa, se não estamos deixando tokens para trás.
			 */
			if($pos == 0) {
				/*
				 * Dentro de $matches[0], o índice [0] representa
				 * a string casada com a ER.
				 */
				$lexeme = $matches[0][0];

				// Armazenamos a linha e a coluna atuais...
				$line = $this->line;
				$col = $this->col;

				/*
				 * O tratamento abaixo se deve única e exclusivamente para
				 * acertar a posição (linha e coluna) no programa.
				 */
				if($type == self::T_WHITESPACE) {
					$lf = "\n";
					$posLf = strpos($lexeme, $lf);
					$lastPosLf = strrpos($lexeme, $lf);

					/* Caso haja quebras de linha, precisamos incrementar
					 * o valor da linha e resetar o valor da coluna.
					 */
					if($posLf !== false) {
						$this->line += substr_count($lexeme, $lf);
						$this->col = 1;
					}

					$tab = "\t";
					$posTab = strpos($lexeme, $tab);

					/* Caso haja tabulações, precisamos contar quantas
					 * existem DEPOIS da última quebra de linha e somar esse
					 * valor multiplicado pelo fator tabSize à coluna atual.
					 */
					if($posTab !== false) {
						$this->col += $this->tabSize *
									  substr_count($lexeme, $tab, $lastPosLf);
					} 

					/* Caso haja espaços, precisamos contar quantos
					 * existem DEPOIS da última quebra de linha e somar esse
					 * valor à coluna atual.
					 */
					$space = " ";
					$spacePos = strpos($lexeme, $space);
					if($spacePos !== false) {
						$this->col += substr_count($lexeme, $space, $lastPosLf);
					}
				} else {
					/* Se não for espaço em branco, apenas somamos
					 * o tamanho do token à coluna.
					 */
					$this->col += strlen($lexeme);
				}

				// Incrementamos o cusor
				$this->cursor += strlen($lexeme);

				return array('lexeme' => $lexeme,
							 'line'	  => $line,
							 'col'	  => $col,
							 'type'	  => $type);
			}
		}
		return null;
	}
}

