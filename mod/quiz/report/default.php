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
 * Base class for quiz report plugins.
 *
 * @package mod_quiz
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Base class for quiz report plugins.
 *
 * Doesn't do anything on it's own -- it needs to be extended.
 * This class displays quiz reports.  Because it is called from
 * within /mod/quiz/report.php you can assume that the page header
 * and footer are taken care of.
 *
 * This file can refer to itself as report.php to pass variables
 * to itself - all these will also be globally available.  You must
 * pass "id=$cm->id" or q=$quiz->id", and "mode=reportname".
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class quiz_default_report {
    /**
     * Override this function to displays the report.
     * @param $cm the course-module for this quiz.
     * @param $course the coures we are in.
     * @param $quiz this quiz.
     */
    abstract function display($cm, $course, $quiz);

    function print_header_and_tabs($cm, $course, $quiz, $reportmode="overview", $meta="") {
        global $CFG;
    /// Define some strings
        $strquizzes = get_string("modulenameplural", "quiz");
        $strquiz  = get_string("modulename", "quiz");
    /// Print the page header
        $navigation = build_navigation('', $cm);

        print_header_simple(format_string($quiz->name), "", $navigation,
                     '', $meta, true, update_module_button($cm->id, $course->id, $strquiz), navmenu($course, $cm));
    /// Print the tabs
        $currenttab = 'reports';
        $mode = $reportmode;
        require($CFG->dirroot . '/mod/quiz/tabs.php');
        $course_context = get_context_instance(CONTEXT_COURSE, $course->id);
        if (has_capability('gradereport/grader:view', $course_context) && has_capability('moodle/grade:viewall', $course_context)) {
            echo '<div class="allcoursegrades"><a href="' . $CFG->wwwroot . '/grade/report/grader/index.php?id=' . $course->id . '">'
                . get_string('seeallcoursegrades', 'grades') . '</a></div>';
        }
    }
}
