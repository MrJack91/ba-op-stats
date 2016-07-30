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
		'PatGeb', 'Pat-Gender', 'ASARisk', 'Gewicht', 'Groesse', 'HT', 'Raucher', 'NI',
		'relevantes',
		'Freitext'
	);

	protected $fieldParse = array();

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

			'relevantes' => array(function ($val) {
				// 'rel_anamie', 'rel_diabetes', 'rel_adipositas', 'rel_gerinnungsstoerung', 'rel_allergie', 'rel_immunsuppression', 'rel_medikamente', 'rel_malignom', 'rel_schwangerschaft',
			})
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
			if (isset($opCsv[$csvPos])) {
				$value = $opCsv[$csvPos];

				// do parsing
				if (isset($this->fieldParse[$dbField])) {
					foreach ($this->fieldParse[$dbField] as $func) {
						$value = call_user_func($func, $value);
					}
				}

				$values[$dbField] = $value;
			} else {
				echo 'undefined dbField: ' . $dbField . ' for pos ' . $csvPos . '<br>';
			}
		}

		var_dump($values);

		// add calculated helper fields: bmi, op duration, ...

		// $this->worker->db->insert('Operation', $values);
		$uid = $this->worker->counter++;

		return $uid;
	}

}