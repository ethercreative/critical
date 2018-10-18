<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\web\twig;

use Twig_Error_Syntax;
use Twig_Token;

/**
 * Class FoldTokenParser
 *
 * @author  Ether Creative
 * @package ether\critical\web\twig
 */
class FoldTokenParser extends \Twig_TokenParser
{

	/**
	 * Parses a token and returns a node.
	 *
	 * @param Twig_Token $token
	 *
	 * @return FoldNode A Twig_Node instance
	 * @throws Twig_Error_Syntax
	 */
	public function parse (Twig_Token $token)
	{
		$lineNo = $token->getLine();
		$stream = $this->parser->getStream();

		$nodes = [];
		$attributes = [];

		$stream->expect(Twig_Token::BLOCK_END_TYPE);
		$nodes['body'] = $this->parser->subparse([$this, 'decideEnd'], true);
		$stream->expect(Twig_Token::BLOCK_END_TYPE);

		return new FoldNode($nodes, $attributes, $lineNo, $this->getTag());
	}

	/**
	 * Gets the tag name associated with this token parser.
	 *
	 * @return string The tag name
	 */
	public function getTag ()
	{
		return 'fold';
	}

	public function decideEnd (\Twig_Token $token)
	{
		return $token->test('endfold');
	}

}