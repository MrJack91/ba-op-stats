<?php

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
/*
$config->general = new stdClass();
$config->general->fileSource = '/data/tx_eosdirectory/';
$config->general->fileTarget = 'public_html/uploads/tx_ossdirectory/imported'; // change this must also be changed in code -> relative path DbHelper/importFirm()
*/
