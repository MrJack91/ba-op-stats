<?php

namespace Mha\BaOpsStats;
use DateTime;

/**
 * Class ProgressBar
 * User: Michael Hadorn
 * Date: 30.06.16
 * Time: 15:25
 */

class ProgressBar {

	protected $currStep = 0;
	protected $currItem = 0;
	protected $maxSteps = 0;
	protected $maxItems = 0;

	public function init($maxItems, $maxSteps = 100) {
		$this->currItem = 0;
		$this->currStep = 0;
		$this->maxItems = $maxItems;
		$this->maxSteps = $maxSteps;

		echo '<pre>';
		echo '|' . str_repeat('-', $this->maxSteps) . '| 100% (Items: ' . $this->maxItems . ')<br>|';
                self::showOutput();
	}

	public function addStep() {
		$this->currItem++;
                
                // three-sentence: how much is already done; result in perSteps
                $filledSteps = intval($this->currItem * $this->maxSteps / $this->maxItems);
                
                // amount of steps should be filled
		// $percentFull = intval($this->currItem / $this->stepSize);
                
                // $minStep = intval(1 / $step);
                while ($this->currStep < $filledSteps && $filledSteps <= $this->maxSteps) {
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
		ob_flush();
		flush();
	}
}
