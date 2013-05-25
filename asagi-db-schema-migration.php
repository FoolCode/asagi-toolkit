<?php

/**
 * Asagi DB Schema Migration
 */

// start now
if (PHP_SAPI != 'cli') {
    dieCli('This file can only be accessed via command line.');
}

if (version_compare(PHP_VERSION, '5.3.0') < 0) {
    dieCli('This migration script is only compatible with PHP 5.3 or higher.');
}

error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

// load resources
include 'resources/functions.php';
include 'resources/parameters.php';
$args = new arg_parser(array());

echoCli('
Asagi DB Schema Migration
==============================
');

if ($args->passed('--help')) { dieCli('
This migration script rebuilds your database tables and image directories to be compatible with Asagi.
It is important that you read the README.me file provided with the script before using it.

Options:
--import-database <name>  This is the name of the old database containing all data.
--board <board>           Process the specified board only.
                          If this argument is not provided, all boards specified in the asagi.json will be processed.
--phase <stage>           Run the specified phase only. [1] Alter Table [2] Update Table Data.
                          If this argument is not provided, all phases will be run sequentially on all selected boards.
'); }

$json_file '../asagi.json';
if (!file_exists($json_file)) {
    dieCli('Unable to locate asagi.json file.');
}

// load asagi.json
$json = @json_decode(file_get_contents($json_file));
if (!is_object($json)) {
    dieCli('Your asagi.json file does not appear to be a valid JSON file.');
}

if (!isset($json->settings) || !isset($json->settings->dumperEngine) || !isset($json->settings->sourceEngine) || !isset($json->settings->boardSettings)) {
    dieCli('Your asagi.json file is not properly structured.');
}

// load asagi.json
$json = @json_decode(file_get_contents($json_file));
if (!is_object($json)) {
    dieCli('Your asagi.json file does not appear to be a valid JSON file.');
}

if (!isset($json->settings) || !isset($json->settings->dumperEngine) || !isset($json->settings->sourceEngine) || !isset($json->settings->boardSettings)) {
    dieCli('Your asagi.json file is not properly structured.');
}

$asagi = $json->settings->boardSettings;
$default = $asagi->default;
unset($asagi->default);

// setup DB connection information and path
$board_engine = strtolower($default->engine);
$database = $default->database;
$hostname = $default->host;
$username = $default->username;
$password = $default->password;
$charset = 'utf8mb4';

if (!$args->passed('--migration')) {
    dieCli('You must specify the migration ###.');
}

$migration = str_pad($args->get_full_passed('--migration'), 3, "0", STR_PAD_LEFT);

if (!file_exists('resources/sql/'.$board_engine.'/db-'.$migration.'-boards-alter.sql')) {
	dieCli('You have specified an invalid migration ###.');
}

// core migration
foreach($asagi as $shortname => $settings) {
    // if --board parameter provided, process a single board only
    if ($args->passed('--board')) {
        if ($shortname != $args->get_full_passed('--board'))
            continue;
    }

    // connect to DB
    $db = new mysqli($hostname, $username, $password, $database);
    if ($db->connect_error)
        dieCli('Connection Error ('.$db->connect_errno.') '.$db->connect_error);

    $db->query("SET SESSION sql_mode = 'ANSI'");

    // UTF8MB4 compatibility check
    $check_utf8mb4 = $db->query("SHOW CHARACTER SET WHERE Charset = 'utf8mb4'");
    if ($check_utf8mb4->num_rows) {
        echoCli('Detected MySQL Server with 4-byte character support. Character Set: UTF8MB4.');
    } else {
        $charset = 'utf8';
        echoCli('Detected MySQL Server without 4-byte character support. Character Set: UTF8.');
        echoCli('If you wish to utilize the UTF8MB4 character set, upgrade your MySQL Server to 5.5 or higher.');
    }
    $db->set_charset($charset);

    echoCli('Processing: /'.$shortname.'/');

    /**
     * PHASE 1: ALTER TABLE
     */
    if (!$args->passed('--phase') || $args->get_full_passed('--phase') == 1) {
        echoCli('PHASE 1: ALTER TABLES');

    	// alter
    	$table_sql = file_get_contents('resources/sql/'.$board_engine.'/db-'.$migration.'-boards-alter.sql');
    	$table_sql = str_replace("%%BOARD%%", $shortname, $table_sql);

    	$db->query($table_sql);
    	if ($db->error) dieCli('[database error] '.$db->error);

    	// triggers
    	$triggers_sql = file_get_contents('resources/sql/'.$board_engine.'/db-000-triggers.sql');
    	$triggers_sql = str_replace("%%BOARD%%", $shortname, $triggers_sql);

    	$db->query($triggers_sql);
    	if ($db->error) dieCli('[database error] '.$db->error);

    	echoCli('PHASE 1: COMPLETE!');
    }

    /**
     * PHASE 2: UPDATE TABLE DATA
     */
    if (!$args->passed('--phase') || $args->get_full_passed('--phase') == 1) {
        echoCli('PHASE 2: UPDATE TABLE DATA');

    	$update_sql = file_get_contents('resources/sql/'.$board_engine.'/db-'.$migration.'-boards-update.sql');
    	$update_sql = str_replace("%%BOARD%%", $shortname, $update_sql);

    	$db->query($update);
    	if ($db->error) dieCli('[database error] '.$db->error);

    	echoCli('PHASE 2: COMPLETE!');
    }
}