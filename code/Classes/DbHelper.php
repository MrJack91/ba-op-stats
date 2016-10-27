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

	protected $stateFlag = 0;

	public function trim($val) {
		return trim($val);
	}

	public function intval($val) {
		$tempVal = intval($val);
		// don't allow negative numbers (example for PIDs)
		if (strlen($val) > 0 && $tempVal == $val && $tempVal >= 0) {
			return $tempVal;
		} else {
			return null;
		}
	}

	public function nullForEmpty($val) {
		if ($val == '') {
			return null;
		} else {
			return $val;
		}
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
						return 1;   // Allgemeinanästhesie
						break;
					case 'RA':
						return 2;   // Regionalanästhesie
						break;
					case 'MAC':
						return 3;   // Monitored Anaesthesia Care
						break;
					case 'AN+RA':
						return 4;   // Kombinationsanästhesie geplant
						break;
					case 'misslungene_RA':
						return 5;   // misslungene Regionalanästhesie
						break;
				}
				return null; // kA as Text
			}),

			'Dringlichkeit' => array(function ($val, $fieldName, &$values) {
				switch ($val) {
					case 'N!':
						return 1;   // OP sehr dringend (unmittelbar notwendig)
						break;
					case 'N':
						return 2;   // OP innerhalb einiger Stunden notwendig; sicher noch am gleichen Tag
						break;
					case 'oN':
						return 3;   // organisatorischer Notfall: OP wurde nachgemeldet, nicht speziell dringend, aber nicht eingeplant
						break;
					case 'E':
						return 4;   // Elektivoperation (geplant)
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
					// note: fix sgarcode3 is missing. skip all cols by one
					/*
					echo 'Verlegungsort mismatch: ' . $val . ' (so SGARCode3 was filled with null)<br>';
					$this->stateFlag = 1;
					*/
					// reset pointers, rebuild
					$values['SGARCode3'] = null; // set sgarcode 3 to null
					// go one col back and add verlegungsort again
					$colPos -= 2;       // one col is missing, this was empty, so sub 2
					$colParsePos -= 1;  // next col is
				}
				return null;
			}),

			'Modus' => array(function ($val, $fieldName, &$values, &$opCsv, &$colPos, &$colParsePos) {
				$valid = array('A/S', 'amb', 'stat'); // ambulant/stationär, ambulant, stationär
				if (in_array($val, $valid)) {
					return $val;
				} else {
					// there was a transpose offset before Modus (mostly same as for verlegungsort)
					echo 'Modus mismatch: ' . $val . '<br>';
					// reset pointers, rebuild
					$this->stateFlag = 1;
				}
				return null;
			}),

			'Relevantes' => array(function ($val, $fieldName, &$values, &$csv) {
				$booleans = explode('/', $val);
				$addDbFields = array('rel_anamie', 'rel_diabetes', 'rel_adipositas', 'rel_gerinnungsstoerung', 'rel_allergie', 'rel_immunsuppression', 'rel_medikamente', 'rel_malignom', 'rel_schwangerschaft');
				$i = 0;
				// if (count($booleans) == 9) {
				if (count($booleans) >= 9) {
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

				// return if person has currently problems
				$return = 0;
				if (strlen($val) > 0) {
					$return = 1;
				}

				// saves problems from past
				$nLevel = null;
				switch ($level) {
					case 'min':
						$nLevel = 1;
						break;
					case 'may':
						$nLevel = 2;
						break;
				}
				$values['HT_problems'] = $nLevel;

				return $return;
			}),

			'Raucher' => array(function ($val, $fieldName, &$values, &$csv) {
				$pos = array_search($fieldName, $this->fieldMap);
				$level = $csv[++$pos];

				// return if person has currently problems
				$return = 0;
				if (strlen($val) > 0) {
					$return = 1;
				}

				// saves problems from past
				$nLevel = null;
				switch ($level) {
					case 'min':
						$nLevel = 1;
						break;
					case 'may':
						$nLevel = 2;
						break;
				}
				$values['Raucher_problems'] = $nLevel;

				return $return;
			}),

			'NI' => array(function ($val, $fieldName, &$values, &$csv) {
				$pos = array_search($fieldName, $this->fieldMap);
				$level = $csv[++$pos];

				// return if person has currently problems
				$return = 0;
				if (strlen($val) > 0) {
					$return = 1;
				}

				// saves problems from past
				$nLevel = null;
				switch ($level) {
					case 'min':
						$nLevel = 1;
						break;
					case 'may':
						$nLevel = 2;
						break;
				}
				$values['NI_problems'] = $nLevel;

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
		$this->stateFlag = 0;
		$values = array(
			'csvLinePos' => $row,
			'csvData' => json_encode($opCsv)
		);

		if (json_last_error() > 0) {
			var_dump(json_last_error());
			var_dump(json_last_error_msg());
			var_dump($row);
			var_dump($opCsv);
			echo '<hr>';
		}

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

		// add freitext with all remaining cols
		$remainingCols = array_slice($opCsv, $colPos);
		$remainingCols = array_filter($remainingCols);
		$values['Freitext'] = implode(';', $remainingCols);

		if ($this->stateFlag == 1) {
			var_dump($values);
		}

		$this->worker->db->insert('Operation', $values);
		$uid = $this->worker->counter++;

		return $uid;
	}


	public function loadAllData($selector = '*', $where = '', $orderBy = 'ops_id', $limit = '') {
		if (strlen($limit) > 0) {
			$limit = 'LIMIT ' . $limit;
		}

		if (strlen($where) > 0) {
			$where = ' AND ' . $where;
		}

		$sql = '
			SELECT ' . $selector . '
			FROM Operation
			WHERE 1=1 ' . $where . '
			ORDER BY ' . $orderBy . '
			' . $limit . ';
		';
		$data = $this->worker->db->exec($sql);
		return $data;
	}

}