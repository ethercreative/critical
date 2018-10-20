<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\web\twig;

use ether\critical\Critical;
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
			->addDebugInfo($this)
			->write('$criticalService = ')
			->raw(Critical::class . '::getInstance()->critical;');

		$compiler
			->write('list($startComment, $endComment) = ')
			->raw('$criticalService->getFoldComment();');

		$compiler
			->write('$shouldRenderFoldTags = $criticalService->shouldRenderFoldTags()');

		if ($this->hasNode('conditions'))
		{
			$compiler
				->raw(' && (')
				->subcompile($this->getNode('conditions'))
				->raw(')');
		}

		$compiler
			->write(';')
			->write('echo $shouldRenderFoldTags ? $startComment : \'\';')
			->subcompile($this->getNode('body'))
			->write('echo $shouldRenderFoldTags ? $endComment : \'\';');
	}

}