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
 * This file defines the quiz overview report class.
 *
 * @package quiz_overview
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot.'/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot.'/mod/quiz/report/overview/overviewsettings_form.php');
require_once($CFG->dirroot.'/mod/quiz/report/overview/overview_table.php');


/**
 * Quiz report subclass for the overview (grades) report.
 *
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_overview_report extends quiz_attempt_report {

    function display($quiz, $cm, $course) {
        global $CFG, $COURSE;

        $this->context = get_context_instance(CONTEXT_MODULE, $cm->id);

        $download = optional_param('download', '', PARAM_ALPHA);

        list($currentgroup, $students, $groupstudents, $allowed) =
                $this->load_relevant_students($cm);

        $pageoptions = array();
        $pageoptions['id'] = $cm->id;
        $pageoptions['mode'] = 'overview';

        $reporturl = new moodle_url($CFG->wwwroot.'/mod/quiz/report.php', $pageoptions);
        $qmsubselect = quiz_report_qm_filter_select($quiz);

        $mform = new mod_quiz_report_overview_settings($reporturl,
                array('qmsubselect' => $qmsubselect, 'quiz' => $quiz, 'currentgroup' => $currentgroup, 'context'=>$this->context));

        if ($fromform = $mform->get_data()) {
            $regradeall = false;
            $regradealldry = false;
            $regradealldrydo = false;
            $attemptsmode = $fromform->attemptsmode;
            if ($qmsubselect) {
                $qmfilter = $fromform->qmfilter;
            } else {
                $qmfilter = 0;
            }
            $regradefilter = !empty($fromform->regradefilter);
            set_user_preference('quiz_report_overview_detailedmarks', $fromform->detailedmarks);
            set_user_preference('quiz_report_pagesize', $fromform->pagesize);
            $detailedmarks = $fromform->detailedmarks;
            $pagesize = $fromform->pagesize;

        } else {
            $regradeall  = optional_param('regradeall', 0, PARAM_BOOL);
            $regradealldry  = optional_param('regradealldry', 0, PARAM_BOOL);
            $regradealldrydo  = optional_param('regradealldrydo', 0, PARAM_BOOL);
            $attemptsmode = optional_param('attemptsmode', null, PARAM_INT);
            if ($qmsubselect) {
                $qmfilter = optional_param('qmfilter', 0, PARAM_INT);
            } else {
                $qmfilter = 0;
            }
            $regradefilter = optional_param('regradefilter', 0, PARAM_INT);
            $detailedmarks = get_user_preferences('quiz_report_overview_detailedmarks', 1);
            $pagesize = get_user_preferences('quiz_report_pagesize', 0);
        }

        $this->validate_common_options($attemptsmode, $pagesize, $course, $currentgroup);
        if (!$this->should_show_grades($quiz)) {
            $detailedmarks = 0;
        }

        // We only want to show the checkbox to delete attempts
        // if the user has permissions and if the report mode is showing attempts.
        $candelete = has_capability('mod/quiz:deleteattempts', $this->context)
                && ($attemptsmode!= QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO);

        $displayoptions = array();
        $displayoptions['attemptsmode'] = $attemptsmode;
        $displayoptions['qmfilter'] = $qmfilter;
        $displayoptions['regradefilter'] = $regradefilter;

        // Load the required questions.
        if ($detailedmarks) {
            $questions = quiz_report_get_significant_questions($quiz);
        } else {
            $questions = array();
        }

        $table = new quiz_report_overview_table($quiz , $qmsubselect, $groupstudents,
                $students, $detailedmarks, $questions, $candelete, $reporturl,
                $displayoptions, $this->context);
        $table->is_downloading($download, get_string('reportoverview','quiz'),
                    "$COURSE->shortname ".format_string($quiz->name,true));

        // Process actions.
        if (empty($currentgroup) || $groupstudents) {
            if (optional_param('delete', 0, PARAM_BOOL) && confirm_sesskey()) {
                if ($attemptids = optional_param('attemptid', array(), PARAM_INT)) {
                    require_capability('mod/quiz:deleteattempts', $this->context);
                    $this->delete_selected_attempts($quiz, $cm, $attemptids, $groupstudents);
                    redirect($reporturl->out(false, $displayoptions));
                }

            } else if (optional_param('regrade', 0, PARAM_BOOL) && confirm_sesskey()) {
                if ($attemptids = optional_param('attemptid', array(), PARAM_INT)) {
                    require_capability('mod/quiz:regrade', $this->context);
                    $this->regrade_attempts($quiz, false, $groupstudents, $attemptids);
                    redirect($reporturl->out(false, $displayoptions));
                }
            }
        }

        if ($regradeall && confirm_sesskey()) {
            require_capability('mod/quiz:regrade', $this->context);
            $this->regrade_attempts($quiz, false, $groupstudents);
            redirect($reporturl->out(false, $displayoptions), '', 5);

        } else if ($regradealldry && confirm_sesskey()) {
            require_capability('mod/quiz:regrade', $this->context);
            $this->regrade_attempts($quiz, true, $groupstudents);
            redirect($reporturl->out(false, $displayoptions), '', 5);

        } else if ($regradealldrydo && confirm_sesskey()) {
            require_capability('mod/quiz:regrade', $this->context);
            $this->regrade_attempts_needing_it($quiz, $groupstudents);
            redirect($reporturl->out(false, $displayoptions), '', 5);
        }

        // Start output.
        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data
            $this->print_header_and_tabs($cm, $course, $quiz, 'overview');
        }

        if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used
            if (!$table->is_downloading()) {
                groups_print_activity_menu($cm, $reporturl->out(false, $displayoptions));
            }
        }

        // Print information on the number of existing attempts
        if (!$table->is_downloading()) { //do not print notices when downloading
            if ($strattemptnum = quiz_num_attempt_summary($quiz, $cm, true, $currentgroup)) {
                echo '<div class="quizattemptcounts">' . $strattemptnum . '</div>';
            }
        }

        $nostudents = false;
        if (!$students) {
            notify(get_string('nostudentsyet'));
            $nostudents = true;
        } else if ($currentgroup && !$groupstudents) {
            notify(get_string('nostudentsingroup'));
            $nostudents = true;
        }

        if (!$table->is_downloading()) {
            // Print display options
            $mform->set_data($displayoptions + compact('detailedmarks', 'pagesize'));
            $mform->display();
        }

        if (!$nostudents || ($attemptsmode == QUIZ_REPORT_ATTEMPTS_ALL)) {
            // Construct the SQL
            $fields = sql_concat('u.id', "'#'", 'COALESCE(quiza.attempt, 0)') . ' AS uniqueid, ';
            if ($qmsubselect) {
                $fields .=
                    "(CASE " .
                    "   WHEN $qmsubselect THEN 1" .
                    "   ELSE 0 " .
                    "END) AS gradedattempt, ";
            }

            list($fields, $from, $where, $params) =
                    $this->base_sql($quiz, $qmsubselect, $qmfilter, $attemptsmode, $allowed);

            $table->set_count_sql("SELECT COUNT(1) FROM $from WHERE $where", $params);

            // Test to see if there are any regraded attempts to be listed.
            $fields .= ", COALESCE((SELECT MAX(qqr.regraded) FROM {$CFG->prefix}quiz_question_regrade qqr WHERE qqr.questionusageid = quiza.uniqueid),-1) AS regraded";
            if ($regradefilter) {
                $where .= " AND COALESCE((SELECT MAX(qqr.regraded) FROM {$CFG->prefix}quiz_question_regrade qqr WHERE qqr.questionusageid = quiza.uniqueid),-1) <> -1";
            }
            $table->set_sql($fields, $from, $where);

            if (!$table->is_downloading()) { //do not print notices when downloading
                //regrade buttons
                if (has_capability('mod/quiz:regrade', $this->context)) {
                    $regradesneeded = $this->count_question_attempts_needing_regrade(
                            $quiz, $groupstudents);
                    if ($currentgroup) {
                        $a= new stdClass;
                        $a->groupname = groups_get_group_name($currentgroup);
                        $a->coursestudents = get_string('participants');
                        $a->countregradeneeded = $regradesneeded;
                        $regradealldrydolabel = get_string('regradealldrydogroup', 'quiz_overview', $a);
                        $regradealldrylabel = get_string('regradealldrygroup', 'quiz_overview', $a);
                        $regradealllabel = get_string('regradeallgroup', 'quiz_overview', $a);
                    } else {
                        $regradealldrydolabel = get_string('regradealldrydo', 'quiz_overview', $regradesneeded);
                        $regradealldrylabel = get_string('regradealldry', 'quiz_overview');
                        $regradealllabel = get_string('regradeall', 'quiz_overview');
                    }
                    echo '<div class="mdl-align">';
                    echo '<form action="'.$reporturl->out(true).'">';
                    echo '<div>';
                    echo $reporturl->hidden_params_out(array(), 0, $displayoptions);
                    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />' . "\n";
                    echo '<input type="submit" name="regradeall" value="'.$regradealllabel.'"/>';
                    echo '<input type="submit" name="regradealldry" value="'.$regradealldrylabel.'"/>';
                    if ($regradesneeded) {
                        echo '<input type="submit" name="regradealldrydo" value="'.$regradealldrydolabel.'"/>';
                    }
                    echo '</div>';
                    echo '</form>';
                    echo '</div>';
                }
                // Print information on the grading method
                if ($strattempthighlight = quiz_report_highlighting_grading_method($quiz, $qmsubselect, $qmfilter)) {
                    echo '<div class="quizattemptcounts">' . $strattempthighlight . '</div>';
                }
            }

            // Define table columns
            $columns = array();
            $headers = array();

            if (!$table->is_downloading() && $candelete) {
                $columns[] = 'checkbox';
                $headers[] = NULL;
            }

            $this->add_user_columns($table, $columns, $headers);

            $this->add_time_columns($columns, $headers);

            if ($detailedmarks) {
                foreach ($questions as $qnumber => $question) {
                    // Ignore questions of zero length
                    $columns[] = 'qsgrade' . $qnumber;
                    $header = get_string('qbrief', 'quiz', $question->number);
                    if (!$table->is_downloading()) {
                        $header .= '<br />';
                    } else {
                        $header .= ' ';
                    }
                    $header .= '/' . quiz_rescale_grade($question->maxmark, $quiz, 'question');
                    $headers[] = $header;
                 }
            }

            if (!$table->is_downloading() && has_capability('mod/quiz:regrade', $this->context) &&
                    $this->has_regraded_questions($from, $where)) {
                $columns[] = 'regraded';
                $headers[] = get_string('regrade', 'quiz_overview');
            }

            $this->add_grade_columns($quiz, $columns, $headers);

            $this->set_up_table_columns($table, $columns, $headers, $reporturl, $displayoptions, false);

            $table->out($pagesize, true);
        }

        if (!$table->is_downloading() && $this->should_show_grades($quiz)) {
            if ($currentgroup && $groupstudents) {
                list($usql, $params) = get_in_or_equal($groupstudents);
                if (record_exists_select('quiz_grades', "userid $usql AND quiz = $quiz->id")) {
                     $imageurl = "{$CFG->wwwroot}/mod/quiz/report/overview/overviewgraph.php?id={$quiz->id}&amp;groupid=$currentgroup";
                     $graphname = get_string('overviewreportgraphgroup', 'quiz_overview', groups_get_group_name($currentgroup));
                     print_heading($graphname);
                     echo '<div class="mdl-align"><img src="'.$imageurl.'" alt="'.$graphname.'" /></div>';
                }
            }

            if (record_exists('quiz_grades', 'quiz', $quiz->id)) {
                 $graphname = get_string('overviewreportgraph', 'quiz_overview');
                 $imageurl = $CFG->wwwroot.'/mod/quiz/report/overview/overviewgraph.php?id='.$quiz->id;
                 print_heading($graphname);
                 echo '<div class="mdl-align"><img src="'.$imageurl.'" alt="'.$graphname.'" /></div>';
            }
        }
        return true;
    }

    /**
     * Regrade a particular quiz attempt. Either for real ($dryrun = false), or
     * as a pretend regrade to see which fractions would change. The outcome is
     * stored in the quiz_question_regrade table.
     *
     * Note, $attempt is not upgraded in the database. The caller needs to do that.
     * However, $attempt->sumgrades is updated, if this is not a dry run.
     *
     * @param object $attempt the quiz attempt to regrade.
     * @param boolean $dryrun if true, do a pretend regrade, otherwise do it for real.
     * @param array $qnumbers if null, regrade all questoins, otherwise, just regrade
     *      the quetsions with those qnumbers.
     */
    protected function regrade_attempt($attempt, $dryrun = false, $qnumbers = null) {
        begin_sql();

        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        if (is_null($qnumbers)) {
            $qnumbers = $quba->get_question_numbers();
        }

        foreach ($qnumbers as $qnumber) {
            $qqr = new stdClass;
            $qqr->oldfraction = $quba->get_question_fraction($qnumber);

            $quba->regrade_question($qnumber);

            $qqr->newfraction = $quba->get_question_fraction($qnumber);

            if (abs($qqr->oldfraction - $qqr->newfraction) > 1e-7) {
                $qqr->questionusageid = $quba->get_id();
                $qqr->numberinusage = $qnumber;
                $qqr->regraded = empty($dryrun);
                $qqr->timemodified = time();
                insert_record('quiz_question_regrade', $qqr, false);
            }
        }

        if (!$dryrun) {
            question_engine::save_questions_usage_by_activity($quba);
        }

        commit_sql();
    }

    /**
     * Regrade attempts for this quiz, exactly which attempts are regraded is
     * controlled by the parameters.
     * @param object $quiz the quiz settings.
     * @param boolean $dryrun if true, do a pretend regrade, otherwise do it for real.
     * @param array $groupstudents blank for all attempts, otherwise regrade attempts
     * for these users.
     * @param array $attemptids blank for all attempts, otherwise only regrade
     * attempts whose id is in this list.
     */
    protected function regrade_attempts($quiz, $dryrun = false,
            $groupstudents = array(), $attemptids = array()) {
        $where = "quiz = $quiz->id AND preview = 0";

        if ($groupstudents) {
            list($usql, $params) = get_in_or_equal($groupstudents);
            $where .= " AND userid $usql";
        }

        if ($attemptids) {
            list($asql, $aparams) = get_in_or_equal($attemptids);
            $where .= " AND id $asql";
        }

        $attempts = get_records_select('quiz_attempts', $where);
        if (!$attempts) {
            return;
        }

        $this->clear_regrade_table($quiz, $groupstudents);

        foreach ($attempts as $attempt) {
            set_time_limit(30);
            $this->regrade_attempt($attempt, $dryrun);
        }

        if (!$dryrun) {
            $this->update_overall_grades($quiz);
        }
    }

    /**
     * Regrade those questions in those attempts that are marked as needing regrading
     * in the quiz_question_regrade table.
     * @param object $quiz the quiz settings.
     * @param array $groupstudents blank for all attempts, otherwise regrade attempts
     * for these users.
     */
    protected function regrade_attempts_needing_it($quiz, $groupstudents) {
        global $CFG;

        $where = "quiza.quiz = $quiz->id AND quiza.preview = 0 AND qqr.regraded = 0";

        // Fetch all attempts that need regrading
        if ($groupstudents) {
            list($usql, $params) = get_in_or_equal($groupstudents);
            $where .= " AND quiza.userid $usql";
        }

        $toregrade = get_records_sql("
                SELECT quiza.uniqueid, qqr.numberinusage
                FROM {$CFG->prefix}quiz_attempts quiza
                JOIN {$CFG->prefix}quiz_question_regrade qqr ON qqr.questionusageid = quiza.uniqueid
                WHERE $where");

        if (!$toregrade) {
            return;
        }

        $attemptquestions = array();
        foreach ($toregrade as $row) {
            $attemptquestions[$row->uniqueid][] = $row->numberinusage;
        }
        $attempts = get_records_list('quiz_attempts', 'uniqueid', implode(',', array_keys($attemptquestions)));

        $this->clear_regrade_table($quiz, $groupstudents);

        foreach ($attempts as $attempt) {
            set_time_limit(30);
            $this->regrade_attempt($attempt, false, $attemptquestions[$attempt->uniqueid]);
        }

        $this->update_overall_grades($quiz);
    }

    /**
     * Count the number of attempts in need of a regrade.
     * @param object $quiz the quiz settings.
     * @param array $groupstudents user ids. If this is given, only data relating
     * to these users is cleared.
     */
    protected function count_question_attempts_needing_regrade($quiz, $groupstudents) {
        global $CFG;
        if ($groupstudents) {
            list($usql, $params) = get_in_or_equal($groupstudents);
            $usertest = "quiza.userid $usql AND ";
        } else {
            $usertest = '';
        }
        $sql = "SELECT COUNT(DISTINCT quiza.id)
                FROM {$CFG->prefix}quiz_attempts quiza
                JOIN {$CFG->prefix}quiz_question_regrade qqr ON quiza.uniqueid = qqr.questionusageid
                WHERE
                    $usertest
                    quiza.quiz = $quiz->id AND
                    quiza.preview = 0 AND
                    qqr.regraded = 0";
        return count_records_sql($sql);
    }

    /**
     * Are there any pending regrades in the table we are going to show?
     * @param $from tables used by the main query.
     * @param $where where clause used by the main query.
     * @return boolean
     */
    protected function has_regraded_questions($from, $where) {
        $qubaids = new qubaid_join($from, 'uniqueid', $where);
        return record_exists_select('quiz_question_regrade',
                'questionusageid ' . $qubaids->usage_id_in(), '', '*');
    }

    /**
     * Remove all information about pending/complete regrades from the database.
     * @param object $quiz the quiz settings.
     * @param array $groupstudents user ids. If this is given, only data relating
     * to these users is cleared.
     */
    protected function clear_regrade_table($quiz, $groupstudents) {
        global $CFG;
        // Fetch all attempts that need regrading
        if ($groupstudents) {
            list($usql, $params) = get_in_or_equal($groupstudents);
            $where = "userid $usql AND ";
        } else {
            $where = '';
        }
        if (!delete_records_select('quiz_question_regrade',
                "questionusageid IN (
                    SELECT uniqueid
                    FROM {$CFG->prefix}quiz_attempts
                    WHERE $where quiz = $quiz->id
                )")) {
            print_error('err_failedtodeleteregrades', 'quiz_overview');
        }
    }

    /**
     * Update the final grades for all attempts. This method is used following
     * a regrade.
     * @param object $quiz the quiz settings.
     * @param array $userids only update scores for these userids.
     * @param array $attemptids attemptids only update scores for these attempt ids.
     */
    protected function update_overall_grades($quiz) {
        quiz_update_all_attempt_sumgrades($quiz);
        quiz_update_all_final_grades($quiz);
        quiz_update_grades($quiz);
    }
}
