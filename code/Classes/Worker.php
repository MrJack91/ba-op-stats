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

	/**
     * @param $config stdClass
     */
    public function __construct($config) {
        $this->config = $config;
        $this->log = new Logger(dirname(__DIR__).'/Logs', LogLevel::DEBUG);

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

        // read and parse csv
        $row = 0;
        if (($handle = fopen('Ressources/Raw/Daten_01012006_bis_30062016.csv', 'r')) !== FALSE) {
        // if (($handle = fopen('Ressources/Raw/Daten_01012006_bis_30062016 copy.csv', 'r')) !== FALSE) {

            // if (($handle = fopen('Ressources/Raw/Daten_01012006_bis_30062016.exp', 'r')) !== FALSE) {
        // if (($handle = fopen('Ressources/Raw/test_sgar.csv', 'r')) !== FALSE) {
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
            }
            fclose($handle);
        }

        if ($this->config->general->doRealCommit) {
            $this->db->transactionCommit();
        } else {
            $this->db->transactionRollback();
        }

        $mainDuration = round(Utility::trackTime('main'), 3);
        var_dump($mainDuration);
    }
}
