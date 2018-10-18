<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical;

use craft\base\Plugin;
use craft\events\ElementEvent;
use craft\services\Elements;
use ether\critical\models\SettingsModel;
use ether\critical\services\CriticalService;
use ether\critical\web\twig\Extension;
use yii\base\Event;

/**
 * Class Critical
 *
 * @property CriticalService $critical
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

	// Plugin
	// =========================================================================

	public function init ()
	{
		parent::init();

		// TODO: Add ability to (re)gen critical en masse (loop through all elements)
		// TODO: Add ability to clear all critical for a specific template

		// Components
		// ---------------------------------------------------------------------

		$this->setComponents([
			'critical' => CriticalService::class,
		]);

		// Events
		// ---------------------------------------------------------------------

		// TODO: Watch for element moves / saves / deletes to regen critical

		// Twig
		// ---------------------------------------------------------------------

		if (\Craft::$app->request->isSiteRequest)
		{
			// Register Extension
			\Craft::$app->view->registerTwigExtension(new Extension());

			// Register Hook
			\Craft::$app->view->hook('critical-css', [$this, 'onRegisterHook']);
		}
	}

	// Settings
	// =========================================================================

	protected function createSettingsModel ()
	{
		return new SettingsModel();
	}

	// Events
	// =========================================================================

	public function onRegisterHook (/*&$context*/)
	{
		$request = \Craft::$app->request;

		if (
			!$this->getSettings()->criticalEnabled
			|| !$request->isGet
			|| !\Craft::$app->response->isOk
			|| $request->isActionRequest
			|| $request->isLivePreview
		) return;

		$path = $this->critical->uriToTemplatePath(
			$request->absoluteUrl,
			'index.css'
		);

		if (!file_exists($path))
		{
			$this->critical->queueCritical([$request->absoluteUrl]);
			return;
		}

		\Craft::$app->view->registerCss(file_get_contents($path));
	}

}