<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\models;

use craft\base\Model;

/**
 * Class SettingsModel
 *
 * @author  Ether Creative
 * @package ether\critical\models
 */
class SettingsModel extends Model
{

	// Properties
	// =========================================================================

	/**
	 * @var bool Set to false if you don't want Critical CSS to be output
	 *      (useful for dev).
	 *
	 * If you want to manage Critical in your env folder, set this to
	 * `filter_var(getenv('CRITICAL'), FILTER_VALIDATE_BOOLEAN)`
	 * and in your .env file add `CRITICAL=false`.
	 */
	public $criticalEnabled = true;

	/**
	 * @var bool If true, query strings will be treated as unique pages.
	 */
	public $includeQueryString = false;

	/**
	 * @var string Relative path to your critical folder in your templates
	 *      directory.
	 */
	public $criticalFolder = '_critical';

	/**
	 * @var array URI patterns to generate critical CSS for.
	 *      An array of strings, matches PCRE regex syntax.
	 */
	public $includedUriPatterns = [];

	/**
	 * @var array URI patterns to not generate critical CSS for. Takes
	 *      precedence over included patterns.
	 *      An array of strings, matches PCRE regex syntax.
	 */
	public $excludedUriPatterns = [];

}