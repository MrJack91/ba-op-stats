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
            $this->db->transactionCommit();
        } else {
            $this->db->transactionRollback();
        }

        $mainDuration = round(Utility::trackTime('main'), 3);
        var_dump($mainDuration);
    }


    protected function typeInitialImport() {
        // read and parse csv
        $row = 0;
        $filepath = 'Ressources/Raw/Daten_01012006_bis_30062016.csv';
        // $filepath = 'Ressources/Raw/Daten_01012006_bis_30062016_mit_Schmerztherapie.csv';
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
    }

    /* *** Types *** */

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

    protected function typeAddReoperation() {
        $data = $this->dbHelper->loadAllData('ops_id, PID, OPDatum', 'PID > 0', 'PID, OPDatum', $this->config->general->importAmount);

        $this->progressBar->init(count($data));

        $op = null;
        $lastOp = null;
        $nextOp = null;
        for ($i = 0; $i < count($data); $i++) {
            $op = $data[$i];
            $opId = $op['ops_id'];
            $opPid = $op['PID'];

            $values = array('_LastOp' => null, '_NextOp' => null);
            foreach (array('_LastOp' => -1, '_NextOp' => 1) as $fieldName => $indexAddon) {
                $key = $i+$indexAddon;
                // check if compare records exists
                if (!array_key_exists($key, $data)) {
                    continue;
                }
                $op2 = $data[$i+$indexAddon];

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

    protected function typeAddBmi() {
        $data = $this->dbHelper->loadAllData('ops_id, Gewicht, Groesse', '', 'OPDatum', $this->config->general->importAmount);

        $this->progressBar->init(count($data));


        foreach ($data as $op) {
            $opId = $op['ops_id'];
            $opWeight = floatval($op['Gewicht']);
            $opHeight = floatval($op['Groesse']);

            $bmi = null;
            if ($opWeight > 0 && $opWeight < 300 && $opHeight > 0 && $opHeight < 250) {
                $opHeight = $opHeight / 100;
                $bmi = $opWeight / pow($opHeight , 2);
            }

            // todo: add bmi validity
            /*
			var_dump($opId);
			echo $opWeight.'/('.$opHeight.'^2) = '.$bmi;
			var_dump($opWeight);
			var_dump($opHeight);
			var_dump($bmi);
			echo '<hr>';
			*/

            $values = array(
                '_BMI' => $bmi
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

    protected function typeAddTimeDiff() {
        $data = $this->dbHelper->loadAllData('ops_id, ANABereit, OPStart, OPEnde, Zeitprognose', '', 'OPDatum', $this->config->general->importAmount);

        $this->progressBar->init(count($data));

        foreach ($data as $op) {
            $opId = $op['ops_id'];

            // waiting time
            $opANABereit = $op['ANABereit'];
            $opStart = $op['OPStart'];
            $opEnd = $op['OPEnde'];
            $opPlanned = intval($op['Zeitprognose']);

            $opANABereit = Utility::convertDateTime($opANABereit);
            $opStart = Utility::convertDateTime($opStart);
            $opEnd = Utility::convertDateTime($opEnd);

            // waiting time
            $minWaiting = null;
            if ($opANABereit && $opStart) {
                $diff = $opANABereit->diff($opStart);
                $minWaiting = $diff->days*1440 + $diff->h*60 + $diff->i;
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

                if ($opPlanned > 0) {
                    $minDiffPlanned = $opPlanned - $minOp;
                }

            }

            $values = array(
                '_time_waiting_ANABereit_to_OPStart' => $minWaiting,
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



    // TODO: add op duration diff to plan, op duration per phase and total, next op in same room, op-group via sgarcode

}
