<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\services;

use craft\base\Component;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\queue\BaseJob;
use craft\queue\Queue;
use craft\web\View;
use ether\critical\jobs\CriticalJob;
use IvoPetkov\HTML5DOMDocument;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Property\Selector;
use Sabberworm\CSS\RuleSet\DeclarationBlock;

/**
 * Class CriticalService
 *
 * @author  Ether Creative
 * @package ether\critical\services
 */
class CriticalService extends Component
{

	// Properties
	// =========================================================================

	public static $RENDER_TAGS = false;

	private $_client;

	// Fold Tags
	// =========================================================================

	/**
	 * Returns the comments to replace the fold tags with
	 *
	 * @param bool $end
	 *
	 * @return string
	 */
	public function getFoldComment ($end = false)
	{
		if ($end) return '<!-- I wanna get Critical, Critical! -->';

		return '<!-- Let\'s get Critical, Critical! -->';
	}

	/**
	 * Checks whether we should render the fold tags.
	 *
	 * @return bool
	 */
	public function shouldRenderFoldTags ()
	{
		return self::$RENDER_TAGS;
	}

	// Critical Generation
	// =========================================================================

	public function queueCritical ($element)
	{
		// TODO: Support all element types
		if (!$element instanceof Entry)
			return;

		\Craft::$app->queue->push(new CriticalJob([
			'elementId' => $element->id,
		]));
	}

	public function generateCritical ($elementId, $setProgress = null)
	{
		if (is_callable($setProgress)) {
			$progress = function ($step) use ($setProgress) {
				$setProgress($step, 6);
			};
		} else {
			$progress = function ($step) {};
		}

		// 1. Get the markup
		$progress(0);
		$markup = $this->_markup($elementId);

		if ($markup === null)
			return;

		// 2. Get all areas wrapped in Fold tags
		$progress(1);
		$aboveTheFold = $this->_aboveFold($markup);

		// 3. Get CSS
		$progress(2);
		$css = $this->_css($markup);

		if ($css === null)
			return;

		// 4. Get DOM
		$progress(3);
		$dom = $this->_dom($aboveTheFold);

		// 5. Get the critical CSS
		$progress(4);
		$critical = $this->_critical($dom, $css);

		// 6. Save
		$progress(5);
		$path = \Craft::getAlias('@templates/_critical/');
		$file = $elementId . '.css';

		if (!file_exists($path))
			mkdir($path, 0777, true);

		file_put_contents($path . $file, $critical);

		$progress(6);
	}

	// Helpers
	// =========================================================================

	private function _client ()
	{
		if ($this->_client)
			return $this->_client;

		return $this->_client = new \GuzzleHttp\Client(['verify' => false]);
	}

	private function _markup ($entryId)
	{
		// TODO: Support all element types

		$entry = \Craft::$app->entries->getEntryById($entryId);
		$sectionSiteSettings = $entry->section->siteSettings;

		if (
			!isset($sectionSiteSettings[$entry->siteId])
			|| !$sectionSiteSettings[$entry->siteId]->hasUrls
		) null;

		$site = \Craft::$app->sites->getSiteById($entry->siteId);

		if (!$site) null;

		\Craft::$app->sites->setCurrentSite($site);

		$markup = null;

		try {
			\Craft::$app->language = $site->language;
			\Craft::$app->set(
				'locale', \Craft::$app->i18n->getLocaleById($site->language)
			);

			\Craft::$app->elements->setPlaceholderElement($entry);

			$view = \Craft::$app->view;
			$view->twig->disableStrictVariables();

			self::$RENDER_TAGS = true;

			$oldTemplateMode = $view->templateMode;
			$view->setTemplateMode(View::TEMPLATE_MODE_SITE);

			$markup = $view->renderTemplate(
				$sectionSiteSettings[$entry->siteId]->template,
				compact('entry')
			);

			$view->setTemplateMode($oldTemplateMode);

			self::$RENDER_TAGS = false;
		} catch (\Exception $e) {
			\Craft::error(
				'Failed to render markup: ' . $e->getMessage(),
				'critical-css'
			);
			\Craft::error($e);
			return null;
		}

		return $markup;
	}

	private function _aboveFold ($markup)
	{
		$start = $this->getFoldComment();
		$end   = $this->getFoldComment(true);

		preg_match_all(
			'/(?s)(?<=' . $start . ')(.*?)(?=' . $end . ')/m',
			$markup,
			$matches,
			PREG_SET_ORDER,
			0
		);

		return array_reduce($matches, function ($a, $b) {
			$a .= ' ' . $b[0];
			return $a;
		}, '');
	}

	private function _css ($markup)
	{
		preg_match_all(
			'/<link(.*)href="(.+\.css)"(.*)>/m',
			$markup,
			$matches,
			PREG_SET_ORDER,
			0
		);

		// TODO: Make it possible to exclude certain stylesheets

		$cssUrls = array_reduce($matches, function ($a, $b) {
			$a[] = $b[2];
			return $a;
		}, []);

		$client = $this->_client();
		$css = '';

		foreach ($cssUrls as $url)
		{
			try {
				if (!UrlHelper::isAbsoluteUrl($url))
					$url = UrlHelper::siteUrl($url);
			} catch (\Exception $e) {
				\Craft::error(
					'Failed to make URL absolute: ' . $url,
					'critical-css'
				);
				return null;
			}

			$res = $client->get($url);
			$status = $res->getStatusCode();

			if ($status < 200 || $status >= 400)
			{
				\Craft::error(
					'Failed to retrieve stylesheet: ' . $url,
					'critical-css'
				);
				continue;
			}

			$css .= $res->getBody();
		}

		if (empty($css))
		{
			\Craft::error(
				'CSS is empty!',
				'critical-css'
			);
			return null;
		}

		$parser = new Parser($css);

		return $parser->parse();
	}

	private function _dom ($markup)
	{
		$dom = new HTML5DOMDocument();
		$dom->loadHTML('<html><body>' . $markup . '</body></html>');

		return $dom;
	}

	private function _critical (HTML5DOMDocument $dom, Document $css)
	{
		$critical = new Document();

		/** @var DeclarationBlock $block */
		foreach ($css->getAllDeclarationBlocks() as $block)
		{
			/** @var Selector $selector */
			foreach ($block->getSelectors() as $selector)
			{
				if ($dom->querySelectorAll($selector->getSelector())->count() > 0)
				{
					$critical->append($block);
					continue 2;
				}
			}
		}

		return $critical->render(OutputFormat::createCompact());
	}

}