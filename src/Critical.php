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
	public $hasCpSection  = false;

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
			Elements::EVENT_AFTER_SAVE_ELEMENT,
			[$this, 'onAfterElementSave']
		);

		// Twig
		// ---------------------------------------------------------------------

		// Register Extension
		\Craft::$app->view->registerTwigExtension(new Extension());

		if (\Craft::$app->request->isSiteRequest) {
			// Register Hook
			\Craft::$app->view->hook('critical-css', [$this, 'onRegisterHook']);
		}
	}

	// Events
	// =========================================================================

	public function onAfterElementSave (ElementEvent $event)
	{
		$this->critical->queueCritical($event->element);
	}

	public function onRegisterHook (&$context)
	{
		if (!array_key_exists('entry', $context))
			return;

		$view = \Craft::$app->view;
		$entryId = $context['entry']->id;

		$view->registerCss(
			$view->renderString(
				'{{ source(\'_critical/' . $entryId . '.css\', ignore_missing=true) }}'
			)
		);
	}

}