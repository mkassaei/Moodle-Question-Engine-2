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
 * Library code for the Quiz upgrade status report.
 *
 * @package report_quizupgrade
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/question/engine/upgradefromoldqe/upgrade.php');

/**
 * Get the information about a quiz to be upgraded.
 * @param integer $quizid the quiz id.
 * @return object an inforation abject about that quiz, as for report_quizupgrade_get_quizzes.
 */
function report_quizupgrade_get_quizzes() {
    global $CFG;
    return get_records_sql("
    SELECT
        quiz.id,
        quiz.name,
        c.shortname,
        c.id AS courseid,
        COUNT(1) AS numtoconvert
    FROM {$CFG->prefix}quiz_attempts quiza
    JOIN {$CFG->prefix}quiz quiz ON quiz.id = quiza.quiz
    JOIN {$CFG->prefix}course c ON c.id = quiz.course
    WHERE quiza.needsupgradetonewqe = 1
    GROUP BY quiz.id, quiz.name, c.shortname, c.id
    ORDER BY c.shortname, quiz.name, quiz.id");
}

/**
 * Get the information about a quiz to be upgraded.
 * @param integer $quizid the quiz id.
 * @return object an inforation abject about that quiz, as for report_quizupgrade_get_quizzes.
 */
function report_quizupgrade_get_quiz($quizid) {
    global $CFG;
    return get_record_sql("
    SELECT
        quiz.id,
        quiz.name,
        c.shortname,
        c.id AS courseid,
        COUNT(1) AS numtoconvert
    FROM {$CFG->prefix}quiz_attempts quiza
    JOIN {$CFG->prefix}quiz quiz ON quiz.id = quiza.quiz
    JOIN {$CFG->prefix}course c ON c.id = quiz.course
    WHERE quiza.needsupgradetonewqe = 1 AND quiz.id = {$quizid}
    GROUP BY quiz.id, quiz.name, c.shortname, c.id
    ORDER BY c.shortname, quiz.name, quiz.id");
}

class report_quizupgrade_attempt_upgrader extends question_engine_attempt_upgrader {
    public $quizid;
    public $attemptsdone = 0;
    public $attemptstodo;

    public function __construct($quizid, $attemptstodo) {
        $this->quizid = $quizid;
        $this->attemptstodo = $attemptstodo;
    }

    protected function get_quiz_ids() {
        return array($this->quizid => 1);
    }

    protected function print_progress($done, $outof, $quizid) {
    }

    protected function convert_quiz_attempt($quiz, $attempt, $questionsessionsrs, $questionsstatesrs) {
        $this->attemptsdone += 1;
        print_progress($this->attemptsdone, $this->attemptstodo);
        return parent::convert_quiz_attempt($quiz, $attempt, $questionsessionsrs, $questionsstatesrs);
    }
}
