<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\controllers;

use craft\web\Controller;
use ether\critical\Critical;

/**
 * Class DebugController
 *
 * @author  Ether Creative
 * @package ether\critical\controllers
 */
class DebugController extends Controller
{

	protected $allowAnonymous = true;

	public function actionGenerate ()
	{
		Critical::getInstance()->critical->generateCritical(
			\Craft::$app->sites->currentSite->baseUrl
		);
	}

}