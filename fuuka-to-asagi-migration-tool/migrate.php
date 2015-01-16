<?php
/**
 * Fuuka to Asagi Migration 001
 */

date_default_timezone_set('UTC');
error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);

// custom output functions
function dieCli($string) { die($string.PHP_EOL); }
function echoCli($string) { echo '['.date(DATE_ATOM).'] '.$string.PHP_EOL; }
function prntCli($string) { echo $string.PHP_EOL; }

function createBoard($db, $shortname) {
    $boards = file_get_contents('resources/boards.sql');
    $boards = str_replace(array('%%BOARD%%', '%%CHARSET%%'), array($shortname, 'utf8mb4'), $boards);

    $db->query($boards);
    if ($db->error) dieCli('[database error] '.$db->error);
}

function createProcs($db, $shortname) {
    $triggers = file_get_contents('resources/triggers.sql');
    $triggers = str_replace(array('%%BOARD%%'), array($shortname), $triggers);

    $db->multi_query($triggers);
    if ($db->error) dieCli('[database error] '.$db->error);
}

// start now
if (PHP_SAPI != 'cli') {
    dieCli('This file can only be accessed via command line.');
}

if (version_compare(PHP_VERSION, '5.3.0') < 0) {
    dieCli('This migration script is only compatible with PHP 5.3 or higher.');
}


define('BOARD_COLUMNS', 'doc_id, 0, id AS poster_ip, num, subnum, if (parent=0,num,parent) AS thread_num, if (parent=num,1,0) AS op, timestamp, NULL, '.
    'preview AS preview_orig, preview_w, preview_h, media AS media_filename, media_w, media_h, media_size, media_hash, media_filename AS media_orig, '.
    'spoiler, deleted, capcode, email, name, trip, title, comment, delpass, sticky, 0, NULL, NULL, NULL');

// load CLI Class
include 'resources/parameters.php';
$args = new arg_parser(array());

echoCli('
Fuuka to Asagi Migration - 001
==============================
');

if ($args->passed('--help')) { dieCli('
This migration script rebuilds your database tables and image directories to be compatible with Asagi.
It is important that you read the README.me file provided with the script before using it.

Options:
--old-database <name>   This is the name of the old database containing all the data.
--old-path <path>       This is the path to the directory containing all the data.
--board <board>         Process the specified board only.
                        If this argument is not provided, all boards specified in the asagi.json will be processed.
--phase <stage>         Run the specified phase only. [1] Rebuild Tables [2] Rebuild Triggers [3] Rebuild Images [4] Clean Up.
                        If this argument is not provided, all phases will be run sequentially on all selected boards.
--process-thumbs        If you have thumbs stored, this will process the thumbs directory.
--process-images        If you have full images stored, this will process the full images directory.
'); }

$json_file = 'asagi.json';
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

$asagi = $json->settings->boardSettings;
$default = $asagi->default;
unset($asagi->default);

// setup DB connection information and path
$hostname = $default->host;
$username = $default->username;
$password = $default->password;
$new_database = $default->database;
$charset = 'utf8mb4';
$new_path = rtrim($default->path, '/');

// core migration
foreach($asagi as $shortname => $settings) {
    // if --board parameter provided, process a single board only
    if ($args->passed('--board')) {
        if ($shortname != $args->get_full_passed('--board')) {
            continue;
        }
    }

    // connect to DB
    $db = new mysqli($hostname, $username, $password, $new_database);
    if ($db->connect_error) {
        dieCli('Connection Error ('.$db->connect_errno.') '.$db->connect_error);
    }

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

    /**
     * PHASE 1: BUILD NEW TABLES
     */
    if (!$args->passed('--phase') || $args->get_full_passed('--phase') == 1) {
        // this can only be done when --old-database is passed
        if (!$args->passed('--old-database')) {
            dieCli('You must provide the location of the old database.');
        }

        $old_database = $args->get_full_passed('--old-database');

        if ($old_database == $new_database) {
            dieCli('You must create a separate database to contain the new migrated tables.');
        }

        // check if the old database exists
        $check_old_table = $db->query("SHOW TABLE STATUS FROM ".$db->real_escape_string($old_database)." WHERE Name = '".$db->real_escape_string($shortname)."'");
        if ($db->error) {
            dieCli('[database error] '.$db->error);
        }

        if ($check_old_table->num_rows) {
            echoCli('Found: '.$old_database.'.'.$shortname);

            mysqli_free_result($check_old_table);

            // default variables
            $cur_doc_id = 0;
            $max_doc_id = 0;

            // check if the new database exists
            $check_new_table = $db->query("SHOW TABLE STATUS FROM ".$db->real_escape_string($new_database)." WHERE Name = '".$db->real_escape_string($shortname)."'");
            if ($db->error) {
                dieCli('[database error] '.$db->error);
            }

            $state = 'savestate-phase_1_rebuild-'.$shortname;

            if ($check_new_table->num_rows) {
                echoCli('Found: '.$new_database.'.'.$shortname);

                if (file_exists($state)) {
                    $cur_doc_id = intval(file_get_contents($state));
                }
                echoCli('Restarting migration for '.$new_database.'.'.$shortname.' from '.$old_database.'.'.$shortname.' @ '.$cur_doc_id);
            } else {
                echoCli('Creating: '.$new_database.'.'.$shortname);

                createBoard($db, $shortname);
                createProcs($db, $shortname);
                echoCli('Starting migration for '.$new_database.'.'.$shortname.' from '.$old_database.'.'.$shortname);
            }
            mysqli_free_result($check_new_table);

            // we need to determine how large the old database is...
            $max_doc_id_res = $db->query("SELECT MAX(doc_id) AS max FROM \"".$old_database."\".\"".$shortname."\"");
            if ($db->error) {
                dieCli('[database error] '.$db->error);
            }

            while ($row = $max_doc_id_res->fetch_object()) {
                $max_doc_id = $row->max;
            }
            mysqli_free_result($max_doc_id_res);

            // lets start the insertion process
            if ($max_doc_id <= 1000) {
                echoCli('Inserting `'.$old_database.'`.`'.$shortname.'` into `'.$new_database.'`.`'.$shortname.'`.');

                $insert_full_sql = file_get_contents('resources/insert-full.sql');
                $insert_full_sql = str_replace(array('%%BOARD%%', '%%OLD_DATABASE%%', '%%NEW_DATABASE%%'), array($shortname, $old_database, $new_database), $insert_full_sql);

                $db->query($insert_full_sql);
                if ($db->error) {
                    dieCli('[database error] '.$db->error);
                }
            } else {
                echoCli('Inserting `'.$old_database.'`.`'.$shortname.'` into `'.$new_database.'`.`'.$shortname.'`.');

                $insert_sql = file_get_contents('resources/insert-partial.sql');
                $insert_sql = str_replace(array('%%BOARD%%', '%%OLD_DATABASE%%', '%%NEW_DATABASE%%'), array($shortname, $old_database, $new_database), $insert_sql);

                // insert in small chunks
                while ($cur_doc_id + 1000 < $max_doc_id) {
                    echoCli('Inserting `'.$old_database.'`.`'.$shortname.'` into `'.$new_database.'`.`'.$shortname.'` from '.$cur_doc_id.' to '.($cur_doc_id + 1000).'.');
                    file_put_contents($state, $cur_doc_id);

                    $db->query(str_replace(array('%%START_DOC_ID%%', '%%END_DOC_ID%%'), array($cur_doc_id, $cur_doc_id + 1000), $insert_sql));
                    if ($db->error) {
                        dieCli('[database error] '.$db->error);
                    }

                    $cur_doc_id += 1000;
                }

                // insert last chunk again
                $db->query(str_replace(array('%%START_DOC_ID%%', '%%END_DOC_ID%%'), array($cur_doc_id - 1000, $cur_doc_id + 2000), $insert_sql));
                if ($db->error) {
                    dieCli('[database error] '.$db->error);
                }
            }
        } else {
            echoCli('PHASE 1: SKIP!');
            continue;
        }

        echoCli('PHASE 1: COMPLETE!');
    }

    /**
     * PHASE 2: RECREATE TRIGGERS
     */
    if (!$args->passed('--phase') || $args->get_full_passed('--phase') == 2) {
        createProcs($db, $shortname);
        echoCli('PHASE 2: COMPLETE!');
    }

    /**
     * PHASE 3: REBUILD MEDIA DIRECTORY
     */
    if (!$args->passed('--phase') || $args->get_full_passed('--phase') == 3) {
        echoCli('PHASE 3: REBUILDING MEDIA DIRECTORY');

        // this can only be done when --old-path is passed
        if (!$args->passed('--old-path')) {
            dieCli('You must provide the path to the directory containing the old media files.');
        }

        $old_path = $args->get_full_passed('--old-path');

        // default variables
        $cur_doc_id = 0;
        $max_doc_id = 0;

        $state = 'savestate-phase_3_rebuild-'.$shortname;
        $old_work_path = $old_path.'/'.$shortname;
        $new_work_path = $new_path.'/'.$shortname;

        if (file_exists($state)) {
            $cur_doc_id = intval(file_get_contents($state));
        }

        // we need to determine how large the old database is...
        $max_doc_id_res = $db->query("SELECT MAX(doc_id) AS max FROM \"".$new_database."\".\"".$shortname."\"");
        if ($db->error) {
            dieCli('[database error] '.$db->error);
        }

        while ($row = $max_doc_id_res->fetch_object()) {
            $max_doc_id = $row->max;
        }
        $max_doc_len = strlen($max_doc_id);
        mysqli_free_result($max_doc_id_res);

        while ($cur_doc_id < $max_doc_id) {
            file_put_contents($state, $cur_doc_id);

            // init batch
            $temp = array();
            $db->ping();

            $media_res = $db->query("
              SELECT brd.doc_id, brd.media_id, brd.thread_num, brd.op, brd.preview_orig, brd.media_filename, img.media, img.preview_op, img.preview_reply
              FROM \"".$new_database."\".\"".$shortname."\" AS brd
              JOIN \"".$new_database."\".\"".$shortname."_images\" AS img
              ON brd.media_id = img.media_id
              WHERE doc_id >= ".$cur_doc_id." AND doc_id <= ".($cur_doc_id + 1000)."
            ");
            if ($db->error) dieCli('[database error] '.$db->error);

            while ($row = $media_res->fetch_object()) {
                // create the old file structure path with thread_num
                preg_match('/(\d+?)(\d{2})\d{0,3}$/', $row->thread_num, $subpath);
                for ($index = 1; $index <= 2; $index++) {
                    if (!isset($subpath[$index])) $subpath[$index] = '';
                }

                $preview = ($row->op) ? $row->preview_op : $row->preview_reply;
                if ($args->passed('--process-thumbs') && !is_null($preview)) {
                    // make the inner paths
                    $old_image_path_inner = str_pad($subpath[1], 4, "0", STR_PAD_LEFT) . str_pad($subpath[2], 2, "0", STR_PAD_LEFT);
                    $old_image_path_inner = substr($old_image_path_inner, 0, 4) . '/' . substr($old_image_path_inner, 4, 2);
                    $new_image_path_inner = substr($preview, 0, 4) . '/' . substr($preview, 4, 2);

                    // make the full paths
                    $old_image_path = $old_work_path . '/thumb/' . $old_image_path_inner . '/' . $row->preview_orig;
                    $new_image_path = $new_work_path . '/thumb/' . $new_image_path_inner . '/' . $preview;

                    if (!isset($temp['thumb'][$row->media_id][$row->op])) {
                        if (!file_exists($new_image_path)) {
                            @mkdir($new_work_path.'/thumb/'.$new_image_path_inner, 0777, true);
                            if (@copy($old_image_path, $new_image_path)) {
                                $temp['thumb'][$row->media_id][$row->op] = true;
                                echoCli('+ ['.str_pad($row->doc_id, $max_doc_len, "0", STR_PAD_LEFT).']');
                            } else {
                                echoCli('- ['.str_pad($row->doc_id, $max_doc_len, "0", STR_PAD_LEFT).']');
                            }
                        } else {
                            echoCli('= ['.str_pad($row->doc_id, $max_doc_len, "0", STR_PAD_LEFT).']');
                        }
                    } else {
                        echoCli('= ['.str_pad($row->doc_id, $max_doc_len, "0", STR_PAD_LEFT).']');
                    }
                }

                // process that full image!
                if ($args->passed('--process-images') && !is_null($row->media)) {
                    // make the inner paths
                    $old_full_image_path_inner = str_pad($subpath[1], 4, "0", STR_PAD_LEFT) . str_pad($subpath[2], 2, "0", STR_PAD_LEFT);
                    $old_full_image_path_inner = substr($old_full_image_path_inner, 0, 4) . '/' . substr($old_full_image_path_inner, 4, 2);
                    $new_full_image_path_inner = substr($row->media, 0, 4) . '/' . substr($row->media, 4, 2);

                    // make the full paths
                    $old_full_image_path = $old_work_path . '/img/' . $old_full_image_path_inner . '/' . $row->media_filename;
                    $new_full_image_path = $new_work_path . '/image/' . $new_full_image_path_inner . '/' . $row->media;

                    if (!isset($temp['image'][$row->media_id])) {
                        if (!file_exists($new_image_path)) {
                            @mkdir($new_work_path.'/image/'.$new_full_image_path_inner, 0777, true);
                            if (@copy($old_full_image_path, $new_full_image_path)) {
                                $temp['image'][$row->media_id] = true;
                                echoCli('+ ['.str_pad($row->doc_id, $max_doc_len, "0", STR_PAD_LEFT).']');
                            } else {
                                echoCli('- ['.str_pad($row->doc_id, $max_doc_len, "0", STR_PAD_LEFT).']');
                            }
                        } else {
                            echoCli('= ['.str_pad($row->doc_id, $max_doc_len, "0", STR_PAD_LEFT).']');
                        }
                    } else {
                        echoCli('= ['.str_pad($row->doc_id, $max_doc_len, "0", STR_PAD_LEFT).']');
                    }
                }
            }
            mysqli_free_result($media_res);

            $cur_doc_id += 1000;
        }

        echoCli('PHASE 3: COMPLETE!');
    }

    /**
     * PHASE 4: CLEAN UP
     * (THIS HAS BEEN REMOVED)
     */
}
