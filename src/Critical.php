<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical;

use craft\base\Plugin;
use ether\critical\web\twig\Extension;

/**
 * Class Critical
 *
 * @author  Ether Creative
 * @package ether\critical
 */
class Critical extends Plugin
{

	// Properties
	// =========================================================================

	public $schemaVersion = '0.0.1';
	public $hasCpSettings = false;
	public $hasCpSection  = false;

	// Plugin
	// =========================================================================

	public function init ()
	{
		parent::init();

		if (\Craft::$app->request->getIsSiteRequest())
		{
			$extension = new Extension();
			\Craft::$app->view->registerTwigExtension($extension);
		}
	}

}