<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\web\twig;

use Twig_Compiler;

/**
 * Class FoldNode
 *
 * @author  Ether Creative
 * @package ether\critical\web\twig
 */
class FoldNode extends \Twig_Node
{

	public function compile (Twig_Compiler $compiler)
	{
		$compiler
			->write("echo '<!-- LET\'S GET CRITICAL -->';")
			->subcompile($this->getNode('body'))
			->write("echo '<!-- CRITICAL! -->';");
	}

}