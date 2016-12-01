<?php

// max 30min
set_time_limit(1800);
ini_set('memory_limit', '300M');


$config = new stdClass();

// home
$config->db = new stdClass();
// development - local test
$config->db->host = 'localhost';
$config->db->user = 'root';
$config->db->pwd = 'root';
$config->db->db = 'ba_op_stats';
$config->db->utf8Decode = false;

// general
$config->general = new stdClass();

// dev
// $config->general->importAmount = 4000;
// $config->general->doRealCommit = false;

// live
$config->general->importAmount = 1000000;
$config->general->doRealCommit = true;

// possible type values: 'initialImport', 'cleanupInvalidTimes', 'addAge', 'addReoperation', 'addBmi', 'addTimeDiff', 'markFirstPIDRecord'
$config->general->importTypes = array('xx');
// $config->general->importTypes = array('initialImport');
// $config->general->importTypes = array('initialImport', 'cleanupInvalidTimes', 'addAge', 'addReoperation', 'addBmi', 'addTimeDiff', 'markFirstPIDRecord');

// $config->general->importTypes = array('stats');