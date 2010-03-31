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
 * The file defines some subclasses that can be used when you are building
 * a report like the overview or responses report, that basically has one
 * row per attempt.
 *
 * @package mod_quiz
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir.'/tablelib.php');


/**
 * Base class for quiz reports that are basically a table with one row for each attempt.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class quiz_attempt_report extends quiz_default_report {
    /** @var object the quiz context. */
    protected $context;

    /** @var boolean caches the results of {@link should_show_grades()}. */
    protected $showgrades = null;

    /**
     * Should the grades be displayed in this report. That depends on the quiz
     * display options, and whether the quiz is graded.
     * @param object $quiz the quiz settings.
     * @return boolean
     */
    protected function should_show_grades($quiz) {
        if (!is_null($this->showgrades)) {
            return $this->showgrades;
        }

        $fakeattempt = new stdClass();
        $fakeattempt->preview = false;
        $fakeattempt->timefinish = $quiz->timeopen;
        $fakeattempt->userid = 0;
        $fakeattempt->id = 0;

        $reviewoptions = quiz_get_reviewoptions($quiz, $fakeattempt, $this->context);
        $this->showgrades = quiz_has_grades($quiz) && $reviewoptions->scores;
        return $this->showgrades;
    }

    /**
     * Get information about which students to show in the report.
     * @param object $cm the coures module.
     * @return an array with four elements:
     *      0 => integer the current group id (0 for none).
     *      1 => array ids of all the students in this course.
     *      2 => array ids of all the students in the current group.
     *      3 => array ids of all the students to show in the report. Will be the
     *              same as either element 1 or 2.
     */
    protected function load_relevant_students($cm) {
        $currentgroup = groups_get_activity_group($cm, true);

        if (!$students = get_users_by_capability($this->context,
                array('mod/quiz:reviewmyattempts', 'mod/quiz:attempt'),
                '', '', '', '', '', '', false)) {
            $students = array();
        } else {
            $students = array_keys($students);
        }

        if (empty($currentgroup)) {
            return array($currentgroup, $students, array(), $students);
        }

        // We have a currently selected group.
        if (!$groupstudents = get_users_by_capability($this->context,
                array('mod/quiz:reviewmyattempts', 'mod/quiz:attempt'),
                '', '', '', '', $currentgroup, '', false)) {
            $groupstudents = array();
        } else {
            $groupstudents = array_keys($groupstudents);
        }

        return array($currentgroup, $students, $groupstudents, $groupstudents);
    }

    /**
     * Alters $attemptsmode and $pagesize if the current values are inappropriate.
     * @param integer $attemptsmode what sort of attemtps to display (may be updated)
     * @param integer $pagesize number of records to display per page (may be updated)
     * @param object $course the course settings.
     * @param integer $currentgroup the currently selected group. 0 for none.
     */
    protected function validate_common_options(&$attemptsmode, &$pagesize, $course, $currentgroup) {
        if ($currentgroup) {
            //default for when a group is selected
            if ($attemptsmode === null  || $attemptsmode == QUIZ_REPORT_ATTEMPTS_ALL) {
                $attemptsmode = QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH;
            }
        } else if (!$currentgroup && $course->id == SITEID) {
            //force report on front page to show all, unless a group is selected.
            $attemptsmode = QUIZ_REPORT_ATTEMPTS_ALL;
        } else if ($attemptsmode === null) {
            //default
            $attemptsmode = QUIZ_REPORT_ATTEMPTS_ALL;
        }

        if ($pagesize < 1) {
            $pagesize = QUIZ_REPORT_DEFAULT_PAGE_SIZE;
        }
    }

    /**
     * Contruct all the parts of the main database query.
     * @param object $quiz the quiz settings.
     * @param string $qmsubselect SQL fragment from {@link quiz_report_qm_filter_select()}.
     * @param boolean $qmfilter whether to show all, or only the final grade attempt.
     * @param integer $attemptsmode which attemtps to show. One of the QUIZ_REPORT_ATTEMPTS_... constants.
     * @param array $reportstudents list if userids of users to include in the report.
     * @return array with 4 elements ($fields, $from, $where, $params) that can be used to
     *      build the actual database query.
     */
    protected function base_sql($quiz, $qmsubselect, $qmfilter, $attemptsmode, $reportstudents) {
        global $CFG;

        $fields = sql_concat('u.id', "'#'", 'COALESCE(qa.attempt, 0)') . ' AS uniqueid, ';

        if ($qmsubselect) {
            $fields .=
                "(CASE " .
                "   WHEN $qmsubselect THEN 1" .
                "   ELSE 0 " .
                "END) AS gradedattempt, ";
        }

        $fields .= 'qa.uniqueid AS usageid, qa.id AS attempt, ' .
            'u.id AS userid, u.idnumber, u.firstname, u.lastname, ' .
            'u.picture, u.imagealt, u.institution, u.department, u.email, ' .
            'qa.sumgrades, qa.timefinish, qa.timestart, qa.timefinish - qa.timestart AS duration ';

        // This part is the same for all cases - join users and quiz_attempts tables
        $from = "{$CFG->prefix}user u ";
        $from .= "LEFT JOIN {$CFG->prefix}quiz_attempts qa ON qa.userid = u.id AND qa.quiz = $quiz->id";
        $params = array('quizid' => $quiz->id);

        if ($qmsubselect && $qmfilter) {
            $from .= ' AND ' . $qmsubselect;
        }
        switch ($attemptsmode) {
            case QUIZ_REPORT_ATTEMPTS_ALL:
                // Show all attempts, including students who are no longer in the course
                $where = 'qa.id IS NOT NULL AND qa.preview = 0';
                break;
            case QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH:
                // Show only students with attempts
                list($allowed_usql, $allowed_params) = get_in_or_equal($reportstudents, SQL_PARAMS_NAMED, 'u0000');
                $params += $allowed_params;
                $where = "u.id $allowed_usql AND qa.preview = 0 AND qa.id IS NOT NULL";
                break;
            case QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO:
                // Show only students without attempts
                list($allowed_usql, $allowed_params) = get_in_or_equal($reportstudents, SQL_PARAMS_NAMED, 'u0000');
                $params += $allowed_params;
                $where = "u.id $allowed_usql AND qa.id IS NULL";
                break;
            case QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS:
                // Show all students with or without attempts
                list($allowed_usql, $allowed_params) = get_in_or_equal($reportstudents, SQL_PARAMS_NAMED, 'u0000');
                $params += $allowed_params;
                $where = "u.id $allowed_usql AND (qa.preview = 0 OR qa.preview IS NULL)";
                break;
        }

        return array($fields, $from, $where, $params);
    }

    /**
     * Add all the user-related columns to the $columns and $headers arrays.
     * @param table_sql $table the table being constructed.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     */
    protected function add_user_columns($table, &$columns, &$headers) {
        global $CFG;
        if (!$table->is_downloading() && $CFG->grade_report_showuserimage) {
            $columns[] = 'picture';
            $headers[] = '';
        }
        if (!$table->is_downloading()) {
            $columns[] = 'fullname';
            $headers[] = get_string('name');
        } else {
            $columns[] = 'lastname';
            $headers[] = get_string('lastname');
            $columns[] = 'firstname';
            $headers[] = get_string('firstname');
        }

        if ($CFG->grade_report_showuseridnumber) {
            $columns[] = 'idnumber';
            $headers[] = get_string('idnumber');
        }

        if ($table->is_downloading()) {
            $columns[] = 'institution';
            $headers[] = get_string('institution');

            $columns[] = 'department';
            $headers[] = get_string('department');

            $columns[] = 'email';
            $headers[] = get_string('email');
        }
    }

    /**
     * Set the display options for the user-related columns in the table.
     * @param table_sql $table the table being constructed.
     */
    protected function configure_user_columns($table) {
        $table->column_suppress('picture');
        $table->column_suppress('fullname');
        $table->column_suppress('idnumber');

        $table->column_class('picture', 'picture');
        $table->column_class('lastname', 'bold');
        $table->column_class('firstname', 'bold');
        $table->column_class('fullname', 'bold');
    }

    /**
     * Add all the time-related columns to the $columns and $headers arrays.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     */
    protected function add_time_columns(&$columns, &$headers) {
        $columns[] = 'timestart';
        $headers[] = get_string('startedon', 'quiz');

        $columns[] = 'timefinish';
        $headers[] = get_string('timecompleted','quiz');

        $columns[] = 'duration';
        $headers[] = get_string('attemptduration', 'quiz');
    }

    /**
     * Add all the grade and feedback columns, if applicable, to the $columns
     * and $headers arrays.
     * @param object $quiz the quiz settings.
     * @param array $columns the list of columns. Added to.
     * @param array $headers the columns headings. Added to.
     */
    protected function add_grade_columns($quiz, &$columns, &$headers) {
        if ($this->should_show_grades($quiz)) {
            $columns[] = 'sumgrades';
            $headers[] = get_string('grade', 'quiz') . '/' .
                    quiz_format_grade($quiz, $quiz->grade);
        }

        if (quiz_has_feedback($quiz)) {
            $columns[] = 'feedbacktext';
            $headers[] = get_string('feedback', 'quiz');
        }
    }

    /**
     * Set up the table.
     * @param table_sql $table the table being constructed.
     * @param array $columns the list of columns.
     * @param array $headers the columns headings.
     * @param moodle_url $reporturl the URL of this report.
     * @param array $displayoptions the display options.
     * @param boolean $collapsible whether to allow columns in the report to be collapsed.
     */
    protected function set_up_table_columns($table, $columns, $headers, $reporturl, $displayoptions, $collapsible) {
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->sortable(true, 'uniqueid');

        $table->define_baseurl($reporturl->out(false, $displayoptions));

        $this->configure_user_columns($table);

        $table->no_sorting('feedbacktext');
        $table->column_class('sumgrades', 'bold');

        $table->set_attribute('id', 'attempts');

        $table->collapsible($collapsible);
    }
}

/**
 * Base class for the table used by {@link quiz_attempt_report}s.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class quiz_attempt_report_table extends table_sql {
    public $useridfield = 'userid';

    /** @var moodle_url the URL of this report. */
    protected $reporturl;

    /** @var array the display options. */
    protected $displayoptions;

    public function __construct($uniqueid, $quiz , $qmsubselect, $groupstudents,
            $students, $questions, $candelete, $reporturl, $displayoptions) {
        parent::table_sql('mod-quiz-report-responses-report');
        $this->quiz = $quiz;
        $this->qmsubselect = $qmsubselect;
        $this->groupstudents = $groupstudents;
        $this->students = $students;
        $this->questions = $questions;
        $this->candelete = $candelete;
        $this->reporturl = $reporturl;
        $this->displayoptions = $displayoptions;
    }

    public function col_checkbox($attempt) {
        if ($attempt->attempt) {
            return '<input type="checkbox" name="attemptid[]" value="'.$attempt->attempt.'" />';
        } else {
            return '';
        }
    }

    public function col_picture($attempt) {
        global $COURSE;
        $user = new stdClass;
        $user->id = $attempt->userid;
        $user->lastname = $attempt->lastname;
        $user->firstname = $attempt->firstname;
        $user->imagealt = $attempt->imagealt;
        $user->picture = $attempt->picture;
        return print_user_picture($user, $COURSE->id, $attempt->picture, false, true);
    }


    public function col_timestart($attempt) {
        if (!$attempt->attempt) {
            return  '-';
        }

        $startdate = userdate($attempt->timestart, $this->strtimeformat);
        if (!$this->is_downloading()) {
            return  '<a href="review.php?q='.$this->quiz->id.'&amp;attempt='.$attempt->attempt.'">'.$startdate.'</a>';
        } else {
            return  $startdate;
        }
    }

    public function col_timefinish($attempt) {
        if (!$attempt->attempt) {
            return  '-';
        }
        if (!$attempt->timefinish) {
            return  '-';
        }

        $timefinish = userdate($attempt->timefinish, $this->strtimeformat);
        if (!$this->is_downloading()) {
            return '<a href="review.php?q='.$this->quiz->id.'&amp;attempt='.$attempt->attempt.'">'.$timefinish.'</a>';
        } else {
            return $timefinish;
        }
    }

    public function col_duration($attempt) {
        if ($attempt->timefinish) {
            return format_time($attempt->duration);
        } elseif ($attempt->timestart) {
            return get_string('unfinished', 'quiz');
        } else {
            return '-';
        }
    }

    public function col_feedbacktext($attempt) {
        if (!$attempt->timefinish) {
            return '-';
        }

        if (!$this->is_downloading()) {
            return quiz_report_feedback_for_grade(quiz_rescale_grade($attempt->sumgrades, $this->quiz, false), $this->quiz->id);
        } else {
            return strip_tags(quiz_report_feedback_for_grade(quiz_rescale_grade($attempt->sumgrades, $this->quiz, false), $this->quiz->id));
        }
    }
}

