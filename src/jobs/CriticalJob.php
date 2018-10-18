<?php
/**
 * Let's Get Critical
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2018 Ether Creative
 */

namespace ether\critical\jobs;

use craft\queue\BaseJob;
use ether\critical\Critical;

/**
 * Class CriticalJob
 *
 * @author  Ether Creative
 * @package ether\critical\jobs
 */
class CriticalJob extends BaseJob
{

	public $elementId;

	protected function defaultDescription ()
	{
		return 'Generating Critical CSS';
	}

	public function execute ($queue)
	{
		Critical::getInstance()->critical->generateCritical(
			$this->elementId,
			$this->updateProgress($queue)
		);

		$this->setProgress($queue, 1);
	}

	public function updateProgress ($queue)
	{
		$self = $this;

		return function ($step, $totalSteps) use ($self, $queue) {
			$self->setProgress($queue, $step / $totalSteps);
		};
	}

}