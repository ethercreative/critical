<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\web\twig;

/**
 * Class Extension
 *
 * @author  Ether Creative
 * @package ether\critical\web\twig
 */
class Extension extends \Twig_Extension
{

	public function getTokenParsers ()
	{
		return [
			new FoldTokenParser(),
		];
	}

}