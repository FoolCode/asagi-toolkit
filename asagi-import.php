<?php

/**
 * Asagi Import
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
Asagi Import/Merge
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
--full-images             If you have full images stored, this will process the full image directory as well.
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

if (!$args->passed('--import-database')) {
    dieCli('You must specify the database to import.');
}

$import_database = $args->get_full_passed('--import-database');

$asagi = $json->settings->boardSettings;
$default = $asagi->default;
unset($asagi->default);

// setup DB connection information and path
$board_engine = strtolower($default->engine);
$hostname = $default->host;
$username = $default->username;
$password = $default->password;
$database = $default->database;
$charset = 'utf8mb4';
$path = rtrim($default->path, '/');
$import_path = rtrim($default->import_path, '/');

// core migration
foreach($asagi as $shortname => $settings) {
    // if --board parameter provided, process a single board only
    if ($args->passed('--board')) {
        if ($shortname != $args->get_full_passed('--board'))
            continue;
    }

    // connect to DB
    $db = new mysqli($hostname, $username, $password, $import_database);
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
     * PHASE 1: IMPORT DATA
     */
    if (!$args->passed('--phase') || $args->get_full_passed('--phase') == 1) {
        echoCli('PHASE 1: IMPORT DATA');

        // default variables
        $cur_doc_id = 0;
        $max_doc_id = 0;
        $offset = 0;

        $max_doc_id_res = $db->query("SELECT MAX(doc_id) AS max FROM \"".$import_database."\".\"".$shortname."_images\"");
        if ($db->error) dieCli('[database error] '.$db->error);
        while ($row = $max_doc_id_res->fetch_object()) {
            $max_doc_id = $row->max;
        }
        mysqli_free_result($max_doc_id_res);

        echoCli('Importing `'.$import_database.'`.`'.$shortname.'` into `'.$database.'`.`'.$shortname.'`.');

        if ($max_doc_id <= 200000) {

            $db->query("
                INSERT IGNORE INTO \"".$database."\".\"".$shortname."\"
                (poster_ip, num, subnum, thread_num, op, timestamp, timestamp_expired, preview_orig, preview_w, preview_h, media_filename, media_w, media_h, media_size, media_hash, media_orig, spoiler, deleted,   capcode, email, name, trip, title, comment, delpass, sticky, poster_hash, poster_country, exif)
                SELECT poster_ip, num, subnum, thread_num, op, timestamp, timestamp_expired, preview_orig, preview_w, preview_h, media_filename, media_w, media_h, media_size, media_hash, media_orig, spoiler, deleted,   capcode, email, name, trip, title, comment, delpass, sticky, poster_hash, poster_country, exif
                FROM \"".$import_database."\".\"".$shortname."\"
            ");
            if ($db->error) dieCli('[database error] '.$db->error);
        } else {
            $db->query("
                INSERT IGNORE INTO \"".$database."\".\"".$shortname."\"
                (poster_ip, num, subnum, thread_num, op, timestamp, timestamp_expired, preview_orig, preview_w, preview_h, media_filename, media_w, media_h, media_size, media_hash, media_orig, spoiler, deleted,   capcode, email, name, trip, title, comment, delpass, sticky, poster_hash, poster_country, exif)
                SELECT poster_ip, num, subnum, thread_num, op, timestamp, timestamp_expired, preview_orig, preview_w, preview_h, media_filename, media_w, media_h, media_size, media_hash, media_orig, spoiler, deleted,   capcode, email, name, trip, title, comment, delpass, sticky, poster_hash, poster_country, exif
                FROM \"".$import_database."\".\"".$shortname."\"
                WHERE doc_id >= ".$cur_doc_id." AND doc_id <= ".($cur_doc_id + 200000)."
            ");
            if ($db->error) dieCli('[database error] '.$db->error);

            $cur_doc_id += 200000;
        }

        echoCli('PHASE 1: COMPLETE!');
    }

    /**
     * PHASE 2: MIGRATE MEDIA
     */
    if (!$args->passed('--phase') || $args->get_full_passed('--phase') == 2) {
        echoCli('PHASE 2: MIGRATE MEDIA');

        // default variables
        $save = 'save-phase_2_migrate-'.$shortname;

        $max_media_id = 0;
        $offset = 0;
        $processed = 0;
        $image_transfer = 0;
        $thumb_transfer = 0;
        $old_path = $import_path.'/'.$shortname;
        $new_path = $path.'/'.$shortname;

        if (file_exists($save)) {
            $offset = intval(file_get_contents($save));
        } else {
            $max_media_id_res = $db->query("SELECT MAX(media_id) AS max FROM \"".$import_database."\".\"".$shortname."_images\"");
            if ($db->error) dieCli('[database error] '.$db->error);
            while ($row = $max_media_id_res->fetch_object()) {
                $max_media_id = $row->max;
            }
            mysqli_free_result($max_media_id_res);

            $offset = $max_media_id;
        }

        while ($offset > 0) {
            // save state
            file_put_contents($save, $offset);

            // create clean ref array
            $temp = array();

            // ping - pong. are we still alive?
            $db->ping();

            $images_res = $db->query("
                SELECT i.media_hash, i.media AS i_media, i.preview_op AS i_preview_op, i.preview_reply AS i_preview_reply, m.media AS m_media, m.preview_op AS m_preview_op, m.preview_reply AS m_preview_reply, m.banned
                FROM \"".$import_database."\".\"".$shortname."_images\" AS i
                JOIN \"".$database.".\"".$shortname."_images\" AS m
                ON i.media_id = m.media_id
                WHERE i.media_id <= ".$offset." AND i.media_id >= ".($offset - 100000)."
            ");
            if ($db->error) dieCli('[database error] '.$db->error);

            while ($row = $images_res->fetch_object()) {

                // if the thumb is null, something is wrong...
                $i_preview = ($row->op) ? $row->i_preview_op : $row->i_preview_reply;
                $m_preview = ($row->op) ? $row->m_preview_op : $row->m_preview_reply;
                if (!is_null($m_preview)) {
                    // make the full paths
                    $old_image_path = $old_path.'/thumb/'.substr($i_preview, 0, 4).'/'.substr($i_preview, 4, 2).'/'. $i_preview;
                    $new_image_path = $new_path.'/thumb/'.substr($m_preview, 0, 4).'/'.substr($m_preview, 4, 2).'/'. $m_preview;

                    if (!isset($temp['thumb'][$row->media_id][$row->op])) {
                        if (!file_exists($new_image_path)) {
                            @mkdir($new_path.'/thumb/'.substr($m_preview, 0, 4).'/'.substr($m_preview, 4, 2), 0777, true);
                            if (@copy($old_image_path, $new_image_path)) {
                                $thumb_transfer++;
                                $temp['thumb'][$row->media_id][$row->op] = true;
                            }
                        }
                    }
                }

                // process that full image!
                if (!is_null($row->m_media) && $args->passed('--full-images')) {
                    // make the full paths
                    $old_full_image_path = $old_path.'/image/'.substr($row->i_media, 0, 4).'/'.substr($row->media, 4, 2).'/'.$row->i_media;
                    $new_full_image_path = $new_path.'/image/'.substr($row->m_media, 0, 4).'/'.substr($row->media, 4, 2).'/'.$row->m_media;

                    if (!isset($temp['image'][$row->media_id][$row->op]))
                    {
                        if (!file_exists($new_full_image_path)) {
                            @mkdir($new_path.'/image/'.substr($row->m_media, 0, 4).'/'.substr($row->media, 4, 2), 0777, true);
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

            mysqli_free_result($image_res);

            $offset -= 100000;
        }

        echoCli('PHASE 2: COMPLETE!');
    }
}