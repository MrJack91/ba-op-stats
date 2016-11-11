<?php
/**
 * Lauper Computing
 * User: Michael Hadorn <michael.hadorn@laupercomputing.ch>
 * Date: 30.06.16
 * Time: 14:12
 */

namespace Mha\BaOpsStats;

use \PDO;

/**
 * Class DbMySql
 * @author Michael Hadorn <michael.hadorn@laupercomputing.ch>
 * @package
 * @subpackage
 */
class DbMySql {

    /** @var null|PDO  */
    public $host = null;

    /** @var Worker */
    protected $worker = null;

    /** @var bool should every attribute utf8_decoded */
    protected $utf8Decode = false;

    /**
     * init the PDO object
     * @param $worker
     * @param $config
     */
    public function __construct($worker, $config) {
        $this->worker = $worker;
        $this->utf8Decode = $config->utf8Decode;
        try {
            $host = new PDO('mysql:host='.$config->host.';dbname='.$config->db, $config->user, $config->pwd);
            // $host->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
			$host->exec("set names utf8");
            $this->host = $host;
        } catch (\PDOException $e) {
            $error =  'Connection failed: ' . $e->getMessage() . ' (Err: ' . $e->getCode() . ')<br>';
            $this->worker->log->error($error);
            echo $error;
            exit();
        }
    }

    /**
     * cleanup
     * be nice and handle the clutter
     */
    public function __destruct() {
        // disconnect
        $this->host = null;
    }

    /**
     * execute a prepared statement
     * @param $sql string
     * @param array $params for bindings => array(name, value) (the ":" will be added if missing by name)
     * @return \array
     */
    public function exec($sql, $params = array()) {

        // check if params starts with ":". Else add it
        foreach ($params as $key => $val) {
            if (substr($key, 0, 1) !== ':') {
                unset($params[$key]);
                $params[':' . $key] = $val;
            } else {
                break;
            }
        }

        $query = $this->host->prepare($sql); // there must'n have minus in field names
        $query->execute($params);
        if ($query->errorCode() > 0) {
            echo '<span style="background-color: red;">Error in SQL:</span>';
            var_dump($query->errorInfo());
            var_dump(array('Last Query: ', array($sql)));
            var_dump(array('Last Query Params: ', $params));
            $this->worker->log->error('Error in SQL: ', $query->errorInfo());
            $this->worker->log->info('Last Query: ', array($sql));
            $this->worker->log->info('Last Query Params: ', $params);
        }
        $result = $query->fetchAll(PDO::FETCH_ASSOC);

        if ($this->utf8Decode) {
            foreach ($result as &$record) {
                foreach ($record as &$attr) {
                    $attr = utf8_decode($attr);
                }
            }
        }

        return $result;
    }

    /*
    public function query($sql) {
        $result = $this->host->query($sql);

        $data = array();
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $data[] = $row;
        }
        return $data;
    }
    */

    /**
     * manage insert/update
     * @param $table string tablename
     * @param $params array(name, value)
     * @param string $updateWhere if set then update with this where as string
     * @return int inserted id
     */
    public function insert($table, $params, $updateWhere = '') {
        if (strlen($updateWhere) == 0) {
            // insert
            $isNew = true;
            $fields = array_keys($params);
            $sql = '
              INSERT INTO ' . $table .'
                (' . implode(', ', $fields) . ')
              VALUES
                (:' . implode(', :', $fields) . ')
            ;';
        } else {
            // update
            $isNew = false;
            $updateParts = array();
            foreach ($params as $key => $val) {
                $updateParts[] = $key . ' = :' . $key;
            }
            if (count($updateParts) > 0) {
                // cut last ,
                $upatePartString = implode(', ', $updateParts);
                $sql = '
                  UPDATE ' . $table .'
                  SET ' . $upatePartString . '
                  ' . $updateWhere . '
                ;';
            }
        }

        $newId = 0;
        if (strlen($sql) > 0) {
            $this->exec($sql, $params);
            if ($isNew) {
                $newId = $this->host->lastInsertId();
            }
        }

        return $newId;
    }

    public function transactionBegin() {
        // $this->exec('SET autocommit = 0; START TRANSACTION;');
        $this->host->beginTransaction();
    }

    public function transactionCommit() {
        // $this->exec('COMMIT;');
        $this->host->commit();
    }

    public function transactionRollback() {
        // $this->exec('ROLLBACK;');
        $this->host->rollBack();
    }

}
