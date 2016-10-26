<?php

namespace Mha\BaOpsStats;
use DateTime;

/**
 * Class DbHelper
 * User: Michael Hadorn
 * Date: 30.06.16
 * Time: 15:25
 * @package Katzgrau\KLogger
 */

class ProgressBar {

	protected $currStep = 0;
	protected $currItem = 0;
	protected $maxSteps = 0;
	protected $maxItems = 0;

	protected $stepSize = 0;

	public function init($maxItems, $maxSteps = 100) {
		$this->currItem = 0;
		$this->currStep = 0;
		$this->maxItems = $maxItems;
		$this->maxSteps = $maxSteps;

		$this->stepSize = $this->maxItems / $this->maxSteps;

		echo '<pre>';
		echo '|' . str_repeat('-', $this->maxSteps) . '| 100%<br>|';
	}

	public function addStep() {
		$this->currItem++;

		$step = intval($this->currItem / $this->stepSize);
		if ($step > $this->currStep) {
			$this->currStep++;
			self::printState();
		}

	}

	public function finish() {
		while ($this->currStep < $this->maxSteps) {
			$this->currStep++;
			self::printState();
		}
		echo '|</pre>';
		self::showOutput();
	}

	static protected function printState() {
		echo '*';
		self::showOutput();
	}

	static protected function showOutput() {
		// ob_flush();
		flush();
	}

}