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
 * Helper functions for the quiz reports.
 *
 * @package mod_quiz
 * @copyright 2008 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/quiz/lib.php');

define('QUIZ_REPORT_DEFAULT_PAGE_SIZE', 30);
define('QUIZ_REPORT_DEFAULT_GRADING_PAGE_SIZE', 10);

define('QUIZ_REPORT_ATTEMPTS_ALL', 0);
define('QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO', 1);
define('QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH', 2);
define('QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS', 3);

/**
 * Load information about the latest state of selected questions in selected attempts.
 *
 * The $qubaids argument is as for {@link question_engine_data_mapper::load_questions_usages_latest_steps()}.
 *
 * The results are returned as an two dimensional array $qubaid => $qnumber => $dataobject
 *
 * @param mixed $qubaid either an array of usage ids, or a subquery, as above.
 * @param array $qnumbers A list of qnumbers for the questions you want to konw about.
 * @return array of records. See the SQL in this function to see the fields available.
 */
function quiz_report_get_latest_steps($qubaids, $qnumbers) {
    $dm = new question_engine_data_mapper();
    $latesstepdata = $dm->load_questions_usages_latest_steps($qubaids, $qnumbers);
    $lateststeps = array();
    foreach ($latesstepdata as $step) {
        $lateststeps[$step->questionusageid][$step->numberinusage] = $step;
    }
    return $lateststeps;
}

/**
 * Load information about the number of attempts at various questions in each
 * summarystate.
 *
 * The $qubaids argument is as for {@link question_engine_data_mapper::load_questions_usages_question_state_summary()}.
 *
 * The results are returned as an two dimensional array $qubaid => $qnumber => $dataobject
 *
 * @param mixed $qubaid either an array of usage ids, or a subquery, as above.
 * @param array $qnumbers A list of qnumbers for the questions you want to konw about.
 * @return array The array keys are qnumber,qestionid. The values are objects with
 * fields $qnumber, $questionid, $inprogress, $name, $needsgrading, $autograded,
 * $manuallygraded and $all.
 */
function quiz_report_get_state_summary($qubaids, $qnumbers) {
    $dm = new question_engine_data_mapper();
    return $dm->load_questions_usages_question_state_summary($qubaids, $qnumbers);
}

/**
 * Get a list of usage ids where the question with qnumber $qnumber, and optionally
 * also with question id $questionid, is in summary state $summarystate. Also
 * return the total count of such states.
 *
 * Only a subset of the ids can be returned by using $orderby, $limitfrom and
 * $limitnum. A special value 'random' can be passed as $orderby, in which case
 * $limitfrom is ignored.
 *
 * $qubaids is a join, represented as an object with fields ->from,
 * ->usageidcolumn and ->where.
 *
 * @param object $qubaid represents a JOIN, as above.
 * @param integer $qnumber The qnumber for the questions you want to konw about.
 * @param integer $questionid (optional) Only return attempts that were of this specific question.
 * @param string $summarystate 'all', 'needsgrading', 'autograded' or 'manuallygraded'.
 * @param string $orderby 'random', 'date' or 'student'.
 * @param integer $page implements paging of the results.
 *      Ignored if $orderby = random or $pagesize is null.
 * @param integer $pagesize implements paging of the results. null = all.
 */
function quiz_report_get_usage_ids_where_question_in_state($qubaids, $summarystate,
        $qnumber, $questionid = null, $orderby = 'random', $page = 0, $pagesize = null) {
    global $CFG;
    $dm = new question_engine_data_mapper();

    if ($pagesize && $orderby != 'random') {
        $limitfrom = $page * $pagesize;
    } else {
        $limitfrom = 0;
    }

    if ($orderby == 'date') {
        $orderby = "(
                SELECT MAX(sortqas.timecreated)
                FROM {$CFG->prefix}question_attempt_steps sortqas
                WHERE sortqas.questionattemptid = qa.id
                    AND sortqas.state {$dm->in_summary_state_test('manuallygraded', false)}
                )";
    } else if ($orderby == 'student') {
        $qubaids->from .= " JOIN {$CFG->prefix}user u ON quiza.userid = u.id ";
        $orderby = sql_fullname('u.firstname', 'u.lastname');
    }

    return $dm->load_questions_usages_where_question_in_state($qubaids, $summarystate,
            $qnumber, $questionid, $orderby, $limitfrom, $pagesize);
}

/**
 * Takes an array of objects and constructs a multidimensional array keyed by
 * the keys it finds on the object.
 * @param array $datum an array of objects with properties on the object
 * including the keys passed as the next param.
 * @param array $keys Array of strings with the names of the properties on the
 * objects in datum that you want to index the multidimensional array by.
 * @param boolean $keysunique If there is not only one object for each
 * combination of keys you are using you should set $keysunique to true.
 * Otherwise all the object will be added to a zero based array. So the array
 * returned will have count($keys) + 1 indexs.
 * @return array multidimensional array properly indexed.
 */
function quiz_report_index_by_keys($datum, $keys, $keysunique=true) {
    if (!$datum) {
        return $datum;
    }
    $key = array_shift($keys);
    $datumkeyed = array();
    foreach ($datum as $data) {
        if ($keys || !$keysunique) {
            $datumkeyed[$data->{$key}][]= $data;
        } else {
            $datumkeyed[$data->{$key}]= $data;
        }
    }
    if ($keys) {
        foreach ($datumkeyed as $datakey => $datakeyed) {
            $datumkeyed[$datakey] = quiz_report_index_by_keys($datakeyed, $keys, $keysunique);
        }
    }
    return $datumkeyed;
}

function quiz_report_unindex($datum) {
    if (!$datum) {
        return $datum;
    }
    $datumunkeyed = array();
    foreach ($datum as $value) {
        if (is_array($value)) {
            $datumunkeyed = array_merge($datumunkeyed, quiz_report_unindex($value));
        } else {
            $datumunkeyed[] = $value;
        }
    }
    return $datumunkeyed;
}

function quiz_get_regraded_qs($attemptidssql, $limitfrom=0, $limitnum=0) {
    global $CFG;
    if ($attemptidssql && is_array($attemptidssql)) {
        list($asql, $params) = get_in_or_equal($attemptidssql);
        $regradedqsql = "SELECT qqr.* FROM " .
                "{$CFG->prefix}quiz_question_regrade qqr " .
                "WHERE qqr.attemptid $asql";
        $regradedqs = get_records_sql($regradedqsql, $limitfrom, $limitnum);
    } else if ($attemptidssql && is_object($attemptidssql)) {
        $regradedqsql = "SELECT qqr.* FROM " .
                $attemptidssql->from.", ".
                "{$CFG->prefix}quiz_question_regrade qqr " .
                "WHERE qqr.attemptid = qa.uniqueid AND " .
                $attemptidssql->where;
        $regradedqs = get_records_sql($regradedqsql, $limitfrom, $limitnum);
        if (empty($regradedqs)) {
            $regradedqs = array();
        }
    } else {
        return array();
    }
    return quiz_report_index_by_keys($regradedqs, array('attemptid', 'questionid'));
}

function quiz_get_average_grade_for_questions($quiz, $userids, $qnumbers) {
    global $CFG;

    $qmfilter = quiz_report_qm_filter_select($quiz, 'quiza');
    list($usql, $params) = get_in_or_equal($userids);

    $qubaids = "
SELECT quiza.uniqueid
FROM {$CFG->prefix}quiz_attempts quiza 
WHERE 
    ($qmfilter) AND 
    quiza.userid $usql AND 
    quiza.quiz = $quiz->id
";

    $dm = new question_engine_data_mapper();
    return $dm->load_average_marks($qubaids, $qnumbers);
}

/**
 * Get the qnumbers of real questions (not descriptions) in this quiz, in order.
 * @param object $quiz the quiz.
 * @return array of qnumber => $question object with fields ->qnumber, ->id, ->maxmark, ->number, ->length. 
 */
function quiz_report_get_significant_questions($quiz) {
    global $CFG;

    $questionids = quiz_questions_in_quiz($quiz->questions);
    $questions = get_records_sql("
SELECT q.id, q.length, qqi.grade AS maxmark
FROM {$CFG->prefix}question q
JOIN {$CFG->prefix}quiz_question_instances qqi ON qqi.question = q.id
WHERE
    qqi.quiz = $quiz->id AND
    q.id IN ($questionids) AND
    length > 0
");

    $qnumbers = array();
    $number = 1;
    foreach (explode(',', $questionids) as $key => $id) {
        if (!array_key_exists($id, $questions)) {
            continue;
        }

        $qnumber = $key + 1;
        $question = $questions[$id];
        $question->qnumber = $qnumber;
        $question->number = $number;

        $qnumbers[$qnumber] = $question;

        $number += $question->length;
    }
    return $qnumbers;
}

/**
 * Given the quiz grading method return sub select sql to find the id of the
 * one attempt that will be graded for each user. Or return
 * empty string if all attempts contribute to final grade.
 */
function quiz_report_qm_filter_select($quiz, $quizattemptsalias = 'qa') {
    global $CFG;
    if ($quiz->attempts == 1) {//only one attempt allowed on this quiz
        return '';
    }
    $useridsql = "$quizattemptsalias.userid";
    $quizidsql = "$quizattemptsalias.quiz";
    $qmfilterattempts = true;
    switch ($quiz->grademethod) {
    case QUIZ_GRADEHIGHEST :
        return "$quizattemptsalias.id = (
                SELECT MIN(qa2.id)
                FROM {$CFG->prefix}quiz_attempts qa2
                WHERE qa2.quiz = $quizidsql AND qa2.userid = $useridsql AND
                    COALESCE(qa2.sumgrades, 0) = (
                        SELECT MAX(COALESCE(qa3.sumgrades, 0))
                        FROM {$CFG->prefix}quiz_attempts qa3
                        WHERE qa3.quiz = $quizidsql AND qa3.userid = $useridsql
                    )
                )";

    case QUIZ_GRADEAVERAGE :
        return '';

    case QUIZ_ATTEMPTFIRST :
        return "$quizattemptsalias.id = (
                SELECT MIN(qa2.id)
                FROM {$CFG->prefix}quiz_attempts qa2
                WHERE qa2.quiz = $quizidsql AND qa2.userid = $useridsql)";

    case QUIZ_ATTEMPTLAST :
        return "$quizattemptsalias.id = (
                SELECT MAX(qa2.id)
                FROM {$CFG->prefix}quiz_attempts qa2
                WHERE qa2.quiz = $quizidsql AND qa2.userid = $useridsql)";
    }
}

/**
 * Get the nuber of students whose score was in a particular band for this quiz.
 * @param number $bandwidth the width of each band.
 * @param integer $bands the number of bands
 * @param integer $quizid the quiz id.
 * @param array $userids list of user ids.
 * @return array band number => number of users with scores in that band.
 */
function quiz_report_grade_bands($bandwidth, $bands, $quizid, $userids = array()) {
    global $CFG;

    if ($userids) {
        list($usql, $params) = get_in_or_equal($userids);
        $usql = "qg.userid $usql AND";
    } else {
        $usql ='';
    }

    $sql = "
SELECT
    FLOOR(qg.grade/$bandwidth) AS band,
    COUNT(1) AS num

FROM {$CFG->prefix}quiz_grades qg
JOIN {$CFG->prefix}quiz q ON qg.quiz = q.id

WHERE
    $usql
    qg.quiz = $quizid

GROUP BY
    FLOOR(qg.grade/$bandwidth)

ORDER BY
    band";

    $data = get_records_sql_menu($sql);

    //need to create array elements with values 0 at indexes where there is no element
    $data =  $data + array_fill(0, $bands+1, 0);
    ksort($data);

    //place the maximum (prefect grade) into the last band i.e. make last
    //band for example 9 <= g <=10 (where 10 is the perfect grade) rather than
    //just 9 <= g <10.
    $data[$bands-1] += $data[$bands];
    unset($data[$bands]);

    return $data;
}

function quiz_report_highlighting_grading_method($quiz, $qmsubselect, $qmfilter) {
    if ($quiz->attempts == 1) {
        return '<p>' . get_string('onlyoneattemptallowed', 'quiz_overview') . '</p>';

    } else if (!$qmsubselect) {
        return '<p>' . get_string('allattemptscontributetograde', 'quiz_overview') . '</p>';

    } else if ($qmfilter) {
        return '<p>' . get_string('showinggraded', 'quiz_overview') . '</p>';

    } else {
        return '<p>' . get_string('showinggradedandungraded', 'quiz_overview',
                '<span class="highlight">' . quiz_get_grading_option_name($quiz->grademethod) .
                '</span>') . '</p>';
    }
}

/**
 * Get the feedback text for a grade on this quiz. The feedback is
 * processed ready for display.
 *
 * @param float $grade a grade on this quiz.
 * @param integer $quizid the id of the quiz object.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function quiz_report_feedback_for_grade($grade, $quizid) {
    static $feedbackcache = array();

    if (is_null($grade)) {
        return '';
    }

    if (!isset($feedbackcache[$quizid])) {
        $feedbackcache[$quizid] = get_records('quiz_feedback', 'quizid', $quizid);
    }
    $feedbacks = $feedbackcache[$quizid];
    $feedbacktext = '';
    foreach ($feedbacks as $feedback) {
        if ($feedback->mingrade <= $grade && $grade < $feedback->maxgrade) {
            $feedbacktext = $feedback->feedbacktext;
            break;
        }
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass;
    $formatoptions->noclean = true;
    $feedbacktext = format_text($feedbacktext, FORMAT_MOODLE, $formatoptions);

    return $feedbacktext;
}

/**
 * Format a number as a percentage out of $quiz->sumgrades
 * @param number $rawgrade the mark to format.
 * @param object $quiz the quiz settings
 * @param boolean $round whether to round the results ot $quiz->decimalpoints.
 */
function quiz_report_scale_sumgrades_as_percentage($rawgrade, $quiz, $round = true) {
    if ($quiz->sumgrades == 0) {
        return '';
    }

    $grade = $rawgrade * 100 / $quiz->sumgrades;
    if ($round) {
        $grade = quiz_format_grade($quiz, $grade);
    }
    return $grade . '%';
}

/**
 * Returns an array of reports to which the current user has access to.
 * @return array reports are ordered as they should be for display in tabs.
 */
function quiz_report_list($context) {
    static $reportlist = null;
    if (!is_null($reportlist)) {
        return $reportlist;
    }

    $reports = get_records('quiz_reports', '', '', 'displayorder DESC', 'name, capability');

    $reportdirs = get_list_of_plugins('mod/quiz/report');

    // Order the reports tab in descending order of displayorder
    $reportcaps = array();
    if ($reports) {
        foreach ($reports as $key => $report) {
            if (in_array($report->name, $reportdirs)) {
                $reportcaps[$report->name] = $report->capability;
            }
        }
    }

    // Add any other reports, which are on disc but not in the DB, on the end
    foreach ($reportdirs as $reportname) {
        if (!isset($reportcaps[$reportname])) {
            $reportcaps[$reportname] = null;
        }
    }
    $reportlist = array();
    foreach ($reportcaps as $name => $capability) {
        if (empty($capability)) {
            $capability = 'mod/quiz:viewreports';
        }
        if ($has = has_capability($capability, $context)) {
            $reportlist[] = $name;
        }
    }
    return $reportlist;
}
