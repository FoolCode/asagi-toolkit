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
--phase <stage>           Run the specified phase only. [1] Import Data [2] Migrate Images.
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