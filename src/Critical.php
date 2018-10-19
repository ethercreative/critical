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

		// Components
		// ---------------------------------------------------------------------

		$this->setComponents([
			'critical' => CriticalService::class,
		]);

		// Events
		// ---------------------------------------------------------------------

		Event::on(
			Elements::class,
			Elements::EVENT_BEFORE_SAVE_ELEMENT,
			[$this, 'onBeforeSaveElement']
		);

		Event::on(
			Elements::class,
			Elements::EVENT_AFTER_SAVE_ELEMENT,
			[$this, 'onAfterSaveElement']
		);

		Event::on(
			Elements::class,
			Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI,
			[$this, 'onBeforeSaveElement']
		);

		Event::on(
			Elements::class,
			Elements::EVENT_BEFORE_DELETE_ELEMENT,
			[$this, 'onBeforeDeleteElement']
		);

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

	public function onBeforeSaveElement (ElementEvent $event)
	{
		if ($event->isNew)
			return;

		$element = $event->element;
		$current = \Craft::$app->elements->getElementById($element->getId());

		if ($url = $current->getUrl())
			$this->critical->deleteCritical($url);

		if ($url = $element->getUrl())
			$this->critical->queueCritical([$url]);
	}

	public function onAfterSaveElement (ElementEvent $event)
	{
		if ($event->isNew && $url = $event->element->getUrl())
			$this->critical->queueCritical([$url]);
	}

	public function onBeforeDeleteElement (ElementEvent $event)
	{
		if ($url = $event->element->getUrl())
			$this->critical->deleteCritical($url);
	}

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