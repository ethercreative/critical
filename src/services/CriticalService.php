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
use Sabberworm\CSS\CSSList\AtRuleBlockList;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\CSSList\KeyFrame;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Property\Charset;
use Sabberworm\CSS\Property\Import;
use Sabberworm\CSS\Property\Selector;
use Sabberworm\CSS\RuleSet\AtRuleSet;
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

	private static $_allowedPseudoElements = [
		':before',
		':after',
		':visited',
		':first-letter',
		':first-line',
	];

	private static $_bannedPseudoClasses = [
		':hover',
		':active',
		':focus',
		':focus-visible',
		':focus-within',
	];

	private $_client;
	private $_settings;

	// Fold Tags
	// =========================================================================

	/**
	 * Returns the comments to replace the fold tags with
	 *
	 * @return array
	 */
	public function getFoldComment ()
	{
		return [
			'<!-- Let\'s get Critical, Critical! -->',
			'<!-- I wanna get Critical, Critical! -->',
		];
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

	public function generateCritical ($uri, $progress = null)
	{
		if (!is_callable($progress))
			$progress = function ($step) {};

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
		list($start, $end) = $this->getFoldComment();

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

	private function _critical (
		HTML5DOMDocument $dom,
		Document $css,
		$render = true
	) {
		$critical = new Document();
		$selectorValidator = $this->_buildSelectorValidator();

		// 1. Loop over all CSS blocks
		foreach ($css->getContents() as $contentBlock) {

			// 2. If it's a `@keyframes` block, ignore it.
			if ($contentBlock instanceof KeyFrame)
				continue;

			// 3. If the block is a `@charset` or `@import`, include it.
			if (
				$contentBlock instanceof Charset ||
				$contentBlock instanceof Import
			) {
				$critical->append($contentBlock);
				continue;
			}

			// 4. If the block is an @ rule set...
			if ($contentBlock instanceof AtRuleSet)
			{
				// 4a. If it's `@font-face` check to see if it's in use and
				// include it if it is.
				if ($contentBlock->atRuleName() === 'font-face')
				{
					$rules = $contentBlock->getRulesAssoc();

					if (!array_key_exists('font-family', $rules))
						continue;

					$family = $rules['font-family'];

					// TODO: Check if font is in use

					$critical->append($contentBlock);
					continue;
				}

				// 4b. If it's an unknown @ rule set, include it just in case.
				$critical->append($contentBlock);
				continue;
			}

			// 5. If it's an @ rule block list (`@supports`, `@media`, etc.)...
			if ($contentBlock instanceof AtRuleBlockList)
			{
				// 5a. Get any valid rules inside the rule block.
				$doc = new Document();
				$doc->setContents($contentBlock->getContents());
				$nestedCritical = $this->_critical($dom, $doc, false);

				// 5b. If we have any rules, replace the @ rule contents with
				// those rules and append to critical.
				$nestedContents = $nestedCritical->getContents();

				if (!empty($nestedContents))
				{
					$contentBlock->setContents($nestedContents);
					$critical->append($contentBlock);
				}
			}

			// 6. If it's a declaration (`.a, .b { ... }`)...
			if ($contentBlock instanceof DeclarationBlock)
			{
				// 6a. Skip any empty declarations.
				if (empty($contentBlock->getRules()))
					continue;

				// 6b. Find if any of the selectors are in use.
				$selectorsToKeep = $this->_findInUseSelectors(
					$selectorValidator,
					$dom,
					$contentBlock
				);

				// 6c. If we have any in use selectors add them and the rules
				// to critical.
				if (!empty($selectorsToKeep))
				{
					$contentBlock->setSelectors($selectorsToKeep);
					$critical->append($contentBlock);
				}
			}

		}

		return
			$render
				? $critical->render(OutputFormat::createCompact())
				: $critical;
	}

	private function _findInUseSelectors (
		$selectorValidator,
		HTML5DOMDocument $dom,
		DeclarationBlock $block
	) {
		$selectorsToKeep = [];

		// 1. Loop over each selector and try to find a match in the DOM
		/** @var Selector $rawSelector */
		foreach ($block->getSelectors() as $rawSelector)
		{
			// 2. Convert the selector into something we can work with
			$selector = $selectorValidator($rawSelector->getSelector());

			// 3. If the selector is false, skip it
			if ($selector === false)
				continue;

			// 4. If the selector is true, or if it matches anything in our
			// DOM, store it for later.
			if (
				$selector === true ||
				$dom->querySelectorAll($selector)->count() > 0
			) {
				$selectorsToKeep[] = $rawSelector;
			}
		}

		return $selectorsToKeep;
	}

	private function _buildSelectorValidator ()
	{
		$allowedPseudoElements = $this->_buildPseudoRegex(
			self::$_allowedPseudoElements
		);

		$bannedPseudosClasses = $this->_buildPseudoRegex(
			self::$_bannedPseudoClasses
		);

		return function ($selectorString) use ($allowedPseudoElements, $bannedPseudosClasses) {
			// 1. If there are no pseudos return the selector, return.
			if (strpos($selectorString, ':') === false)
				return $selectorString;

			// 2. If we have any banned pseudo classes, ignore the selector.
			if (preg_match($bannedPseudosClasses, $selectorString) === 1)
				return false;

			// 3. Split the selector into individual parts and loop over them.
			$selectors = explode(' ', $selectorString);

			$i = count($selectors);
			while ($i--)
			{
				$selector = $selectors[$i];

				// 4. Continue if there is no pseudo in this part.
				if (strpos($selector, ':') === false)
					continue;

				// 5. If it's selection ignore it.
				if (preg_match('/:?:(-moz-)?selection/', $selector) === 1)
					return false;

				// 6. Remove any allowed pseudo elements.
				$selector = preg_replace($allowedPseudoElements, '', $selector);

				// 7. If the selector is all pseudo (i.e. ::placeholder), we
				// can't match by it but it may affect styling, so...
				if (preg_replace('/:[:]?([a-zA-Z0-9\-_])*/', '', $selector) === '')
				{
					// 7a. If this is the first selector, just include it.
					if ($i === 0) return true;

					// 7b. Otherwise remove it and carry on.
					unset($selectors[$i]);
					continue;
				}


				// 8. Remove any browser prefixed pseudos (again, we can't
				// select by it, but they may affect styling).
				$selector = preg_replace('/:?:-[a-z-]*/', '', $selector);

				// 9. Store any changes we made to the selector part.
				$selectors[$i] = $selector;
			}

			return trim(implode(' ', $selectors));
		};
	}

	private function _buildPseudoRegex ($pseudos)
	{
		return '/' . trim(
			array_reduce(
				$pseudos,
				function ($a, $b) {
					$a .= ':?' . $b . '|';
					return $a;
				},
				''
			), '|'
		) . '/';
	}

}