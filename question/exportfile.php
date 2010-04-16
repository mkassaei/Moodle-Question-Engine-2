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
 * This script sends question exports to users who do not have permisison to
 * view the course files.
 *
 * @package moodlecore
 * @subpackage questionbank
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/filelib.php');

// Note: file.php always calls require_login() with $setwantsurltome=false
//       in order to avoid messing redirects. MDL-14495
require_login(0, true, null, false);

$relativepath = get_file_argument('question/exportfile.php');
if (!$relativepath) {
    error('No valid arguments supplied or incorrect server configuration');
}

$pathname = $CFG->dataroot . '/temp/questionexport/' . $USER->id . '/' .  $relativepath;

send_temp_file($pathname, $relativepath);
