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

	/**
	 * @var array Order of attributes like in csv
	 */
	protected $fieldMap = array(
		'hasAnesthesia', 'PID', 'Dringlichkeit', 'Wochentag', 'OPDatum', 'OPSaal', 'Reihe', 'FolgeOP', 'Zeitprognose',
		'Bestellzeit', 'ANAStart', 'SaalStart', 'ANABereit', 'OPStart', 'OPEnde', 'PatFreigabe', 'SaalEnde', 'ANAEnde',
		'Hauptoperateur', 'ANAOA', 'ANAArt', 'Zeitverzoegerung', 'Urteil', 'Klinik', 'SGARCode1', 'SGARCode2', 'SGARCode3',
		'Verlegungsort', 'OperateurLevel', 'AnaesthLevel', 'AllgANA', 'RegANA', 'Modus',
		'PatGeb', 'PatGender', 'ASARisk', 'Gewicht', 'Groesse', 'HT', '', 'Raucher', '', 'NI', '',
		'Relevantes',
	);

	protected $fieldParse = array();

	public function trim($val) {
		return trim($val);
	}

	public function intval($val) {
		return intval($val);
	}

	public function nullForEmpty($val) {
		if ($val == '') {
			return null;
		}
		return $val;
	}

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
			return null;
			/*
			echo 'invalid Date: '. $val .'<br>';
			exit();
			*/
		}
	}

	/**
	 * Prase a date into sql valid format
	 * @param $val
	 * @return mixed
	 */
	public function parseDateTime($val) {
		if (strlen($val) == 11) {
			// $val .= '0';
			$val = str_pad($val, 12, '0');
		}
		$date = DateTime::createFromFormat('YmdHi', $val);
		if ($date) {
			return $date->format('Y-m-d H:i:s');
		} else {
			return null;
			/*
			echo 'invalid Date: '. $val .'<br>';
			exit();
			*/
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

			// trims
			'AllgANA' => array(array($this, 'trim')),
			'RegANA' => array(array($this, 'trim')),

			// int
			'PID' => array(array($this, 'intval')),
			'OPSaal' => array(array($this, 'intval')),
			'Reihe' => array(array($this, 'intval')),
			'Zeitprognose' => array(array($this, 'intval')),
			'OperateurLevel' => array(array($this, 'intval')),
			'AnaesthLevel' => array(array($this, 'intval')),
			'PatGender' => array(array($this, 'intval')),
			'ASARisk' => array(array($this, 'intval')),
			'Gewicht' => array(array($this, 'intval')),
			'Groesse' => array(array($this, 'intval')),

			// null for empty string
			'SGARCode1' => array(array($this, 'nullForEmpty')),
			'SGARCode2' => array(array($this, 'nullForEmpty')),
			'SGARCode3' => array(array($this, 'nullForEmpty')),



			'Wochentag' => array(function ($val) {
				switch ($val) {
					case 'Montag':
						return 1;
						break;
					case 'Dienstag':
						return 2;
						break;
					case 'Mittwoch':
						return 3;
						break;
					case 'Donnerstag':
						return 4;
						break;
					case 'Freitag':
						return 5;
						break;
					case 'Samstag':
						return 6;
						break;
					case 'Sonntag':
						return 0;
						break;
				}
				return null;
			}),

			'FolgeOP' => array(function ($val) {
				switch ($val) {
					case 'ja':
						return 1;
						break;
					case 'nein':
						return 0;
						break;
				}
				return null;
			}),

			'hasAnesthesia' => array(function ($val) {
				if ($val == 'mit') {
					return 1;
				}
				return 0;
			}),

			'Zeitverzoegerung' => array(function ($val) {
				if ($val == 'keine Angaben') {
					return null;
				}
				return $val;
			}),

			'ANAArt' => array(function ($val, $fieldName, &$values) {
				switch ($val) {
					case 'AN':
						return 1;
						break;
					case 'RA':
						return 2;
						break;
					case 'MAC':
						return 3;
						break;
					case 'AN+RA':
						return 4;
						break;
					case 'misslungene_RA':
						return 5;
						break;
				}
				return null;
			}),

			'Dringlichkeit' => array(function ($val, $fieldName, &$values) {
				switch ($val) {
					case 'N!':
						return 4;
						break;
					case 'N':
						return 3;
						break;
					case 'oN':
						return 2;
						break;
					case 'E':
						return 1;
						break;
				}
				return null;
			}),

			'Verlegungsort' => array(function ($val, $fieldName, &$values, &$opCsv, &$colPos, &$colParsePos) {
				$valid = array('AWR', 'BS', 'EXT_SP', 'GEST', 'IMC', 'IPS', 'TK', 'UNBEK');
				if (in_array($val, $valid)) {
					if ($val == 'UNBEK') {
						return null;
					}
					return $val;
				} else {
					// there was a transpose offset sgarcode before -> correct
					echo 'sgarCode mismatch: ' . $val . '<br>';
					// reset pointers, rebuild
					$values['SGARCode3'] = null;
					$colPos -= 2;
					$colParsePos -= 1;
				}
				return null; //
			}),

			'Relevantes' => array(function ($val, $fieldName, &$values, &$csv) {
				$booleans = explode('/', $val);
				$addDbFields = array('rel_anamie', 'rel_diabetes', 'rel_adipositas', 'rel_gerinnungsstoerung', 'rel_allergie', 'rel_immunsuppression', 'rel_medikamente', 'rel_malignom', 'rel_schwangerschaft');
				$i = 0;
				if (count($booleans) == 9) {
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
				} else {
					// there was most kindly a col transpose (missing semiclon before: sgarCode or dringlichkeit)
					if (strlen($val) > 0) {
						echo 'assume col transpose or empty relevantes (at relevantes): ' . count($booleans) . ' ' . $val;
						var_dump($values);
						echo '<hr>';
					}
				}
				return false; // do not save col Relevantes only direct attributes
			}),

			'HT' => array(function ($val, $fieldName, &$values, &$csv) {
				$pos = array_search($fieldName, $this->fieldMap);
				$level = $csv[++$pos];

				switch ($level) {
					case 'min':
						$return = 0;
						break;
					case 'maj':
						$return = 1;
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
						$return = 0;
						break;
					case 'maj':
						$return = 1;
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
						$return = 0;
						break;
					case 'maj':
						$return = 1;
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
		);
	}


	/**
	 * Import an operation
	 * @param $opCsv
	 * @return DbMySql|null
	 */
	public function importOp($opCsv, $row) {

		$values = array(
			'csvLinePos' => $row,
			'csvData' => json_encode($opCsv)
		);
		$colPos = 0;
		$colParsePos = 0; // can be offset, if col is missing
		// foreach ($this->fieldMap as $csvPos => $dbField) {
		while ($colParsePos < count($this->fieldMap)) {
			$dbField = $this->fieldMap[$colPos];
			$dbFieldParse = $this->fieldMap[$colParsePos];
			if (isset($opCsv[$colPos])) {
				// ignore cols with empty definition
				if ($dbFieldParse !== '' ) {
					$value = $opCsv[$colPos];

					// do parsing
					$addValueToDb = true;
					if (isset($this->fieldParse[$dbFieldParse])) {
						foreach ($this->fieldParse[$dbFieldParse] as $func) {
							$value = call_user_func_array($func, array($value, $dbFieldParse, &$values, &$opCsv, &$colPos, &$colParsePos));
							if ($value === false) {
								$addValueToDb = false;
							}
						}
					}

					if ($addValueToDb) {
						$values[$dbFieldParse] = $value;
					}
				}
			} else {
				echo 'undefined dbField: ' . $dbFieldParse . ' for pos ' . $colPos . '<br>';
			}
			$colPos++;
			$colParsePos++;
		}

		// TODO: add freitext with all remaining cols

		// TODO: add calculated helper fields: bmi, op duration, last operation of same patient...

		// var_dump($values);


		$this->worker->db->insert('Operation', $values);
		$uid = $this->worker->counter++;

		return $uid;
	}

}