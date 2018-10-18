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

	public $uris;

	protected function defaultDescription ()
	{
		return 'Generating Critical CSS';
	}

	public function execute ($queue)
	{
		$stepCount = 6;
		$uriCount = count($this->uris);

		$i = $uriCount;
		$totalSteps = $i * $stepCount;

		while ($i--)
		{
			$currentStep = ($uriCount - $i) * $stepCount;

			Critical::getInstance()->critical->generateCritical(
				$this->uris[$i],
				$this->updateProgress($queue, $totalSteps, $currentStep)
			);
		}

		$this->setProgress($queue, 1);
	}

	public function updateProgress ($queue, $totalSteps, $currentStep)
	{
		$self = $this;

		return function ($step) use ($self, $queue, $totalSteps, $currentStep) {
			$self->setProgress($queue, ($currentStep + $step) / $totalSteps);
		};
	}

}