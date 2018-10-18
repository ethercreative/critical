<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\services;

use craft\base\Component;

/**
 * Class CriticalService
 *
 * @author  Ether Creative
 * @package ether\critical\services
 */
class CriticalService extends Component
{

	public function getFoldComment ($end = false)
	{
		if ($end) return '<!-- I wanna get Critical, Critical! -->';

		return '<!-- Let\'s get Critical, Critical! -->';
	}

	public function shouldRenderFoldTags ()
	{
		// TODO: Check if we're accessing the page for Critical generation

		return true;
	}

}