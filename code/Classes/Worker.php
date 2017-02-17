<?php
/**
 * Lauper Computing
 * User: Michael Hadorn <michael.hadorn@laupercomputing.ch>
 * Date: 30.06.16
 * Time: 14:20
 */
namespace Mha\BaOpsStats;

use Katzgrau\KLogger\Logger;
use \PDO;
use Psr\Log\LogLevel;
use Mha\BaOpsStats\Utility;

/**
 * Class Worker
 * @author Michael Hadorn <michael.hadorn@laupercomputing.ch>
 * @package
 * @subpackage
 */
class Worker {

    /** @var stdClass */
    public $config = null;
    /** @var Logger */
    public $log = null;

    /** @var DbMySql|null  */
    public $db = null;

    /** @var DbMySql|null  */
    public $counter = 0;

    public $progressBar = null;

    public $missingSgarCode3 = 0;

	/**
     * @param $config stdClass
     */
    public function __construct($config) {
        $this->config = $config;
        $this->log = new Logger(dirname(__DIR__).'/Logs', LogLevel::DEBUG);

        // progressbar
        $this->progressBar = new ProgressBar();

        // host connect
        $this->db = new DbMySql($this, $this->config->db);
        $this->dbHelper = new DbHelper($this);
    }

    /**
     * start the import
     */
    public function run() {

        Utility::trackTime('main');
        $this->log->info('Import Start', json_decode(json_encode($this->config), true));

        $this->db->transactionBegin();

        foreach ($this->config->general->importTypes as $importType) {

            // call function
            $functionName = 'type'.ucfirst($importType);
            if (method_exists($this, $functionName)) {
                echo 'start import type: '. $importType .'<br>';
                $this->{$functionName}();
                echo 'finished import type: '. $importType .'<br>';
                echo '<hr>';
            } else {
                echo 'type is not defined: '. $importType .'<br>';
            }

        }
        echo '<br>';

        if ($this->config->general->doRealCommit) {
            echo 'Commit ALL<br>';
            $this->db->transactionCommit();
        } else {
            echo 'Rollback ALL<br>';
            $this->db->transactionRollback();
        }
        echo '<br>';

        $mainDuration = round(Utility::trackTime('main'), 3);
        var_dump($mainDuration);
    }

    /* *** Types *** */

    /**
     * Runs the inital import
     */
    protected function typeInitialImport() {
        // read and parse csv
        $row = 0;
        $filepath = 'Resources/Raw/Daten_01012006_bis_30062016.csv';
        // $filepath = 'Resources/Raw/Daten_01012006_bis_30062016_mit_Schmerztherapie.csv';
        if (($handle = fopen($filepath, 'r')) !== FALSE) {
            $lines = min(intval(exec('wc -l ' . $filepath)-1), $this->config->general->importAmount);
            $this->progressBar->init($lines);

            while (($data = fgetcsv($handle, 100000, ';')) !== FALSE AND $row <= $this->config->general->importAmount) {
                $row++;
                // skip head line
                if ($row == 1) continue;
                /*
                $num = count($data);
                echo "<p> $num fields in line $row: <br /></p>\n";
                */

                // insert all records
                $this->dbHelper->importOp($data, $row);

                /*
                for ($c=0; $c < $num; $c++) {
                    echo $c . ': ' . $data[$c] . '<br />'."\n";
                }
                echo '<hr>';
                */
                $this->progressBar->addStep();
            }
            $this->progressBar->finish();
            fclose($handle);
        }
        echo 'fixed sgarcode3: ' . $this->missingSgarCode3 . '<br>';
    }

    /**
     * Cleanup invalid times (zeitprognose, )
     */
    protected function typeCleanupInvalidTimes() {
        $data = $this->dbHelper->loadAllData('ops_id, Zeitprognose, SaalStart, SaalEnde, DATE_FORMAT(SaalStart,\'%H:%i:%s\') as SaalStart_Timeonly, DATE_FORMAT(SaalEnde,\'%H:%i:%s\') as SaalEnde_Timeonly', '', 'OPDatum', $this->config->general->importAmount);
        $this->progressBar->init(count($data));

        foreach ($data as $op) {
            $opId = $op['ops_id'];

            $opPlanned = intval($op['Zeitprognose']);
            if ($opPlanned > 480) {
                $opPlanned = 0;
            }
            $opPlannedOrig = $opPlanned;
            $opPlanned = Utility::roundToAny($opPlanned);
            $opSaalStart = $op['SaalStart'];
            $opSaalEnd = $op['SaalEnde'];
            $opSaalStartTimeonly = $op['SaalStart_Timeonly'];
            $opSaalEndTimeonly = $op['SaalEnde_Timeonly'];

            $opSaalStart = Utility::convertDateTime($opSaalStart);
            $opSaalEnd = Utility::convertDateTime($opSaalEnd);

            // if op planned is 0, convert back to null (avoid down rounding to null)
            if ($opPlanned == 0 && $opPlannedOrig == 0) {
                $opPlanned = null;
            }
            $values = array(
                '_SaalStart' => null,
                '_SaalEnde' => null,
                '_Zeitprognose' => $opPlanned
            );

            // handle times with 00:00:00 as NULLS
            if (!($opSaalStartTimeonly == '00:00:00' OR is_null($opSaalStartTimeonly)) AND !($opSaalEndTimeonly == '00:00:00' OR is_null($opSaalEndTimeonly))) {
                // op start must be smaller
                if ($opSaalStart < $opSaalEnd) {
                    $values['_SaalStart'] = $opSaalStart->format('Y-m-d H:i:s');
                    $values['_SaalEnde'] = $opSaalEnd->format('Y-m-d H:i:s');
                }
            }

            $this->db->insert(
                'Operation',
                $values,
                'WHERE ops_id = ' . $opId
            );

            $this->progressBar->addStep();
        }

        $this->progressBar->finish();


        echo '<br>';
        echo 'mark invalid times<br>';
        // reset all invalid times
        $sql = '
            UPDATE Operation
            SET _invalidTime = 0;
        ';
        $this->db->exec($sql);


        // alle invaliden Zeiten (entspricht allen minus den validen)
        $sql = '
            SELECT ops_id
            FROM Operation
            WHERE ops_id NOT IN (
                SELECT ops_id
                FROM Operation
                WHERE
                    (ANAStart IS NOT NULL) AND (ANABereit IS NOT NULL) AND (ANAEnde IS NOT NULL)
                    AND ANAStart <= ANABereit
                    AND ANABereit <= OPStart
                    AND OPStart <= OPEnde
                    AND OPEnde <= PatFreigabe
                    AND OPEnde <= ANAEnde
                    AND (OPEnde <= _SaalEnde OR _SaalEnde IS NULL)
            )
        ';
        $ops = $this->db->exec($sql);
        $opsIds = array();
        foreach ($ops as $op) {
            $opsIds[]  = intval($op['ops_id']);
        }
        $sql = '
            UPDATE Operation
            SET _invalidTime = 1
            WHERE ops_id IN (
              '.implode(', ', $opsIds).'
            )
        ';
        $this->db->exec($sql);
    }

    /**
     * Calc the age of the patient in different units
     */
    protected function typeAddAge() {
        $data = $this->dbHelper->loadAllData('ops_id, OPDatum, PatGeb', '', 'OPDatum', $this->config->general->importAmount);
        $this->progressBar->init(count($data));

        foreach ($data as $op) {
            $opId = $op['ops_id'];
            $opDate = new \DateTime($op['OPDatum']);
            $patDate = new \DateTime($op['PatGeb']);

            $datediff = $opDate->diff($patDate, true);
            $age = $datediff->y + (1 / 12 * $datediff->m) + (1 / 12 / 30 * $datediff->d);

            $values = array(
                '_PatAge' => $age,
                '_PatAgeDays' => $datediff->days,
                '_PatAgeYear' => $datediff->y,
                '_PatAgeMonth' => $datediff->m,
                '_PatAgeDay' => $datediff->d
            );

            $this->db->insert(
                'Operation',
                $values,
                'WHERE ops_id = ' . $opId
            );

            $this->progressBar->addStep();
        }

        $this->progressBar->finish();
    }

    /**
     * Calculates the duration between last and next operation of the same patient
     */
    protected function typeAddReoperation() {
        // get data ordered by PID, OPDatum
        $data = $this->dbHelper->loadAllData('ops_id, PID, OPDatum', 'PID > 0', 'PID, OPDatum', $this->config->general->importAmount);
        $this->progressBar->init(count($data));

        $op = null;
        $lastOp = null;
        $nextOp = null;
        for ($i = 0; $i < count($data); $i++) {
            $op = $data[$i];
            $opId = $op['ops_id'];
            $opPid = $op['PID'];

            $values = array('_LastOpDays' => null, '_NextOpDays' => null);
            foreach (array('_LastOpDays' => -1, '_NextOpDays' => 1) as $fieldName => $indexAddon) {
                $key = $i+$indexAddon;
                // check if compare records exists in data (skip very first and very last entry)
                if (!array_key_exists($key, $data)) {
                    continue;
                }
                $op2 = $data[$key];

                // check if same person (pid)
                if ($opPid == $op2['PID']) {
                    $diff = $this->calcDateDiff($op, $op2, 'OPDatum');
                    $values[$fieldName] = $diff->days;
                }
            }

            $this->db->insert(
                'Operation',
                $values,
                'WHERE ops_id = ' . $opId
            );

            $this->progressBar->addStep();
        }
        $this->progressBar->finish();
    }

    /**
     * @param \DateTime|String $op1
     * @param \DateTime|String $op1
     * @param string $key
     * @return bool|\DateInterval
     */
    protected function calcDateDiff($op1, $op2, $key = 'OPDatum') {
        $date1 = Utility::convertDateTime($op1[$key]);
        $date2 = Utility::convertDateTime($op2[$key]);
        $datediff = $date1->diff($date2, true);
        return $datediff;
    }

    /**
     * Adds the bmi of every operation
     */
    protected function typeAddBmi() {
        $data = $this->dbHelper->loadAllData('ops_id, Gewicht, Groesse', '', 'OPDatum', $this->config->general->importAmount);
        $this->progressBar->init(count($data));

        foreach ($data as $op) {
            $opId = $op['ops_id'];
            $opWeight = floatval($op['Gewicht']);
            $opHeight = floatval($op['Groesse']);

            $hasFixed = 0;
            $values = array();
            $values['_Gewicht'] = null;
            $values['_Groesse'] = null;
            $values['_GroesseGewichtFixed'] = 0;
            $values['_BMI'] = null;

            if ($opWeight > 0 && $opHeight > 0) {
                $bmi = Utility::bmi($opHeight, $opWeight);
                if ($bmi > 65 || $bmi < 10) {
                    // try to swich the attributes
                    $bmi = Utility::bmi($opWeight, $opHeight);
                    if ($bmi < 65 && $bmi > 10) {
                        // ok the attr are switched, change them
                        $opHeightTemp = $opHeight;
                        $opHeight = $opWeight;
                        $opWeight = $opHeightTemp;
                        $hasFixed = 1;
                    } else {
                        $bmi = null;
                        $opHeight = null;
                        $opWeight = null;
                    }
                }

                if ($opHeight > 30 && $opHeight < 245) {
                    $values['_Groesse'] = $opHeight;
                }
                if ($opWeight > 2 && $opWeight < 250) {
                    $values['_Gewicht'] = $opWeight;
                }
                // all value must be valid, to set bmi
                if (($opHeight > 30 && $opHeight < 250) && ($opWeight > 2 && $opWeight < 250)) {
                    $values['_BMI'] = $bmi;
                    if ($hasFixed == 1) {
                        $values['_GroesseGewichtFixed'] = 1;
                    }
                }
            }
            $this->db->insert(
                'Operation',
                $values,
                'WHERE ops_id = ' . $opId
            );

            $this->progressBar->addStep();
        }

        $this->progressBar->finish();
    }

    /**
     * Adds the time between different operation stages
     */
    protected function typeAddTimeDiff() {
        $data = $this->dbHelper->loadAllData('ops_id, ANAStart, ANABereit, OPStart, OPEnde, PatFreigabe, ANAEnde, _Zeitprognose, _SaalStart, _SaalEnde', '_invalidTime = 0', 'OPDatum', $this->config->general->importAmount);
        $this->progressBar->init(count($data));

        foreach ($data as $op) {
            $opId = $op['ops_id'];

            $opANAStart = $op['ANAStart'];
            $opANABereit = $op['ANABereit'];
            $opStart = $op['OPStart'];
            $opEnd = $op['OPEnde'];
            $opPatFreigabe = $op['PatFreigabe'];
            $opANAEnde = $op['ANAEnde'];
            $opPlanned = $op['_Zeitprognose'];
            $opSaalStart = $op['_SaalStart'];
            $opSaalEnd = $op['_SaalEnde'];


            $opANAStart = Utility::convertDateTime($opANAStart);
            $opANABereit = Utility::convertDateTime($opANABereit);
            $opStart = Utility::convertDateTime($opStart);
            $opEnd = Utility::convertDateTime($opEnd);
            $opSaalStart = Utility::convertDateTime($opSaalStart);
            $opSaalEnd = Utility::convertDateTime($opSaalEnd);
            $opPatFreigabe = Utility::convertDateTime($opPatFreigabe);
            $opANAEnde = Utility::convertDateTime($opANAEnde);

            // waiting time
            $minWaiting = null;
            if ($opANABereit && $opStart) {
                $diff = $opANABereit->diff($opStart);
                $minWaiting = $diff->days*1440 + $diff->h*60 + $diff->i;
                // be sure to get negative values
                if ($diff->invert) {
                    $minWaiting = $minWaiting * (-1);
                }
            }

            // op time (compared with planned)
            $minOp = null;
            $minDiffPlanned = null;
            if ($opStart && $opEnd) {
                $diff = $opStart->diff($opEnd);
                $minOp = $diff->days*1440 + $diff->h*60 + $diff->i;
                if ($diff->invert) {
                    $minOp = $minOp * (-1);
                }

                // skip null values, but allow values with 0
                if (!is_null($opPlanned) && intval($opPlanned) >= 0) {
                    $minDiffPlanned = intval($opPlanned) - $minOp;
                }
            }

            // saal time
            $minSaal = null;
            if (!is_null($opSaalStart) && !is_null($opSaalEnd)) {
                // if set op saal must smaller den op start
                if ($opSaalStart <= $opStart) {
                    $diff = $opSaalStart->diff($opSaalEnd);
                    $minSaal = $diff->days*1440 + $diff->h*60 + $diff->i;
                    if ($diff->invert) {
                        $minSaal = $minSaal * (-1);
                    }
                    // avoid to long ops
                    if ($minSaal > 780) {
                        $minSaal = null;
                    }
                }
            }

            // Vorbereitungszeit (reale Säulenzeit): PatFreigabe - ANABereit
            $minPrepare = null;
            if (!is_null($opANABereit) && !is_null($opPatFreigabe) && $opPatFreigabe->format('H:i:s') !== '00:00:00') {
                $diff = $opANABereit->diff($opPatFreigabe);
                $minPrepare = $diff->days*1440 + $diff->h*60 + $diff->i;
                if ($diff->invert) {
                    $minPrepare = $minPrepare * (-1);
                }
                // avoid to long preparation
                if ($minPrepare == 0 || $minPrepare > 1000) {
                    $minPrepare = null;
                }
            }

            // minimale Säulenzeit: PatFreigabe - SaalStart
            $minMinPillarTime = null;
            if (!is_null($opSaalStart) && !is_null($opPatFreigabe) && $opPatFreigabe->format('H:i:s') !== '00:00:00') {
                $diff = $opSaalStart->diff($opPatFreigabe);
                $minMinPillarTime = $diff->days*1440 + $diff->h*60 + $diff->i;
                if ($diff->invert) {
                    $minMinPillarTime = $minMinPillarTime * (-1);
                }
                // avoid to long preparation
                if ($minMinPillarTime == 0 || $minMinPillarTime > 720) {
                    $minMinPillarTime = null;
                }
            }

            // ANATime: ANAEnde - ANAStart
            $minANATime = null;
            if (!is_null($opANAStart) && !is_null($opANAEnde) && $opANAEnde->format('H:i:s') !== '00:00:00') {
                $diff = $opANAStart->diff($opANAEnde);
                $minANATime = $diff->days*1440 + $diff->h*60 + $diff->i;
                if ($diff->invert) {
                    $minANATime = $minANATime * (-1);
                }
                if ($minANATime == 0) {
                    $minANATime = null;
                }
            }


            $values = array(
                '_time_waiting_ANABereit_to_OPStart' => $minWaiting,
                '_time_Saal' => $minSaal,
                '_time_ANATime' => $minANATime,
                '_time_minPillarTime' => $minMinPillarTime,
                '_time_Prepare' => $minPrepare,
                '_time_OP' => $minOp,
                '_timediff_OP_planned' => $minDiffPlanned
            );

            $this->db->insert(
                'Operation',
                $values,
                'WHERE ops_id = ' . $opId
            );

            $this->progressBar->addStep();
        }

        $this->progressBar->finish();
    }

    /**
     * Mark the first occurred record per pid
     */
    protected function typeMarkFirstPIDRecord() {
        // reset all
        $sql = '
            UPDATE Operation
            SET _firstOccurredByPID = 0;
        ';
        $this->db->exec($sql);

        // search all first occurred
        $sql = '
            SELECT MIN(ops_id) as opsIdToMark, PID
            FROM Operation
            GROUP BY PID
            ORDER BY OPDatum, ops_id
        ';
        $ops = $this->db->exec($sql);
        $opsIds = array();
        foreach ($ops as $op) {
            $opsIds[]  = intval($op['opsIdToMark']);
        }
        // update them
        $sql = '
            UPDATE Operation
            SET _firstOccurredByPID = 1
            WHERE ops_id IN (
              '.implode(', ', $opsIds).'
            )
        ';
        $this->db->exec($sql);
    }


    /**
     * Template for additional type
     */
    protected function typeName() {
        $data = $this->dbHelper->loadAllData('ops_id, ANABereit, OPStart, OPEnde, _Zeitprognose, _SaalStart, _SaalEnde', '_invalidTime = 0', 'OPDatum', $this->config->general->importAmount);
        $this->progressBar->init(count($data));

        foreach ($data as $op) {
            $values = array();
            $opId = $op['ops_id'];

            $this->db->insert(
                'Operation',
                $values,
                'WHERE ops_id = ' . $opId
            );

            $this->progressBar->addStep();
        }

        $this->progressBar->finish();
    }

    protected function typeStats() {
        echo '<pre>';
        for ($i = 0; $i <= 1; $i++) {
            $sql = '
                SELECT count(*) as count
                FROM Operation
                WHERE _LastOpDays = '.$i.'
                AND Dringlichkeit != 4 AND Dringlichkeit IS NOT NULL
            ';
            $return = $this->db->exec($sql);

            echo "$i\t".$return[0]['count']."<br>";
        }
        echo '</pre>';
    }



    protected function typeTimeSeries() {

        $data = $this->db->exec("
            SELECT OPDatum,
                count(*) as 'OPs',
                sum(IF(Dringlichkeit = 1, 1, 0)) as 'N!',
                sum(IF(Dringlichkeit = 2, 1, 0)) as 'N',
                sum(IF(Dringlichkeit = 3, 1, 0)) as 'oN'
            FROM Operation
            WHERE Dringlichkeit_Text IN ('N!', 'N', 'oN')
            GROUP BY OPDatum
            ORDER BY OPDatum
        ");

        $date = new \DateTime('2006-01-01');

        $stop = false;
        $i = 0;
        $cursor = 0;
        $output = array();
        $currentYear = 0;
        while ($stop == false) {
        // while ($i < 500) {
            $i++;

            $opCount = 0;
            $opDate = null;
            if (isset($data[$cursor])) {
                $current = $data[$cursor];
                // var_dump($current);
                $opDate = $current['OPDatum'];
                $opDate = Utility::convertDateTime($opDate);
                if ($opDate == $date) {
                    $opCount = $current['OPs'];
                    $cursor++;
                }
            }

            $printYear = '';
            if ($date->format('Y') !== $currentYear) {
                $currentYear = $date->format('Y');
                $printYear = $currentYear;
            }

            // build output array
            $output[] = array(
                $printYear,
                $i,
                $date->format('d.m.Y'),
                $date->format('d.m.Y'),
                $opCount
            );

            if ($date->format('d.m.Y') == '30.06.2016') {
                $stop = true;
            }
            $date->add(new \DateInterval('P1D'));
        }

        $filepointer = fopen('php://output', 'w');
        foreach ($output as $line) {
            fputcsv($filepointer, $line, ';', "\"");
        }


        exit();

    }

    protected function typeExcelHelper() {

        $dict = [];
        for ($i = 99; $i<=4000; $i++) {
        // for ($i = 99; $i<=4000; $i=$i+365) {
            //for ($i = 8; $i<=127; $i=$i+12) {
            $dict[] = '=E'.$i.'-I$'.$i;
        }

        $val = implode("\n", $dict);

        echo '<textarea cols="200" rows="20">'.$val.'</textarea><br>';

    }

    /**
     * Runs the inital import
     */
    protected function typeInitialImportCheck() {
        // read and parse csv
        $row = 0;
        $filepath = 'Resources/Raw/Daten_01012006_bis_30062016.csv';
        // $filepath = 'Resources/Raw/Daten_01012006_bis_30062016_mit_Schmerztherapie.csv';
        if (($handle = fopen($filepath, 'r')) !== FALSE) {
            $lines = min(intval(exec('wc -l ' . $filepath)-1), $this->config->general->importAmount);
            $this->progressBar->init($lines);

            while (($data = fgetcsv($handle, 100000, ';')) !== FALSE AND $row <= $this->config->general->importAmount) {
                $row++;
                // skip head line
                if ($row == 1) continue;

                if (count($data) <> 47) {
                    echo count($data) . ' ('.$row.')<br>';
                    // var_dump($data);
                }
                // $this->progressBar->addStep();
            }
            $this->progressBar->finish();
            fclose($handle);
        }
        echo 'fixed sgarcode3: ' . $this->missingSgarCode3 . '<br>';
    }


    protected function typeLogParser() {
        echo '<pre>';

        // $filepath = '/Users/michael/Desktop/rapidminer/performance/kmeans/performance-all_10000_4attr.txt';
        $filepath = '/Users/michael/Desktop/rapidminer/performance-kmeans_all_normal.txt';
        $log = file_get_contents($filepath);
        $groupBy = $_REQUEST['groupBy'] ?? 3;

        // inital
        $pattern = "/(?:Davies Bouldin|Example distribution|Avg\. within centroid distance|Avg\. within cluster distance): (\-?\d\.?\d.*)/i";

        echo 'groupBy: ' . $groupBy . "\n";
        echo $filepath . "\n\n";

        preg_match_all($pattern, $log, $matches);

        $i = 0;
        $line = array();
        $result = array();
        foreach ($matches[1] as $match) {
            $line[] = $match;
            $i++;
            if ($i % $groupBy == 0) {
                $result[] = implode("\t", $line);
                $line = array();
            }
        }
        echo implode("\n", $result) . "\n\n";



        var_dump($pattern);
        var_dump($matches);



        echo '</pre>';
    }


    protected function typeTimeSeriesMonthly() {
        echo '<pre>';

        $sql = "
            SELECT DATE_FORMAT(OPDatum, '%y/%m') 'Jahr/Monat',
              DATE_FORMAT(OPDatum, '%y') 'Jahr',
              DATE_FORMAT(OPDatum, '%m') 'Monat',
              count(*) as 'alle OPs'
            FROM Operation
            WHERE Dringlichkeit_Text IN ('N!', 'N', 'oN')
            GROUP BY DATE_FORMAT(OPDatum, '%y/%m')
            ORDER BY DATE_FORMAT(OPDatum, '%y/%m')";

        $data = $this->db->exec($sql);

        $i = 0;
        foreach ($data as $month) {
            $i++;
            $amountOps = $month['alle OPs'];
            $amountDays = cal_days_in_month(CAL_GREGORIAN, $month['Monat'], $month['Jahr']);
            $correctByAmountOfDay = round($amountOps / $amountDays * 30);

            // echo $amountOps . ' over ' . $amountDays . ' in ' . $month['Jahr'] . '/' . $month['Monat'] . " || " . $amountOps . " => " . $correctByAmountOfDay . "\n";
            echo $i . "\t" . $month['Jahr'] . '/' . $month['Monat'] . "\t" . $correctByAmountOfDay . "\n";

        }

        echo '</pre>';
    }

}
