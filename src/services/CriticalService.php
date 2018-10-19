<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\services;

use craft\base\Component;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use ether\critical\Critical;
use ether\critical\jobs\CriticalJob;
use ether\critical\models\SettingsModel;
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

	private $_client;
	private $_settings;

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
		return strpos($_SERVER['QUERY_STRING'], 'letsGetCritical') !== false;
	}

	// Critical Generation
	// =========================================================================

	public function queueCritical ($uris)
	{
		$uris = array_filter($uris, [$this, 'filterUris']);

		if (empty($uris))
			return;

		\Craft::$app->queue->push(new CriticalJob(compact('uris')));
	}

	public function generateCritical ($uri, $setProgress = null)
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
		$markup = $this->_markup($uri);

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
		$file = $this->uriToTemplatePath($uri);
		$path = str_replace('/index.css', '', $file);

		if (!file_exists($path))
			mkdir($path, 0777, true);

		file_put_contents($file, $critical);

		$progress(6);
	}

	public function deleteCritical ($uri)
	{
		$path = $this->uriToTemplatePath($uri);

		if (file_exists($path))
			unlink($path);
	}

	// Helpers
	// =========================================================================

	// Helpers: Public
	// -------------------------------------------------------------------------

	public function uriToTemplatePath ($uri, $filename = 'index.css')
	{
		if (strpos($uri, '://') !== false)
			$uri = explode('://', $uri, 2)[1];

		$path = \Craft::getAlias('@templates/');
		$path .= $this->_settings()->criticalFolder . '/';
		$path .= str_replace('?', '/', $uri);
		return FileHelper::normalizePath($path . '/' . $filename);
	}

	private function filterUris ($uri)
	{
		// Ignore index.php requests
		if (strpos($uri, '/index.php') === 0)
			return false;

		// Skip if uri has query string, and we're not including them
		if (!$this->_settings()->includeQueryString &&
		    mb_strpos($uri, '?') !== false)
			return false;

		// Check against excluded patterns
		if (
			$this->_matchPatterns(
				$this->_settings()->excludedUriPatterns,
				$uri
			)
		) return false;

		// Check against included patterns
		if (
			$this->_matchPatterns(
				$this->_settings()->includedUriPatterns,
				$uri
			)
		) return true;

		return false;
	}

	// Helpers: Private
	// -------------------------------------------------------------------------

	private function _client ()
	{
		if ($this->_client)
			return $this->_client;

		return $this->_client = new \GuzzleHttp\Client(['verify' => false]);
	}

	private function _settings (): SettingsModel
	{
		if ($this->_settings)
			return $this->_settings;

		return $this->_settings = Critical::getInstance()->getSettings();
	}

	private function _matchPatterns ($patterns, $uri)
	{
		$i = count($patterns);
		while ($i--)
		{
			$pattern = $patterns[$i];

			if ($pattern === '') continue;

			if (preg_match('#' . trim($pattern, '/') . '#', $uri))
				return true;
		}

		return false;
	}

	private function _markup ($uri)
	{
		/** @noinspection PhpUnhandledExceptionInspection */
		$url    = UrlHelper::url($uri, ['letsGetCritical']);
		$res    = $this->_client()->get($url);
		$status = $res->getStatusCode();

		if ($status < 200 || $status >= 400)
		{
			\Craft::error(
				'Failed to retrieve url: ' . $url,
				'critical-css'
			);
			return null;
		}

		return (string) $res->getBody();
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