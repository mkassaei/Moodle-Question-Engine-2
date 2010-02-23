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
 * This script controls the display of the quiz reports.
 *
 * @package mod_quiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');
require_once($CFG->dirroot . '/mod/quiz/report/default.php');

$id = optional_param('id', 0, PARAM_INT);
$q = optional_param('q', 0, PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);

if ($id) {
    if (!$cm = get_coursemodule_from_id('quiz', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = get_record('course', 'id', $cm->course)) {
        print_error('coursemisconf');
    }
    if (!$quiz = get_record('quiz', 'id', $cm->instance)) {
        print_error('invalidcoursemodule');
    }

} else {
    if (!$quiz = get_record('quiz', 'id', $q)) {
        print_error('invalidquizid', 'quiz');
    }
    if (!$course = get_record('course', 'id', $quiz->course)) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

require_login($course, false, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);

$reportlist = quiz_report_list($context);
if (count($reportlist) == 0) {
    print_error('erroraccessingreport', 'quiz');
}
if ($mode == '') {
    $mode = reset($reportlist); // First element in array
} else if (!in_array($mode, $reportlist)) {
    print_error('erroraccessingreport', 'quiz');
}
if (!is_readable("report/$mode/report.php")) {
    print_error('reportnotfound', 'quiz', '', $mode);
}

// If no questions have been set up yet redirect to edit.php
if (!$quiz->questions and has_capability('mod/quiz:manage', $context)) {
    redirect('edit.php?cmid='.$cm->id);
}

add_to_log($course->id, 'quiz', 'report', 'report.php?id=' . $cm->id, $quiz->id, $cm->id);

// Open the selected quiz report and display it
include("report/$mode/report.php");
$reportclassname = 'quiz_' . $mode . '_report';
$report = new $reportclassname();

if (!$report->display($quiz, $cm, $course)) {
    print_error("preprocesserror", 'quiz');
}

// Print footer
print_footer($course);
