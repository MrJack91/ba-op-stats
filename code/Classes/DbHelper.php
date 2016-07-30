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

class DbHelper {

	/** @var Worker */
	protected $worker = null;

	protected $fieldMap = array(
		'hasAnesthesia', 'PID', 'Dringlichkeit', 'Wochentag', 'OPDatum', 'OPSaal', 'Reihe', 'FolgeOP', 'Zeitprognose',
		'Bestellzeit', 'ANAStart', 'SaalStart', 'ANABereit', 'OPStart', 'OPEnde', 'PatFreigabe', 'SaalEnde', 'ANAEnde',
		'Hauptoperateur', 'ANAOA', 'ANAArt', 'Zeitverzoegerung', 'Urteil', 'Klinik', 'SGARCode1', 'SGARCode2', 'SGARCode3',
		'Verlegungsort', 'OperateurLevel', 'AnaesthLevel', 'AllgANA', 'RegANA', 'Modus',
		'PatGeb', 'Pat-Gender', 'ASARisk', 'Gewicht', 'Groesse', 'HT', '', 'Raucher', '', 'NI', '',
		'Relevantes',
	);

	protected $fieldParse = array();

	/**
	 * Prase a date into sql valid format
	 * @param $val
	 * @return mixed
	 */
	public function parseDate($val) {
		$date = DateTime::createFromFormat('Ymd', $val);
		if ($date) {
			return $date->format('Y-m-d');
		} else {
			echo 'invalid Date<br>';
			exit();
		}
	}

	/**
	 * Prase a date into sql valid format
	 * @param $val
	 * @return mixed
	 */
	public function parseDateTime($val) {
		$date = DateTime::createFromFormat('YmdHi', $val);
		if ($date) {
			return $date->format('Y-m-d H:i:s');
		} else {
			echo 'invalid Date<br>';
			exit();
		}
	}

	/**
	 * DbHelper constructor.
	 */
	public function __construct($worker) {
		$this->worker = $worker;

		$this->fieldParse = array(
			// date
			'OPDatum' => array(array($this, 'parseDate')),
			'PatGeb' => array(array($this, 'parseDate')),

			// date & times
			'Bestellzeit' => array(array($this, 'parseDateTime')),
			'ANAStart' => array(array($this, 'parseDateTime')),
			'SaalStart' => array(array($this, 'parseDateTime')),
			'ANABereit' => array(array($this, 'parseDateTime')),
			'OPStart' => array(array($this, 'parseDateTime')),
			'OPEnde' => array(array($this, 'parseDateTime')),
			'PatFreigabe' => array(array($this, 'parseDateTime')),
			'SaalEnde' => array(array($this, 'parseDateTime')),
			'ANAEnde' => array(array($this, 'parseDateTime')),

			'Zeitverzoegerung' => array(function ($val) {
				if ($val == 'keine Angaben') {
					return '';
				}
				return $val;
			}),


			'Relevantes' => array(function ($val, $fieldName, &$values) {
				$booleans = explode('/', $val);
				$addDbFields = array('rel_anamie', 'rel_diabetes', 'rel_adipositas', 'rel_gerinnungsstoerung', 'rel_allergie', 'rel_immunsuppression', 'rel_medikamente', 'rel_malignom', 'rel_schwangerschaft',);
				$i = 0;
				foreach ($addDbFields as $addDbField) {
					if (isset($booleans[$i])) {
						$tempVal = $booleans[$i];
						$newVal = '0';
						if ($tempVal == '+') {
							$newVal = '1';
						}
						$values[$addDbField] = $newVal;
					}
					$i++;
				}
				return false;
			}),

			'HT' => array(function ($val, $fieldName, &$values, &$csv) {
				$pos = array_search($fieldName, $this->fieldMap);
				$level = $csv[++$pos];

				switch ($level) {
					case 'min':
						$return = 'min';
						break;
					case 'maj':
						$return = 'maj';
						break;
					default:
						$return = null;
						break;
				}
				/*
				if ($val == '' && $level == 'min') {
					$return = 0;
				} else if ($val == 'HT' && $level == 'min') {
					$return = 1;
				} else if ($val == 'HT' && $level == 'may') {
					$return = 2;
				}
				*/
				return $return;
			}),

			'Raucher' => array(function ($val, $fieldName, &$values, &$csv) {
				$pos = array_search($fieldName, $this->fieldMap);
				$level = $csv[++$pos];
				$return = null;

				switch ($level) {
					case 'min':
						$return = 'min';
						break;
					case 'maj':
						$return = 'maj';
						break;
					default:
						$return = null;
						break;
				}

				/*
				if ($val == 'NR' && $level == 'min') {
					$return = 0;
				} else if ($val == 'R' && $level == 'min') {
					$return = 1;
				} else if ($val == 'R' && $level == 'may') {
					$return = 2;
				}
				*/
				return $return;
			}),

			'NI' => array(function ($val, $fieldName, &$values, &$csv) {
				$pos = array_search($fieldName, $this->fieldMap);
				$level = $csv[++$pos];
				$return = null;

				switch ($level) {
					case 'min':
						$return = 'min';
						break;
					case 'maj':
						$return = 'maj';
						break;
					default:
						$return = null;
						break;
				}

				/*
				if ($val == '' && $level == 'min') {
					$return = 1;
				} else if ($val == 'HT' && $level == 'min') {
					$return = 2;
				} else if ($val == 'HT' && $level == 'may') {
					$return = 3;
				}
				*/
				return $return;
			}),

			'Urteil' => array(function ($val, $fieldName, &$values, &$csv) {
				switch ($val) {
					case 'leichter':
						$val = 1;
						break;
					case 'wie erwartet':
						$val = 2;
						break;
					case 'schwieriger':
						$val = 3;
						break;
					default:
						$val = null;
						break;
				}
				return $val;
			}),

			/*
			ANAArt
			Urteil
			*/
		);
	}


	/**
	 * Import an operation
	 * @param $opCsv
	 * @return DbMySql|null
	 */
	public function importOp($opCsv) {

		$values = array();
		foreach ($this->fieldMap as $csvPos => $dbField) {
			if ($dbField !== '' && isset($opCsv[$csvPos])) {
				$value = $opCsv[$csvPos];

				// do parsing
				$addValueToDb = true;
				if (isset($this->fieldParse[$dbField])) {
					foreach ($this->fieldParse[$dbField] as $func) {
						$value = call_user_func_array($func, array($value, $dbField, &$values, &$opCsv));
						if ($value === false) {
							$addValueToDb = false;
						}
					}
				}

				if ($addValueToDb) {
					$values[$dbField] = $value;
				}
			} else {
				echo 'undefined dbField or skipped by emtpy definition: ' . $dbField . ' for pos ' . $csvPos . '<br>';
			}
		}

		var_dump($values);

		// add freitext with all remaining cols

		// add calculated helper fields: bmi, op duration, ...

		// $this->worker->db->insert('Operation', $values);
		$uid = $this->worker->counter++;

		return $uid;
	}

}