<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This script serves user's private files
 *
 * @package    moodlecore
 * @subpackage file
 * @copyright  2008 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('config.php');
require_once('lib/filelib.php');

// disable moodle specific debug messages
disable_debugging();

$relativepath = get_file_argument();
$forcedownload = optional_param('forcedownload', 0, PARAM_BOOL);

// relative path must start with '/'
if (!$relativepath) {
    print_error('invalidargorconf');
} else if ($relativepath{0} != '/') {
    print_error('pathdoesnotstartslash');
}

// extract relative path components
$args = explode('/', ltrim($relativepath, '/'));

if (count($args) == 0) { // always at least user id
    print_error('invalidarguments');
}

$contextid = (int)array_shift($args);
$filearea = array_shift($args);

$context = get_context_instance_by_id($contextid);
if ($context->contextlevel != CONTEXT_USER) {
    print_error('invalidarguments');
}

$userid = $context->instanceid;

switch ($filearea) {
    case 'user_profile':
        require_login();
        if (isguestuser()) {
            print_error('noguest');
        }

        // access controll here must match user edit forms
        if ($userid == $USER->id) {
             if (!has_capability('moodle/user:editownprofile', get_context_instance(CONTEXT_SYSTEM))) {
                send_file_not_found();
             }
        } else {
            if (!has_capability('moodle/user:editprofile', $context) and !has_capability('moodle/user:update', $context)) {
                send_file_not_found();
            }
        }
        $itemid = 0;
        $forcedownload = true;
        break;

    case 'user_private':
        require_login();
        if (isguestuser()) {
            send_file_not_found();
        }
        if ($USER->id != $userid) {
            send_file_not_found();
        }
        $itemid = 0;
        $forcedownload = true;
        break;

    default:
        send_file_not_found();
}

$relativepath = '/'.implode('/', $args);

$fs = get_file_storage();

$fullpath = $context->id.$filearea.$itemid.$relativepath;

if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->get_filename() == '.') {
    send_file_not_found();
}

// ========================================
// finally send the file
// ========================================
session_get_instance()->write_close(); // unlock session during fileserving
send_stored_file($file, 0, false, $forcedownload);