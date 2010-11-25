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
 * Library of functions used by the quiz module.
 *
 * This contains functions that are called from within the quiz module only
 * Functions that are also called by core Moodle are in {@link lib.php}
 * This script also loads the code in {@link questionlib.php} which holds
 * the module-indpendent code for handling questions and which in turn
 * initialises all the questiontype classes.
 *
 * @package mod_quiz
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Include those library functions that are also used by core Moodle or other modules
 */
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrules.php');
require_once($CFG->dirroot . '/mod/quiz/attemptlib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->dirroot . '/question/engine/compatibility.php');
require_once($CFG->libdir . '/eventslib.php');

/// Constants ///////////////////////////////////////////////////////////////////

/**#@+
 * Options determining how the grades from individual attempts are combined to give
 * the overall grade for a user
 */
define("QUIZ_GRADEHIGHEST", "1");
define("QUIZ_GRADEAVERAGE", "2");
define("QUIZ_ATTEMPTFIRST", "3");
define("QUIZ_ATTEMPTLAST",  "4");
/**#@-*/


/**
 * We show the countdown timer if there is less than this amount of time left before the
 * the quiz close date. (1 hour)
 */
define('QUIZ_SHOW_TIME_BEFORE_DEADLINE', '3600');

/// Functions related to attempts /////////////////////////////////////////

/**
 * Creates an object to represent a new attempt at a quiz
 *
 * Creates an attempt object to represent an attempt at the quiz by the current
 * user starting at the current time. The ->id field is not set. The object is
 * NOT written to the database.
 *
 * @param object $quiz the quiz to create an attempt for.
 * @param integer $attemptnumber the sequence number for the attempt.
 * @param object $lastattempt the previous attempt by this user, if any. Only needed
 *         if $attemptnumber > 1 and $quiz->attemptonlast is true.
 * @param integer $timenow the time the attempt was started at.
 * @param boolean $ispreview whether this new attempt is a preview.
 *
 * @return object the newly created attempt object.
 */
function quiz_create_attempt($quiz, $attemptnumber, $lastattempt, $timenow, $ispreview = false) {
    global $USER;

    if ($attemptnumber == 1 || !$quiz->attemptonlast) {
    /// We are not building on last attempt so create a new attempt.
        $attempt = new stdClass;
        $attempt->quiz = $quiz->id;
        $attempt->userid = $USER->id;
        $attempt->preview = 0;
        if ($quiz->shufflequestions) {
            $attempt->layout = quiz_clean_layout(quiz_repaginate($quiz->questions, $quiz->questionsperpage, true),true);
        } else {
            $attempt->layout = quiz_clean_layout($quiz->questions,true);
        }
    } else {
    /// Build on last attempt.
        if (empty($lastattempt)) {
            print_error('cannotfindprevattempt', 'quiz');
        }
        $attempt = $lastattempt;
    }

    $attempt->attempt = $attemptnumber;
    $attempt->timestart = $timenow;
    $attempt->timefinish = 0;
    $attempt->timemodified = $timenow;

/// If this is a preview, mark it as such.
    if ($ispreview) {
        $attempt->preview = 1;
    }

    return $attempt;
}

/**
 * Returns the most recent attempt by a given user on a given quiz.
 * May be finished, or may not.
 *
 * @param integer $quizid the id of the quiz.
 * @param integer $userid the id of the user.
 *
 * @return mixed the attempt if there is one, false if not.
 */
function quiz_get_latest_attempt_by_user($quizid, $userid) {
    global $CFG;
    $attempt = get_records_sql("SELECT qa.* FROM {$CFG->prefix}quiz_attempts qa
            WHERE qa.quiz={$quizid} AND qa.userid={$userid} ORDER BY qa.timestart DESC, qa.id DESC", 0, 1);
    if ($attempt) {
        return array_shift($attempt);
    } else {
        return false;
    }
}

/**
 * Load an attempt by id. You need to use this method instead of get_record, because
 * of some ancient history to do with the upgrade from Moodle 1.4 to 1.5, See the comment
 * after CREATE TABLE `prefix_quiz_newest_states` in mod/quiz/db/mysql.php.
 *
 * @param integer $attemptid the id of the attempt to load.
 */
function quiz_load_attempt($attemptid) {
    $attempt = get_record('quiz_attempts', 'id', $attemptid);
    if (!$attempt) {
        return false;
    }

    // TODO deal with the issue that makes this necessary.
//    if (!record_exists('question_sessions', 'attemptid', $attempt->uniqueid)) {
//    /// this attempt has not yet been upgraded to the new model
//        quiz_upgrade_states($attempt);
//    }

    return $attempt;
}

/**
 * Returns an unfinished attempt (if there is one) for the given
 * user on the given quiz. This function does not return preview attempts.
 *
 * @param integer $quizid the id of the quiz.
 * @param integer $userid the id of the user.
 *
 * @return mixed the unfinished attempt if there is one, false if not.
 */
function quiz_get_user_attempt_unfinished($quizid, $userid) {
    $attempts = quiz_get_user_attempts($quizid, $userid, 'unfinished', true);
    if ($attempts) {
        return array_shift($attempts);
    } else {
        return false;
    }
}

/**
 * Delete a quiz attempt.
 * @param mixed $attempt an integer attempt id or an attempt object (row of the quiz_attempts table).
 * @param object $quiz the quiz object.
 */
function quiz_delete_attempt($attempt, $quiz) {
    if (is_numeric($attempt)) {
        if (!$attempt = get_record('quiz_attempts', 'id', $attempt)) {
            return;
        }
    }

    if ($attempt->quiz != $quiz->id) {
        debugging("Trying to delete attempt $attempt->id which belongs to quiz $attempt->quiz " .
                "but was passed quiz $quiz->id.");
        return;
    }

    delete_records('quiz_attempts', 'id', $attempt->id);
    question_engine::delete_questions_usage_by_activity($attempt->uniqueid);

    // Search quiz_attempts for other instances by this user.
    // If none, then delete record for this quiz, this user from quiz_grades
    // else recalculate best grade

    $userid = $attempt->userid;
    if (!record_exists('quiz_attempts', 'userid', $userid, 'quiz', $quiz->id)) {
        delete_records('quiz_grades', 'userid', $userid,'quiz', $quiz->id);
    } else {
        quiz_save_best_grade($quiz, $userid);
    }

    quiz_update_grades($quiz, $userid);
}

/**
 * Delete all the preview attempts at a quiz, or possibly all the attempts belonging
 * to one user.
 * @param object $quiz the quiz object.
 * @param integer $userid (optional) if given, only delete the previews belonging to this user.
 */
function quiz_delete_previews($quiz, $userid = null) {
    $conditions = "quiz = '$quiz->id' AND preview = '1'";
    if (!empty($userid)) {
        $conditions .= " AND userid = '$userid'";
    }
    $previewattempts = get_records_select('quiz_attempts', $conditions);
    if (!$previewattempts) {
        return;
    }
    foreach ($previewattempts as $attempt) {
        quiz_delete_attempt($attempt, $quiz);
    }
}

/**
 * @param integer $quizid The quiz id.
 * @return boolean whether this quiz has any (non-preview) attempts.
 */
function quiz_has_attempts($quizid) {
    return record_exists('quiz_attempts', 'quiz', $quizid, 'preview', 0);
}

/// Functions to do with quiz layout and pages ////////////////////////////////

/**
 * Returns a comma separated list of question ids for the current page
 *
 * @return string         Comma separated list of question ids
 * @param string $layout  The string representing the quiz layout. Each page is represented as a
 *                        comma separated list of question ids and 0 indicating page breaks.
 *                        So 5,2,0,3,0 means questions 5 and 2 on page 1 and question 3 on page 2
 * @param integer $page   The number of the current page.
 */
function quiz_questions_on_page($layout, $page) {
    $pages = explode(',0', $layout);
    return trim($pages[$page], ',');
}

/**
 * Returns a comma separated list of question ids for the quiz
 *
 * @return string         Comma separated list of question ids
 * @param string $layout  The string representing the quiz layout. Each page is represented as a
 *                        comma separated list of question ids and 0 indicating page breaks.
 *                        So 5,2,0,3,0 means questions 5 and 2 on page 1 and question 3 on page 2
 */
function quiz_questions_in_quiz($layout) {
    return str_replace(',0', '', $layout);
}

/**
 * Returns the number of pages in the quiz layout
 *
 * @return integer         Comma separated list of question ids
 * @param string $layout  The string representing the quiz layout.
 */
function quiz_number_of_pages($layout) {
    return substr_count($layout, ',0');
}

/**
 * Re-paginates the quiz layout
 *
 * @return string         The new layout string
 * @param string $layout  The string representing the quiz layout.
 * @param integer $perpage The number of questions per page
 * @param boolean $shuffle Should the questions be reordered randomly?
 */
function quiz_repaginate($layout, $perpage, $shuffle=false) {
    $layout = str_replace(',0', '', $layout); // remove existing page breaks
    $questions = explode(',', $layout);
    if ($shuffle) {
        srand((float)microtime() * 1000000); // for php < 4.2
        shuffle($questions);
    }
    $i = 1;
    $layout = '';
    foreach ($questions as $question) {
        if ($perpage and $i > $perpage) {
            $layout .= '0,';
            $i = 1;
        }
        $layout .= $question.',';
        $i++;
    }
    return $layout.'0';
}

/// Functions to do with quiz grades //////////////////////////////////////////

/**
 * Creates an array of maximum grades for a quiz
 *
 * The grades are extracted from the quiz_question_instances table.
 * @return array        Array of grades indexed by question id
 *                      These are the maximum possible grades that
 *                      students can achieve for each of the questions
 * @param integer $quiz The quiz object
 */
function quiz_get_all_question_grades($quiz) {
    global $CFG;

    $questionlist = quiz_questions_in_quiz($quiz->questions);
    if (empty($questionlist)) {
        return array();
    }

    $instances = get_records_sql("SELECT question,grade,id
                            FROM {$CFG->prefix}quiz_question_instances
                            WHERE quiz = '$quiz->id'" .
                            (is_null($questionlist) ? '' :
                            "AND question IN ($questionlist)"));

    $list = explode(",", $questionlist);
    $grades = array();

    foreach ($list as $qid) {
        if (isset($instances[$qid])) {
            $grades[$qid] = $instances[$qid]->grade;
        } else {
            $grades[$qid] = 1;
        }
    }
    return $grades;
}

/**
 * Convert the raw grade stored in $attempt into a grade out of the maximum
 * grade for this quiz.
 *
 * @param float $rawgrade the unadjusted grade, fof example $attempt->sumgrades
 * @param object $quiz the quiz object. Only the fields grade, sumgrades and decimalpoints are used.
 * @param boolean|string $format whether to format the results for display
 *      or 'question' to format a question grade (different number of decimal places.
 * @return float|string the rescaled grade, or null/the lang string 'notyetgraded' if the $grade is null.
 */
function quiz_rescale_grade($rawgrade, $quiz, $format = true) {
    if (is_null($rawgrade)) {
        $grade = null;
    } else if ($quiz->sumgrades) {
        $grade = $rawgrade * $quiz->grade / $quiz->sumgrades;
    } else {
        $grade = 0;
    }
    if ($format === 'question') {
        $grade = quiz_format_question_grade($quiz, $grade);
    } else if ($format) {
        $grade = quiz_format_grade($quiz, $grade);
    }
    return $grade;
}

/**
 * Get the feedback text that should be show to a student who
 * got this grade on this quiz. The feedback is processed ready for diplay.
 *
 * @param float $grade a grade on this quiz.
 * @param integer $quizid the id of the quiz object.
 * @return string the comment that corresponds to this grade (empty string if there is not one.
 */
function quiz_feedback_for_grade($grade, $quizid) {
    if (is_null($grade)) {
        return '';
    }

    $feedback = get_field_select('quiz_feedback', 'feedbacktext',
            "quizid = $quizid AND mingrade <= $grade AND $grade < maxgrade");

    if (empty($feedback)) {
        $feedback = '';
    }

    // Clean the text, ready for display.
    $formatoptions = new stdClass;
    $formatoptions->noclean = true;
    $feedback = format_text($feedback, FORMAT_MOODLE, $formatoptions);

    return $feedback;
}

/**
 * @param object $quiz the quiz database row.
 * @return boolean Whether this quiz has any non-blank feedback text.
 */
function quiz_has_feedback($quiz) {
    static $cache = array();
    if (!array_key_exists($quiz->id, $cache)) {
        $cache[$quiz->id] = quiz_has_grades($quiz) &&
                record_exists_select('quiz_feedback', "quizid = $quiz->id AND " .
                    sql_isnotempty('quiz_feedback', 'feedbacktext', false, true));
    }
    return $cache[$quiz->id];
}

function quiz_update_sumgrades($quiz) {
    global $CFG;
    $sql = "UPDATE {$CFG->prefix}quiz
            SET sumgrades = COALESCE((
                SELECT SUM(grade)
                FROM {$CFG->prefix}quiz_question_instances
                WHERE quiz = {$CFG->prefix}quiz.id
            ), 0)
            WHERE id = $quiz->id";
    execute_sql($sql, false);
    $quiz->sumgrades = get_field('quiz', 'sumgrades', 'id', $quiz->id);
}

function quiz_update_all_attempt_sumgrades($quiz) {
    global $CFG;
    $dm = new question_engine_data_mapper();
    $timenow = time();

    $sql = "UPDATE {$CFG->prefix}quiz_attempts
            SET
                timemodified = $timenow,
                sumgrades = (
                    {$dm->sum_usage_marks_subquery('uniqueid')}
                )
            WHERE quiz = $quiz->id AND timefinish <> 0";
    execute_sql($sql, false);
}

/**
 * The quiz grade is the score that student's results are marked out of. When it
 * changes, the corresponding data in quiz_grades and quiz_feedback needs to be
 * rescaled. After calling this function, you probably need to call
 * quiz_update_all_attempt_sumgrades, quiz_update_all_final_grades and
 * quiz_update_grades.
 *
 * @param float $newgrade the new maximum grade for the quiz.
 * @param object $quiz the quiz we are updating. Passed by reference so its grade field can be updated too.
 * @return boolean indicating success or failure.
 */
function quiz_set_grade($newgrade, $quiz) {
    // This is potentially expensive, so only do it if necessary.
    if (abs($quiz->grade - $newgrade) < 1e-7) {
        // Nothing to do.
        return true;
    }

    // Use a transaction, so that on those databases that support it, this is safer.
    begin_sql();

    // Update the quiz table.
    $success = set_field('quiz', 'grade', $newgrade, 'id', $quiz->instance);

    // Rescaling the other data is only possible if the old grade was non-zero.
    if ($quiz->grade > 1e-7) {
        global $CFG;

        $factor = $newgrade/$quiz->grade;
        $quiz->grade = $newgrade;

        // Update the quiz_grades table.
        $timemodified = time();
        $success = $success && execute_sql("
                UPDATE {$CFG->prefix}quiz_grades
                SET grade = $factor * grade, timemodified = $timemodified
                WHERE quiz = $quiz->id
        ", false);

        // Update the quiz_feedback table.
        $success = $success && execute_sql("
                UPDATE {$CFG->prefix}quiz_feedback
                SET mingrade = $factor * mingrade, maxgrade = $factor * maxgrade
                WHERE quizid = $quiz->id
        ", false);
    }

    if ($success) {
        return commit_sql();
    } else {
        rollback_sql();
        return false;
    }
}

/**
 * Save the overall grade for a user at a quiz in the quiz_grades table
 *
 * @param object $quiz The quiz for which the best grade is to be calculated and then saved.
 * @param integer $userid The userid to calculate the grade for. Defaults to the current user.
 */
function quiz_save_best_grade($quiz, $userid = null) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    // Get all the attempts made by the user
    if (!$attempts = quiz_get_user_attempts($quiz->id, $userid)) {
        throw new moodle_exception('noattemptsfound', 'quiz');
    }

    // Calculate the best grade
    $bestgrade = quiz_calculate_best_grade($quiz, $attempts);
    $bestgrade = quiz_rescale_grade($bestgrade, $quiz, false);

    // Save the best grade in the database
    if (is_null($bestgrade)) {
        delete_records('quiz_grades', 'quiz', $quiz->id, 'userid', $userid);

    } else if ($grade = get_record('quiz_grades', 'quiz', $quiz->id, 'userid', $userid)) {
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        update_record('quiz_grades', $grade);

    } else {
        $grade->quiz = $quiz->id;
        $grade->userid = $userid;
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        insert_record('quiz_grades', $grade);
    }

    quiz_update_grades($quiz, $userid);
}

/**
 * Calculate the overall grade for a quiz given a number of attempts by a particular user.
 *
 * @return float          The overall grade
 * @param object $quiz    The quiz for which the best grade is to be calculated
 * @param array $attempts An array of all the attempts of the user at the quiz
 */
function quiz_calculate_best_grade($quiz, $attempts) {
    switch ($quiz->grademethod) {

        case QUIZ_ATTEMPTFIRST:
            foreach ($attempts as $attempt) {
                return $attempt->sumgrades;
            }
            return $final;

        case QUIZ_ATTEMPTLAST:
            foreach ($attempts as $attempt) {
                $final = $attempt->sumgrades;
            }
            return $final;

        case QUIZ_GRADEAVERAGE:
            $sum = 0;
            $count = 0;
            foreach ($attempts as $attempt) {
                if (!is_null($attempt->sumgrades)) {
                    $sum += $attempt->sumgrades;
                    $count++;
                }
            }
            if ($count == 0) {
                return null;
            }
            return $sum / $count;

        default:
        case QUIZ_GRADEHIGHEST:
            $max = null;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                }
            }
            return $max;
    }
}

/**
 * Update the final grade at this quiz for all students.
 *
 * This function is equivalent to calling quiz_save_best_grade for all
 * users, but much more efficient.
 *
 * @param object $quiz the quiz settings.
 */
function quiz_update_all_final_grades($quiz) {
    global $CFG;

    if (!$quiz->sumgrades) {
        return;
    }

    $firstlastattemptjoin = "JOIN (
            SELECT
                iquiza.userid,
                MIN(attempt) AS firstattempt,
                MAX(attempt) AS lastattempt

            FROM {$CFG->prefix}quiz_attempts iquiza

            WHERE
                iquiza.timefinish <> 0 AND
                iquiza.preview = 0 AND
                iquiza.quiz = $quiz->id

            GROUP BY iquiza.userid
        ) first_last_attempts ON first_last_attempts.userid = quiza.userid";

    switch ($quiz->grademethod) {
        case QUIZ_ATTEMPTFIRST:
            // Becuase of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(quiza.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'quiza.attempt = first_last_attempts.firstattempt AND';
            break;

        case QUIZ_ATTEMPTLAST:
            // Becuase of the where clause, there will only be one row, but we
            // must still use an aggregate function.
            $select = 'MAX(quiza.sumgrades)';
            $join = $firstlastattemptjoin;
            $where = 'quiza.attempt = first_last_attempts.lastattempt AND';
            break;

        case QUIZ_GRADEAVERAGE:
            $select = 'AVG(quiza.sumgrades)';
            $join = '';
            $where = '';
            break;

        default:
        case QUIZ_GRADEHIGHEST:
            $select = 'MAX(quiza.sumgrades)';
            $join = '';
            $where = '';
            break;
    }

    $finalgradesubquery = "
            SELECT quiza.userid, $select * $quiz->grade / $quiz->sumgrades AS newgrade
            FROM {$CFG->prefix}quiz_attempts quiza
            $join
            WHERE
                $where
                quiza.timefinish <> 0 AND
                quiza.preview = 0 AND
                quiza.quiz = $quiz->id
            GROUP BY quiza.userid";

    $changedgrades = get_records_sql("
            SELECT users.userid, qg.id, qg.grade, newgrades.newgrade

            FROM (
                SELECT userid
                FROM {$CFG->prefix}quiz_grades qg
                WHERE quiz = $quiz->id
            UNION
                SELECT DISTINCT userid
                FROM {$CFG->prefix}quiz_attempts quiza2
                WHERE
                    quiza2.timefinish <> 0 AND
                    quiza2.preview = 0 AND
                    quiza2.quiz = $quiz->id
            ) users

            LEFT JOIN {$CFG->prefix}quiz_grades qg ON qg.userid = users.userid AND qg.quiz = $quiz->id

            LEFT JOIN (
                $finalgradesubquery
            ) newgrades ON newgrades.userid = users.userid

            WHERE
                ABS(newgrades.newgrade - qg.grade) > 0.000005 OR
                (newgrades.newgrade IS NULL) <> (qg.grade IS NULL)");

    if (empty($changedgrades)) {
        return;
    }

    $timenow = time();
    $todelete = array();
    foreach ($changedgrades as $changedgrade) {

        if (is_null($changedgrade->newgrade)) {
            $todelete[] = $changedgrade->userid;

        } else if (is_null($changedgrade->grade)) {
            $toinsert = new stdClass;
            $toinsert->quiz = $quiz->id;
            $toinsert->userid = $changedgrade->userid;
            $toinsert->timemodified = $timenow;
            $toinsert->grade = $changedgrade->newgrade;
            insert_record('quiz_grades', $toinsert);

        } else {
            $toupdate = new stdClass;
            $toupdate->id = $changedgrade->id;
            $toupdate->grade = $changedgrade->newgrade;
            $toupdate->timemodified = $timenow;
            update_record('quiz_grades', $toupdate);
        }
    }

    if (!empty($todelete)) {
        delete_records_select('quiz_grades', "quiz = $quiz->id AND userid IN (" .
                implode(',', $todelete) . ")");
    }
}

/**
 * Return the attempt with the best grade for a quiz
 *
 * Which attempt is the best depends on $quiz->grademethod. If the grade
 * method is GRADEAVERAGE then this function simply returns the last attempt.
 * @return object         The attempt with the best grade
 * @param object $quiz    The quiz for which the best grade is to be calculated
 * @param array $attempts An array of all the attempts of the user at the quiz
 */
function quiz_calculate_best_attempt($quiz, $attempts) {

    switch ($quiz->grademethod) {

        case QUIZ_ATTEMPTFIRST:
            foreach ($attempts as $attempt) {
                return $attempt;
            }
            break;

        case QUIZ_GRADEAVERAGE: // need to do something with it :-)
        case QUIZ_ATTEMPTLAST:
            foreach ($attempts as $attempt) {
                $final = $attempt;
            }
            return $final;

        default:
        case QUIZ_GRADEHIGHEST:
            $max = -1;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                    $maxattempt = $attempt;
                }
            }
            return $maxattempt;
    }
}

/**
 * @return the options for calculating the quiz grade from the individual attempt grades.
 */
function quiz_get_grading_options() {
    return array (
            QUIZ_GRADEHIGHEST => get_string('gradehighest', 'quiz'),
            QUIZ_GRADEAVERAGE => get_string('gradeaverage', 'quiz'),
            QUIZ_ATTEMPTFIRST => get_string('attemptfirst', 'quiz'),
            QUIZ_ATTEMPTLAST  => get_string('attemptlast', 'quiz'));
}

/**
 * @param int $option one of the values QUIZ_GRADEHIGHEST, QUIZ_GRADEAVERAGE, QUIZ_ATTEMPTFIRST or QUIZ_ATTEMPTLAST.
 * @return the lang string for that option.
 */
function quiz_get_grading_option_name($option) {
    $strings = quiz_get_grading_options();
    return $strings[$option];
}

/// Other quiz functions ////////////////////////////////////////////////////

/**
 * Upgrade states for an attempt to Moodle 1.5 model
 *
 * Any state that does not yet have its timestamp set to nonzero has not yet been upgraded from Moodle 1.4
 * The reason these are still around is that for large sites it would have taken too long to
 * upgrade all states at once. This function sets the timestamp field and creates an entry in the
 * question_sessions table.
 * @param object $attempt  The attempt whose states need upgrading
 */
function quiz_upgrade_states($attempt) {
    global $CFG;
    // The old quiz model only allowed a single response per quiz attempt so that there will be
    // only one state record per question for this attempt.

    // We set the timestamp of all states to the timemodified field of the attempt.
    execute_sql("UPDATE {$CFG->prefix}question_states SET timestamp = '$attempt->timemodified' WHERE attempt = '$attempt->uniqueid'", false);

    // For each state we create an entry in the question_sessions table, with both newest and
    // newgraded pointing to this state.
    // Actually we only do this for states whose question is actually listed in $attempt->layout.
    // We do not do it for states associated to wrapped questions like for example the questions
    // used by a RANDOM question
    $session = new stdClass;
    $session->attemptid = $attempt->uniqueid;
    $questionlist = quiz_questions_in_quiz($attempt->layout);
    if ($questionlist and $states = get_records_select('question_states', "attempt = '$attempt->uniqueid' AND question IN ($questionlist)")) {
        foreach ($states as $state) {
            $session->newgraded = $state->id;
            $session->newest = $state->id;
            $session->questionid = $state->question;
            insert_record('question_sessions', $session, false);
        }
    }
}

/**
 * @param object $quiz the quiz.
 * @param integer $cmid the course_module object for this quiz.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @return string html for a number of icons linked to action pages for a
 * question - preview and edit / view icons depending on user capabilities.
 */
function quiz_question_action_icons($quiz, $cmid, $question, $returnurl) {
    $html = quiz_question_preview_button($quiz, $question) . ' ' .
            quiz_question_edit_button($cmid, $question, $returnurl);
    return $html;
}

/**
 * @param integer $cmid the course_module.id for this quiz.
 * @param object $question the question.
 * @param string $returnurl url to return to after action is done.
 * @param string $contentbeforeicon some HTML content to be added inside the link, before the icon.
 * @return the HTML for an edit icon, view icon, or nothing for a question (depending on permissions).
 */
function quiz_question_edit_button($cmid, $question, $returnurl, $contentbeforeicon = '') {
    global $CFG;

    // Minor efficiency saving. Only get strings once, even if there are a lot of icons on one page.
    static $stredit = null;
    static $strview = null;
    if ($stredit === null){
        $stredit = get_string('edit');
        $strview = get_string('view');
    }

    // What sort of icon should we show?
    $action = '';
    if (question_has_capability_on($question, 'edit', $question->category) ||
            question_has_capability_on($question, 'move', $question->category)) {
        $action = $stredit;
        $icon = '/t/edit';
    } else if (question_has_capability_on($question, 'view', $question->category)) {
        $action = $strview;
        $icon = '/i/info';
    }

    // Build the icon.
    if ($action) {
        $questionparams = array('returnurl' => $returnurl, 'cmid' => $cmid, 'id' => $question->id);
        $questionurl = new moodle_url("$CFG->wwwroot/question/question.php", $questionparams);
        return '<a title="' . $action . '" href="' . $questionurl->out() . '">' . $contentbeforeicon .
                '<img src="' . $CFG->pixpath . $icon . '.gif" alt="' . $action . '" /></a>';
    } else {
        return $contentbeforeicon;
    }
}

/**
 * @param object $quiz the quiz
 * @param object $question the question
 * @param boolean $label if true, show the preview question label after the icon
 * @return the HTML for a preview question icon.
 */
function quiz_question_preview_button($quiz, $question, $label = false) {
    global $CFG, $COURSE, $USER;
    if (!question_has_capability_on($question, 'use', $question->category)) {
        return '';
    }

    // Get the appropriate display options.
    $displayoptions = mod_quiz_display_options::make_from_quiz($quiz,
            mod_quiz_display_options::DURING);

    // Work out the correcte preview URL.
    $url = question_preview_url($question->id, $quiz->preferredbehaviour,
            $question->maxmark, $displayoptions);
    $url = str_replace($CFG->wwwroot, '', $url);

    // Do we want a label?
    $strpreviewlabel = '';
    if ($label) {
        $strpreviewlabel = get_string('preview', 'quiz');
    }

    // Build the icon.
    $strpreviewquestion = get_string('previewquestion', 'quiz');
    return link_to_popup_window($url, 'questionpreview',
            '<img src="' . $CFG->pixpath . '/t/preview.gif" class="iconsmall" alt="' .
            $strpreviewquestion . '" /> ' . $strpreviewlabel, 0, 0, $strpreviewquestion,
            QUESTION_PREVIEW_POPUP_OPTIONS, true);
}

/**
 * @param object $attempt the attempt.
 * @param object $context the quiz context.
 * @return integer whether flags should be shown/editable to the current user for this attempt.
 */
function quiz_get_flag_option($attempt, $context) {
    global $USER;
    if (!has_capability('moodle/question:flag', $context)) {
        return question_display_options::HIDDEN;
    } else if ($attempt->userid == $USER->id) {
        return question_display_options::EDITABLE;
    } else {
        return question_display_options::VISIBLE;
    }
}

/**
 * Work out what state this quiz attempt is in.
 * @param object $quiz the quiz settings
 * @param object $attempt the quiz_attempt database row.
 * @return integer one of the mod_quiz_display_options::DURING,
 *      IMMEDIATELY_AFTER, LATER_WHILE_OPEN or AFTER_CLOSE constants.
 */
function quiz_attempt_state($quiz, $attempt) {
    if ($attempt->timefinish == 0) {
        return mod_quiz_display_options::DURING;
    } else if (time() < $attempt->timefinish + 120) {
        return mod_quiz_display_options::IMMEDIATELY_AFTER;
    } else if (!$quiz->timeclose || time() < $quiz->timeclose) {
        return mod_quiz_display_options::LATER_WHILE_OPEN;
    } else {
        return mod_quiz_display_options::AFTER_CLOSE;
    }
}

/**
 * The the appropraite mod_quiz_display_options object for this attempt at this
 * quiz right now.
 *
 * @param object $quiz the quiz instance.
 * @param object $attempt the attempt in question.
 * @param $context the roles and permissions context,
 *          normally the context for the quiz module instance.
 *
 * @return mod_quiz_display_options
 */
function quiz_get_reviewoptions($quiz, $attempt, $context) {
    $options = mod_quiz_display_options::make_from_quiz($quiz, quiz_attempt_state($quiz, $attempt));

    $options->readonly = true;
    $options->flags = quiz_get_flag_option($attempt, $context);
    $options->questionreviewlink = '/mod/quiz/reviewquestion.php?attempt=' . $attempt->id;

    // Show a link to the comment box only for closed attempts
    if ($attempt->timefinish && !$attempt->preview && !is_null($context) &&
            has_capability('mod/quiz:grade', $context)) {
        $options->manualcomment = question_display_options::VISIBLE;
        $options->manualcommentlink = '/mod/quiz/comment.php?attempt=' . $attempt->id;
    }

    if (!is_null($context) && !$attempt->preview && has_capability('mod/quiz:viewreports', $context) &&
            has_capability('moodle/grade:viewhidden', $context)) {
        // People who can see reports and hidden grades should be shown everything,
        // except during preview when teachers want to see what students see.
        $options->attempt = question_display_options::VISIBLE;
        $options->correctness = question_display_options::VISIBLE;
        $options->marks = question_display_options::MARK_AND_MAX;
        $options->feedback = question_display_options::VISIBLE;
        $options->generalfeedback = question_display_options::VISIBLE;
        $options->rightanswer = question_display_options::VISIBLE;
        $options->overallfeedback = question_display_options::VISIBLE;
        $options->history = question_display_options::VISIBLE;
    }

    return $options;
}

/**
 * Combines the review options from a number of different quiz attempts.
 * Returns an array of two ojects, so the suggested way of calling this
 * funciton is:
 * list($someoptions, $alloptions) = quiz_get_combined_reviewoptions(...)
 *
 * @param object $quiz the quiz instance.
 * @param array $attempts an array of attempt objects.
 * @param $context the roles and permissions context,
 *          normally the context for the quiz module instance.
 *
 * @return array of two options objects, one showing which options are true for
 *          at least one of the attempts, the other showing which options are true
 *          for all attempts.
 */
function quiz_get_combined_reviewoptions($quiz, $attempts) {
    $fields = array('marks', 'feedback', 'generalfeedback', 'rightanswer', 'overallfeedback');
    $someoptions = new stdClass;
    $alloptions = new stdClass;
    foreach ($fields as $field) {
        $someoptions->$field = false;
        $alloptions->$field = true;
    }
    foreach ($attempts as $attempt) {
        $attemptoptions = mod_quiz_display_options::make_from_quiz($quiz,
                quiz_attempt_state($quiz, $attempt));
        foreach ($fields as $field) {
            $someoptions->$field = $someoptions->$field || $attemptoptions->$field;
            $alloptions->$field = $alloptions->$field && $attemptoptions->$field;
        }
    }
    return array($someoptions, $alloptions);
}

/**
 * Clean the question layout from various possible anomalies:
 * - Remove consecutive ","'s
 * - Remove duplicate question id's
 * - Remove extra "," from beginning and end
 * - Finally, add a ",0" in the end if there is none
 *
 * @param $string $layout the quiz layout to clean up, usually from $quiz->questions.
 * @param boolean $removeemptypages If true, remove empty pages from the quiz. False by default.
 * @return $string the cleaned-up layout
 */
function quiz_clean_layout($layout, $removeemptypages = false) {
    // Remove duplicate "," (or triple, or...)
    $layout = preg_replace('/,{2,}/', ',', trim($layout, ','));

    // Remove duplicate question ids
    $layout = explode(',', $layout);
    $cleanerlayout = array();
    $seen = array();
    foreach ($layout as $item) {
        if ($item == 0) {
            $cleanerlayout[] = '0';
        } else if (!in_array($item, $seen)) {
            $cleanerlayout[] = $item;
            $seen[] = $item;
        }
    }

    if ($removeemptypages) {
        // Avoid duplicate page breaks
        $layout = $cleanerlayout;
        $cleanerlayout = array();
        $stripfollowingbreaks = true; // Ensure breaks are stripped from the start.
        foreach ($layout as $item) {
            if ($stripfollowingbreaks && $item == 0) {
                continue;
            }
            $cleanerlayout[] = $item;
            $stripfollowingbreaks = $item == 0;
        }
    }

    // Add a page break at the end if there is none
    if (end($cleanerlayout) !== '0') {
        $cleanerlayout[] = '0';
    }

    return implode(',', $cleanerlayout);
}

/**
 * Get the slot for a question with a particular id.
 * @param object $quiz the quiz settings.
 * @param integer $questionid the of a question in the quiz.
 * @return integer the corresponding slot. Null if the question is not in the quiz.
 */
function quiz_get_slot_for_question($quiz, $questionid) {
    $questionids = quiz_questions_in_quiz($quiz->questions);
    foreach (explode(',', $questionids) as $key => $id) {
        if ($id == $questionid) {
            return $key + 1;
        }
    }
    return null;
}

/// FUNCTIONS FOR SENDING NOTIFICATION EMAILS ///////////////////////////////

/**
 * Sends confirmation email to the student taking the course
 *
 * @param stdClass $a associative array of replaceable fields for the templates
 *
 * @return bool|string result of email_to_user()
 */
function quiz_send_confirmation($a) {

    global $USER;

    // recipient is self
    $a->useridnumber = $USER->idnumber;
    $a->username = fullname($USER);
    $a->userusername = $USER->username;

    // fetch the subject and body from strings
    $subject = get_string('emailconfirmsubject', 'quiz', $a);
    $body = get_string('emailconfirmbody', 'quiz', $a);

    // send email and analyse result
    return email_to_user($USER, get_admin(), $subject, $body);
}

/**
 * Sends notification email to the interested parties that assign the role capability
 *
 * @param object $recipient user object of the intended recipient
 * @param stdClass $a associative array of replaceable fields for the templates
 *
 * @return bool|string result of email_to_user()
 */
function quiz_send_notification($recipient, $a) {

    global $USER;

    // recipient info for template
    $a->username = fullname($recipient);
    $a->userusername = $recipient->username;
    $a->userusername = $recipient->username;

    // fetch the subject and body from strings
    $subject = get_string('emailnotifysubject', 'quiz', $a);
    $body = get_string('emailnotifybody', 'quiz', $a);

    // send email and analyse result
    return email_to_user($recipient, $USER, $subject, $body);
}

/**
 * Takes a bunch of information to format into an email and send
 * to the specified recipient.
 *
 * @param object $course the course
 * @param object $quiz the quiz
 * @param object $attempt this attempt just finished
 * @param object $context the quiz context
 * @param object $cm the coursemodule for this quiz
 *
 * @return int number of emails sent
 */
function quiz_send_notification_emails($course, $quiz, $attempt, $context, $cm) {
    global $CFG, $USER;
    // we will count goods and bads for error logging
    $emailresult = array('good' => 0, 'block' => 0, 'fail' => 0);

    // do nothing if required objects not present
    if (empty($course) or empty($quiz) or empty($attempt) or empty($context)) {
        debugging('quiz_send_notification_emails: Email(s) not sent due to program error.',
                DEBUG_DEVELOPER);
        return $emailresult['fail'];
    }

    // check for confirmation required
    $sendconfirm = false;
    $notifyexcludeusers = '';
    if (has_capability('mod/quiz:emailconfirmsubmission', $context, NULL, false)) {
        // exclude from notify emails later
        $notifyexcludeusers = $USER->id;
        // send the email
        $sendconfirm = true;
    }

    // check for notifications required
    $notifyfields = 'u.id, u.username, u.firstname, u.lastname, u.email, u.emailstop, u.lang, u.timezone, u.mailformat, u.maildisplay';
    $groups = groups_get_all_groups($course->id, $USER->id);
    if (is_array($groups) && count($groups) > 0) {
        $groups = array_keys($groups);
    } else if (groups_get_activity_groupmode($cm, $course) != NOGROUPS) {
        // If the user is not in a group, and the quiz is set to group mode,
        // then set $gropus to a non-existant id so that only users with
        // 'moodle/site:accessallgroups' get notified.
        $groups = -1;
    } else {
        $groups = '';
    }
    $userstonotify = get_users_by_capability($context, 'mod/quiz:emailnotifysubmission',
            $notifyfields, '', '', '', $groups, $notifyexcludeusers, false, false, true);

    // if something to send, then build $a
    if (! empty($userstonotify) or $sendconfirm) {
        $a = new stdClass;
        // course info
        $a->coursename = $course->fullname;
        $a->courseshortname = $course->shortname;
        // quiz info
        $a->quizname = $quiz->name;
        $a->quizreporturl = $CFG->wwwroot . '/mod/quiz/report.php?q=' . $quiz->id;
        $a->quizreportlink = '<a href="' . $a->quizreporturl . '">' . format_string($quiz->name) . ' report</a>';
        $a->quizreviewurl = $CFG->wwwroot . '/mod/quiz/review.php?attempt=' . $attempt->id;
        $a->quizreviewlink = '<a href="' . $a->quizreviewurl . '">' . format_string($quiz->name) . ' review</a>';
        $a->quizurl = $CFG->wwwroot . '/mod/quiz/view.php?q=' . $quiz->id;
        $a->quizlink = '<a href="' . $a->quizurl . '">' . format_string($quiz->name) . '</a>';
        // attempt info
        $a->submissiontime = userdate($attempt->timefinish);
        $a->timetaken = format_time($attempt->timefinish - $attempt->timestart);
        // student who sat the quiz info
        $a->studentidnumber = $USER->idnumber;
        $a->studentname = fullname($USER);
        $a->studentusername = $USER->username;
    }

    // send confirmation if required
    if ($sendconfirm) {
        // send the email and update stats
        switch (quiz_send_confirmation($a)) {
            case true:
                $emailresult['good']++;
                break;
            case false:
                $emailresult['fail']++;
                break;
            case 'emailstop':
                $emailresult['block']++;
                break;
        }
    }

    // send notifications if required
    if (!empty($userstonotify)) {
        // loop through recipients and send an email to each and update stats
        foreach ($userstonotify as $recipient) {
            switch (quiz_send_notification($recipient, $a)) {
                case true:
                    $emailresult['good']++;
                    break;
                case false:
                    $emailresult['fail']++;
                    break;
                case 'emailstop':
                    $emailresult['block']++;
                    break;
            }
        }
    }

    // log errors sending emails if any
    if (! empty($emailresult['fail'])) {
        debugging('quiz_send_notification_emails:: '.$emailresult['fail'].' email(s) failed to be sent.', DEBUG_DEVELOPER);
    }
    if (! empty($emailresult['block'])) {
        debugging('quiz_send_notification_emails:: '.$emailresult['block'].' email(s) were blocked by the user.', DEBUG_DEVELOPER);
    }

    // return the number of successfully sent emails
    return $emailresult['good'];
}

/**
 * Checks if browser is safe browser
 *
 * @return true, if browser is safe browser else false
 */
function quiz_check_safe_browser() {
    return strpos($_SERVER['HTTP_USER_AGENT'], "SEB") !== false;
}

/**
 * An extension of question_display_options that includes the extra options used
 * by the quiz.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quiz_display_options extends question_display_options {
    /**#@+
     * @var integer bits used to indicate various times in relation to a
     * quiz attempt.
     */
    const DURING =            0x10000;
    const IMMEDIATELY_AFTER = 0x01000;
    const LATER_WHILE_OPEN =  0x00100;
    const AFTER_CLOSE =       0x00010;
    /**#@-*/

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $attempt = true;

    /**
     * @var boolean if this is false, then the student is not allowed to review
     * anything about the attempt.
     */
    public $overallfeedback = self::VISIBLE;

    /**
     * Set up the various options from the quiz settings, and a time constant.
     * @param stdClass $quiz the quiz settings.
     * @param integer $one of the {@link DURING}, {@link IMMEDIATELY_AFTER},
     * {@link LATER_WHILE_OPEN} or {@link AFTER_CLOSE} constants.
     * @return mod_quiz_display_options set up appropriately.
     */
    public static function make_from_quiz($quiz, $when) {
        $options = new self();

        $options->attempt = self::extract($quiz->reviewattempt, $when, true, false);
        $options->correctness = self::extract($quiz->reviewcorrectness, $when);
        $options->marks = self::extract($quiz->reviewmarks, $when, self::MARK_AND_MAX);
        $options->feedback = self::extract($quiz->reviewspecificfeedback, $when);
        $options->generalfeedback = self::extract($quiz->reviewgeneralfeedback, $when);
        $options->rightanswer = self::extract($quiz->reviewrightanswer, $when);
        $options->overallfeedback = self::extract($quiz->reviewoverallfeedback, $when);

        $options->numpartscorrect = $options->feedback;

        if ($quiz->questiondecimalpoints != -1) {
            $options->markdp = $quiz->questiondecimalpoints;
        } else {
            $options->markdp = $quiz->decimalpoints;
        }

        return $options;
    }

    protected static function extract($bitmask, $bit, $whenset = self::VISIBLE, $whennotset = self::HIDDEN) {
        if ($bitmask & $bit) {
            return $whenset;
        } else {
            return $whennotset;
        }
    }
}


/**
 * A {@link qubaid_condition} for finding all the question usages belonging to
 * a particular quiz.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quibaid_for_quiz extends qubaid_join {
    public function __construct($quizid, $includepreviews = true, $onlyfinished = false) {
        global $CFG;

        $from = $CFG->prefix . 'quiz_attempts quiza';

        $where = 'quiza.quiz = ' . $quizid;

        if (!$includepreviews) {
            $where .= ' AND preview = 0';
        }

        if ($onlyfinished) {
            $where .= ' AND timefinish <> 0';
        }

        parent::__construct($from, 'quiza.uniqueid', $where);
    }
}
