<?php
/**
 * Created by PhpStorm.
 * User: Michael Hadorn
 * Date: 30.06.16
 * Time: 12:13
 */

require_once('Psr/Log/LoggerInterface.php');
require_once('Psr/Log/AbstractLogger.php');
require_once('Psr/Log/LogLevel.php');

require_once('Classes/Logger.php');
require_once('Classes/Utility.php');
require_once('Classes/ProgressBar.php');
require_once('Classes/DbMySql.php');
require_once('Classes/DbHelper.php');
require_once('Classes/Worker.php');


// secure connection credentials
require_once('config.inc.php');

$worker = new \Mha\BaOpsStats\Worker($config);
$worker->run();
