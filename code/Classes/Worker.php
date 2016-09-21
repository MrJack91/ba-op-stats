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
        $data = $this->dbHelper->loadAllData('ops_id, OPDatum, PatGeb', $this->config->general->importAmount, 'OPDatum');

        $this->progressBar->init(count($data));


        foreach ($data as $op) {
            $opId = $op['ops_id'];
            $opDate = new \DateTime($op['OPDatum']);
            $patDate = new \DateTime($op['PatGeb']);

            $datediff = $opDate->diff($patDate, true);
            $age = $datediff->y + (1 / 12 * $datediff->m) + (1 / 12 / 30 * $datediff->d);

            $this->db->insert('Operation', array('_PatAge' => $age, '_PatAgeDays' => $datediff->days, '_PatAgeYear' => $datediff->y, '_PatAgeMonth' => $datediff->m, '_PatAgeDay' => $datediff->d), 'WHERE ops_id = ' . $opId);

            $this->progressBar->addStep();
        }

        $this->progressBar->finish();
    }

    protected function typeAddReoperation() {
        $data = $this->dbHelper->loadAllData('ops_id, PID, OPDatum', 30, 'PID');

        var_dump($data);
        exit();

        /*
        foreach ($data as $op) {
            $opId = $op['ops_id'];
            $opDate = new \DateTime($op['OPDatum']);
            $patDate = new \DateTime($op['PatGeb']);

            $datediff = $opDate->diff($patDate, true);
            $age = $datediff->y + (1 / 12 * $datediff->m) + (1 / 12 / 30 * $datediff->d);

            $this->db->insert('Operation', array('_PatAge' => $age, '_PatAgeDays' => $datediff->days, '_PatAgeYear' => $datediff->y, '_PatAgeMonth' => $datediff->m, '_PatAgeDay' => $datediff->d), 'WHERE ops_id = ' . $opId);
        }
        */
    }


    // TODO: add op duration diff, op duration per phase and total, next op in same room, op-group via sgarcode, bmi, last operation of same patient

}
