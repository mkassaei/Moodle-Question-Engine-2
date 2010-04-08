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

// ou-specific This whole file
// until the new question engine is merged into Moodle core (probably 2.1).

/**
 * Sets up the tabs used by the quiz pages based on the users capabilites.
 *
 * @package mod_quiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); /// It must be included from a Moodle page.
}

if (empty($quiz)) {
    if (empty($attemptobj)) {
        print_error('cannotcallscript');
    }
    $quiz = $attemptobj->get_quiz();
    $cm = $attemptobj->get_cm();
}
if (!isset($currenttab)) {
    $currenttab = '';
}
if (!isset($cm)) {
    $cm = get_coursemodule_from_instance('quiz', $quiz->id);
}


$context = get_context_instance(CONTEXT_MODULE, $cm->id);

if (!isset($contexts)) {
    $contexts = new question_edit_contexts($context);
}
$tabs = array();
$row  = array();
$inactive = array();
$activated = array();

if (has_capability('mod/quiz:view', $context)) {
    $row[] = new tabobject('info', "$CFG->wwwroot/mod/quiz/view.php?id=$cm->id", get_string('info', 'quiz'));
}
if (has_capability('mod/quiz:viewreports', $context)) {
    $row[] = new tabobject('reports', "$CFG->wwwroot/mod/quiz/report.php?id=$cm->id", get_string('results', 'quiz'));
}
if (has_capability('mod/quiz:preview', $context)) {
    $row[] = new tabobject('preview', "$CFG->wwwroot/mod/quiz/attempt.php?id=$cm->id", get_string('preview', 'quiz'));
}
if (has_capability('mod/quiz:manage', $context)) {
    $row[] = new tabobject('edit', "$CFG->wwwroot/mod/quiz/edit.php?cmid=$cm->id", get_string('edit'));
}

if ($currenttab == 'info' && count($row) == 1) {
    // Don't show only an info tab (e.g. to students).
} else {
    $tabs[] = $row;
}

if ($currenttab == 'reports' and isset($mode)) {
    $activated[] = 'reports';

    $row  = array();
    $currenttab = '';

    $reportlist = quiz_report_list($context);

    foreach ($reportlist as $report) {
        $row[] = new tabobject($report, "$CFG->wwwroot/mod/quiz/report.php?id=$cm->id&amp;mode=$report",
                                get_string($report, 'quiz_'.$report));
        if ($report == $mode) {
            $currenttab = $report;
        }
    }
    $tabs[] = $row;
}

if ($currenttab == 'edit' and isset($mode)) {
    $activated[] = 'edit';

    $row  = array();
    $currenttab = $mode;

    $strquiz = get_string('modulename', 'quiz');
    $streditingquiz = get_string("editinga", "moodle", $strquiz);

    if (has_capability('mod/quiz:manage', $context) && $contexts->have_one_edit_tab_cap('editq')) {
        $row[] = new tabobject('editq', "$CFG->wwwroot/mod/quiz/edit.php?".$thispageurl->get_query_string(), $strquiz, $streditingquiz);
    }
    questionbank_navigation_tabs($row, $contexts, $thispageurl->get_query_string());
    $tabs[] = $row;

}

if (!$quiz->questions) {
// ou-specific begins
    // $inactive += array('info', 'reports', 'preview');
    $inactive += array('preview');
// ou-specific ends
}

print_tabs($tabs, $currenttab, $inactive, $activated);

?>
