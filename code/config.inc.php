<?php

// max 30min
set_time_limit(1800);
// ini_set('memory_limit', '300M');


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
$config->general->importAmount = 1000;
// $config->general->importAmount = 1000000;
$config->general->doRealCommit = false;

// 'initialImport', 'addAge', 'addReoperation'
$config->general->importTypes = array('addReoperation');

