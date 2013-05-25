<?php
/**
 * Fuuka to Asagi Migration
 */

function createBoard($board_engine, $database, $board) {
    $boards = file_get_contents('resources/sql/'.$board_engine.'/db-000-boards.sql');
    $boards = str_replace(array('%%BOARD%%'), array($shortname), $boards);

    $db->query($boards);
    if ($db->error) dieCli('[database error] '.$db->error);

    $triggers = file_get_contents('resources/sql/'.$board_engine.'/db-000-triggers.sql');
    $triggers = str_replace(array('%%BOARD%%'), array($shortname), $triggers);

    $db->query($triggers);
    if ($db->error) dieCli('[database error] '.$db->error);
}

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
Fuuka to Asagi Migration - 001
==============================
');

if ($args->passed('--help')) { dieCli('
This migration script rebuilds your database tables and image directories to be compatible with Asagi.
It is important that you read the README.me file provided with the script before using it.

Options:
--old-database <name>   This is the name of the old database containing all data.
--board <board>         Process the specified board only.
                        If this argument is not provided, all boards specified in the asagi.json will be processed.
--phase <stage>         Run the specified phase only. [1] Rebuild Tables [2] Rebuild Images.
                        If this argument is not provided, all phases will be run sequentially on all selected boards.
--full-images           If you have full images stored, this will process the full image directory as well.
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

$asagi = $json->settings->boardSettings;
$default = $asagi->default;
unset($asagi->default);

// setup DB connection information and path
$board_engine = strtolower($default->engine);
$new_database = $default->database;
$hostname = $default->host;
$username = $default->username;
$password = $default->password;
$charset = 'utf8mb4';
$path = rtrim($default->path, '/');
$old_path = rtrim($default->oldpath, '/');

// core migration
foreach($asagi as $shortname => $settings) {
    // if --board parameter provided, process a single board only
    if ($args->passed('--board')) {
        if ($shortname != $args->get_full_passed('--board'))
            continue;
    }

    // connect to DB
    $db = new mysqli($hostname, $username, $password, $new_database);
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
    * PHASE 1: BUILD NEW TABLES
    */
    if (!$args->passed('--phase') || $args->get_full_passed('--phase') == 1) {
        echoCli('PHASE 1: REBUILD TABLES');

        // this can only be done when --old-database is passed
        if (!$args->passed('--old-database')) {
            dieCli('Unable to locate asagi.json file.');
        }

        $old_database = $args->get_full_passed('--old-database');

        if ($old_database == $new_database) {
            dieCli('You must create a separate database to contain the new migrated tables.');
        }

        // check if the old database exists
        $check_old_table = $db->query("SHOW TABLES STATUS ".$db->real_escape_string($old_database)." WHERE Name = '".$db->real_escape_string($shortname)."'");
        if ($db->error) dieCli('[database error] '.$db->error);

        if ($check_old_table->num_rows) {
            echoCli('Found: '.$old_database.'.'.$shortname);

            mysqli_free_result($check_old_table);

            // default variables
            $cur_doc_id = 0;
            $max_doc_id = 0;

            // check if the new database exists
            $check_new_table = $db->query("SHOW TABLES STATUS '".$db->real_escape_string($new_database)."' WHERE Name = '".$db->real_escape_string($shortname)."'");
            if ($db->error) dieCli('[database error] '.$db->error);

            if ($check_new_table->num_rows) {
                echoCli('Found: '.$new_database.'.'.$shortname);

                $cur_doc_id_res = $db->query("SELECT MAX(doc_id) AS max FROM \"".$new_database."\".\"".$shortname."\"");
                if ($db->error) dieCli('[database error] '.$db->error);

                while ($row = $cur_doc_id_res->fetch_object()) {
                    $cur_doc_id = $row->max;
                }
                mysqli_free_result($cur_doc_id_res);

                $cur_doc_id = floor($cur_doc_id/200000)*200000;
                echoCli('Restarting migration for '.$new_database.'.'.$shortname.' row '.$cur_doc_id);
            } else {
                echoCli('Creating: '.$new_database.'.'.$shortname);

                createBoard($board_engine, $new_database, $shortname);
                echoCli('Starting migration for '.$new_database.'.'.$shortname);
            }
            mysqli_free_result($check_new_table);

            // we need to determine how large the old database is...
            $max_doc_id_res = $db->query("SELECT MAX(doc_id) AS max FROM \"".$new_database."\".\"".$shortname."\"");
            if ($db->error) dieCli('[database error] '.$db->error);

            while ($row = $max_doc_id_res->fetch_object()) {
                $max_doc_id = $row->max;
            }
            mysqli_free_result($max_doc_id_res);

            echoCli('Inserting `'.$new_database.'`.`'.$shortname.'` into `'.$new_database.'`.`'.$shortname.'`.');

            // lets start the insertion process
            if ($max_doc_id <= 200000) {
                $insert_full_sql = file_get_contents('resources/sql/'.$board_engine.'/db-000-insert-full.sql');
                $insert_full_sql = str_replace(array('%%BOARD%%', '%%OLD_DATABASE%%', '%%NEW_DATABASE%%'), array($shortname, $old_database, $new_database), $insert_full_sql);

                $db->query($insert_full_sql);
                if ($db->error) dieCli('[database error] '.$db->error);
            } else {
                $insert_sql = file_get_contents('resources/sql/'.$board_engine.'/db-000-insert-partial.sql');
                $insert_sql = str_replace(array('%%BOARD%%', '%%OLD_DATABASE%%', '%%NEW_DATABASE%%'), array($shortname, $old_database, $new_database), $insert_sql);

                // insert in small chunks
                while ($cur_doc_id + 200000 < $max_doc_id) {
                    echoCli('Inserting `'.$new_database.'`.`'.$shortname.'` into `'.$new_database.'`.`'.$shortname.'` from '.$cur_doc_id.' to '.($cur_doc_id + 200000).'.');

                    $db->query(str_replace(array('%%START_DOC_ID%%', '%%END_DOC_ID%%'), array($cur_doc_id, $cur_doc_id + 200000), $insert_sql));
                    if ($db->error) dieCli('[database error] '.$db->error);

                    $cur_doc_id += 200000;
                }

                // insert last chunk again
                $db->query(str_replace(array('%%START_DOC_ID%%', '%%END_DOC_ID%%'), array($cur_doc_id - 200000, $cur_doc_id + 400000), $insert_sql));
                if ($db->error) dieCli('[database error] '.$db->error);
            }
        } else {
            echoCli('PHASE 1: SKIP!');
            continue;
        }

        echoCli('PHASE 1: COMPLETE!');
    }

    /**
    * PHASE 2: REBUILD IMAGE FOLDER
    */
    if (!$args->passed('--phase') || $args->get_full_passed('--phase') == 2) {
        echoCli('PHASE 2: REBUILDING IMAGE DIRECTORY');

        // default variables
        $init = time();
        $save = 'save-phase_2_rebuild-'.$shortname;

        $max_doc_id = 0;
        $offset = 0;
        $processed = 0;
        $image_transfer = 0;
        $thumb_transfer = 0;
        $old_path = $old_path.'/'.$shortname;
        $new_path = $path.'/'.$shortname;

        if (file_exists($save)) {
            $offset = intval(file_get_contents($save));
        } else {
            $max_doc_id_res = $db->query("SELECT MAX(doc_id) AS max FROM \"".$new_database."\"".$shortname."\"");
            if ($db->error) dieCli('[database error] '.$db->error);
            while ($row = $max_doc_id_res->fetch_object()) {
                $max_doc_id = $row->max;
            }
            mysqli_free_result($max_doc_id_res);

            $offset = $max_doc_id;
        }

        while ($offset > 0) {
            // save state
            file_put_contents($save, $offset);

            // create clean ref array
            $temp = array();

            // ping - pong. are we still alive?
            $db->ping();

            $images_res = $db->query("
                SELECT brd.media_id, num, thread_num, op, brd.preview_orig, img.preview_op, img.preview_reply, img.media
                FROM \"".$new_database."\"".$shortname."\" AS brd
                JOIN \"".$new_database."\"".$shortname."_images\" AS img
                ON brd.media_id = img.media_id
                WHERE doc_id <= ".$offset." AND doc_id >= ".($offset - 100000)." AND brd.media_hash IS NOT NULL
            ");
            if ($db->error) dieCli('[database error] '.$db->error);

            while ($row = $images_res->fetch_object()) {
                // create the old file structure path with thread_num
                preg_match('/(\d+?)(\d{2})\d{0,3}$/', $row->thread_num, $subpath);
                for ($index = 1; $index <= 2; $index++) {
                    if (!isset($subpath[$index])) $subpath[$index] = '';
                }

                // if the thumb is null, something is wrong...
                $preview = ($row->op) ? $row->preview_op : $row->preview_reply;
                if (!is_null($preview)) {
                    // make the inner paths
                    $old_image_path_inner = str_pad($subpath[1], 4, "0", STR_PAD_LEFT) . str_pad($subpath[2], 2, "0", STR_PAD_LEFT);
                    $old_image_path_inner = substr($old_image_path_inner, 0, 4) . '/' . substr($old_image_path_inner, 4, 2);
                    $new_image_path_inner = substr($preview, 0, 4) . '/' . substr($preview, 4, 2);

                    // make the full paths
                    $old_image_path = $old_path . '/thumb/' . $old_image_path_inner . '/' . $row->preview_orig;
                    $new_image_path = $new_path . '/thumb/' . $new_image_path_inner . '/' . $preview;

                    if (!isset($temp['thumb'][$row->media_id][$row->op])) {
                        if (!file_exists($new_image_path)) {
                            @mkdir($new_path.'/thumb/'.$new_image_path_inner, 0777, true);
                            if (@copy($old_image_path, $new_image_path)) {
                                $thumb_transfer++;
                                $temp['thumb'][$row->media_id][$row->op] = true;
                            }
                        }
                    }
                }

                // process that full image!
                if (!is_null($row->media) && $args->passed('--full-images')) {
                    // make the inner paths
                    $old_full_image_path_inner = str_pad($subpath[1], 4, "0", STR_PAD_LEFT) . str_pad($subpath[2], 2, "0", STR_PAD_LEFT);
                    $old_full_image_path_inner = substr($old_full_image_path_inner, 0, 4) . '/' . substr($old_full_image_path_inner, 4, 2);
                    $new_full_image_path_inner = substr($row->media, 0, 4) . '/' . substr($row->media, 4, 2);

                    // make the full paths
                    $old_full_image_path = $old_path . '/img/' . $old_full_image_path_inner . '/' . $row->media_orig;
                    $new_full_image_path = $new_path . '/image/' . $new_full_image_path_inner . '/' . $row->media;

                    if (!isset($temp['image'][$row->media_id][$row->op]))
                    {
                        if (!file_exists($new_full_image_path)) {
                            @mkdir($new_path.'/image/'.$new_full_image_path_inner, 0777, true);
                            if (@copy($old_full_image_path, $new_full_image_path)) {
                                $full_transfer++;
                                $temp['image'][$row->media_id][$row->op] = true;
                            }
                        }
                    }
                }

                $processed++;

                // print some fun stuff to screen
                if ($processed % 5 == 0 && time() - $init > 0) {
                    if (!isset($last_output)))
                        $last_output = 0;

                    $delete = "";
                    for($i = 0; $i < $last_output; $i++) {
                        $delete .= "\x08";
                    }

                    $output = 'Processing rows '.$offset.' to '.($offset - 100000).' - Processed: '.$processed.' ('.$thumb_transfer.'/'.$image_transfer.') - Images per Hour: '.floor(($images_processed/(time() - $init))*3600);

                    echoCli($delete.str_pad($output, $last_output));
                    $last_output = strlen($output)''
                }
            }

            mysqli_free_result($images_res);

            $offset -= 100000;
        }

        echoCli('PHASE 2: COMPLETE!');
    }
}