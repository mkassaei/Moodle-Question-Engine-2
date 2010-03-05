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
 * This file defines the quiz manual grading report class.
 *
 * @package quiz_grading
 * @copyright 2006 Gustav Delius 
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/mod/quiz/report/grading/gradingsettings_form.php');


/**
 * Quiz report to help teachers manually grade questions that need it.
 *
 * This report basically provides two screens:
 * - List question that might need manual grading (or optionally all questions).
 * - Provide an efficient UI to grade all attempts at a particular question.
 *
 * @copyright 2006 Gustav Delius 
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_grading_report extends quiz_default_report {
    const DEFAULT_PAGE_SIZE = 5;
    const DEFAULT_ORDER = 'random';

    protected $viewoptions = array();
    protected $questions;
    protected $currentgroup;
    protected $users;
    protected $cm;
    protected $quiz;
    protected $context;

    function display($quiz, $cm, $course) {
        global $CFG, $QTYPES;

        $this->quiz = $quiz;
        $this->cm = $cm;

        // Get the URL options.
        $qnumber = optional_param('qnumber', null, PARAM_INT);
        $questionid = optional_param('qid', null, PARAM_INT);
        $grade = optional_param('grade', null, PARAM_ALPHA);

        $includeauto = optional_param('includeauto', false, PARAM_BOOL);
        if (!in_array($grade, array('all', 'needsgrading', 'autograded', 'manuallygraded'))) {
            $grade = null;
        }
        $pagesize = optional_param('pagesize', self::DEFAULT_PAGE_SIZE, PARAM_INT);
        $page = optional_param('page', 0, PARAM_INT);
        $shownames = optional_param('shownames', false, PARAM_BOOL);
        $order = optional_param('order', self::DEFAULT_ORDER, PARAM_ALPHA);
        if (!in_array($order, array('random', 'date', 'student'))) {
            $order = self::DEFAULT_ORDER;
        }
        if ($order == 'random') {
            $page = 0;
        }

        // Assemble the options requried to reload this page.
        $optparams = array('includeauto', 'page', 'shownames');
        foreach ($optparams as $param) {
            if ($$param) {
                $this->viewoptions[$param] = $$param;
            }
        }
        if ($pagesize != self::DEFAULT_PAGE_SIZE) {
            $this->viewoptions['pagesize'] = $pagesize;
        }
        if ($order != self::DEFAULT_ORDER) {
            $this->viewoptions['order'] = $order;
        }

        // Check permissions
        $this->context = get_context_instance(CONTEXT_MODULE, $cm->id);
        require_capability('mod/quiz:grade', $this->context);

        // Process any submitted data.
        if ($data = data_submitted() && confirm_sesskey()) {
            $this->process_submitted_data($data, $questions, $quiz);
        }

        // Get the list of questions in this quiz.
        $this->questions = quiz_report_get_significant_questions($quiz);

        // Get the group, and the list of significant users.
        $this->currentgroup = groups_get_activity_group($this->cm, true);
        $this->users = get_users_by_capability($this->context,
                array('mod/quiz:reviewmyattempts', 'mod/quiz:attempt'), '', '', '', '',
                $this->currentgroup, '', false);

        // Start output.
        $this->print_header_and_tabs($cm, $course, $quiz, 'grading');

        // What sort of page to display?
        if (!$qnumber) {
            $this->display_index($includeauto);

        } else {
            $this->display_grading_interface($qnumber, $questionid, $grade, $pagesize, $page,
                    $shownames, $order);
        }
        return true;
    }

    protected function get_attempts_query() {
        global $CFG;

        $from = "FROM {$CFG->prefix}quiz_attempts quiza";
        $where = "quiza.quiz = {$this->cm->instance}";

        if ($this->currentgroup) {
            $where .= ' AND quiza.userid IN (' . implode(',', $this->userids) . ')';
        }

        $sql = new stdClass;
        $sql->from = $from;
        $sql->where = $where;
        $sql->usageidcolumn = 'quiza.uniqueid';

        return $sql;
    }

    /**
     * Get the URL of the front page of the report that lists all the questions.
     * @param $includeauto if not given, use the current setting, otherwise,
     *      force a paricular value of includeauto in the URL.
     * @return string the URL.
     */
    protected function list_questions_url($includeauto = null) {
        global $CFG;

        $url = $CFG->wwwroot . '/mod/quiz/report.php?id=' . $this->cm->id .
                '&mode=grading';

        $options = $this->viewoptions;
        if (!is_null($includeauto)) {
            $options['includeauto'] = $includeauto;
        }
        foreach ($options as $name => $value) {
            $url .= '&' . $name . '=' . $value;
        }

        return $url;
        
    }

    protected function grade_question_url($qnumber, $questionid, $grade, $includecurrentpage = true) {
        global $CFG;

        $url = $CFG->wwwroot . '/mod/quiz/report.php?id=' . $this->cm->id .
                '&mode=grading&qnumber=' . $qnumber . '&qid=' . $questionid .
                '&grade=' . $grade;

        $options = $this->viewoptions;
        if (!$includecurrentpage) {
            unset($options['page']);
        }
        foreach ($options as $name => $value) {
            $url .= '&' . $name . '=' . $value;
        }

        return $url;
    }

    protected function format_count_for_table($counts, $type, $gradestring) {
        $result = $counts->$type;
        if ($counts->$type > 0) {
            $result .= ' <a class="gradetheselink" href="' .
                    $this->grade_question_url($counts->qnumber, $counts->questionid, $type) .
                    '">' . get_string($gradestring, 'quiz_grading') . '</a>';
        }
        return $result;
    }

    protected function display_index($includeauto) {
        if ($groupmode = groups_get_activity_groupmode($this->cm)) {   // Groups are being used
            groups_print_activity_menu($this->cm, $this->list_questions_url());
        }

        print_heading(get_string('questionsthatneedgrading', 'quiz_grading'));
        if ($includeauto) {
            $linktext = get_string('hideautomaticallygraded', 'quiz_grading');
        } else {
            $linktext = get_string('alsoshowautomaticallygraded', 'quiz_grading');
        }
        echo '<p class="toggleincludeauto"><a href="' . $this->list_questions_url(!$includeauto) .
                '">' . $linktext . '</a></p>';

        $attemptssql = $this->get_attempts_query();
        $statecounts = quiz_report_get_state_summary($attemptssql, array_keys($this->questions));

        $data = array();
        foreach ($statecounts as $counts) {
            if ($counts->all == 0) {
                continue;
            }
            if (!$includeauto && $counts->needsgrading == 0 && $counts->manuallygraded == 0) {
                continue;
            }

            $row = array();

            $row[] = $this->questions[$counts->qnumber]->number;

            $row[] = format_string($counts->name);

            $row[] = $this->format_count_for_table($counts, 'needsgrading', 'grade');

            $row[] = $this->format_count_for_table($counts, 'manuallygraded', 'updategrade');

            if ($includeauto) {
                $row[] = $this->format_count_for_table($counts, 'autograded', 'updategrade');
            }

            $row[] = $counts->inprogress;

            $row[] = $this->format_count_for_table($counts, 'all', 'gradeall');

            $data[] = $row;
        }

        if (empty($data)) {
            print_heading(get_string('noquestionsfound', 'quiz_grading'));
            return;
        }

        $table = new stdClass;
        $table->class = 'generaltable';
        $table->id = 'questionstograde';

        $table->head[] = get_string('qno', 'quiz_grading');
        $table->head[] = get_string('questionname', 'quiz_grading');
        $table->head[] = get_string('tograde', 'quiz_grading');
        $table->head[] = get_string('alreadygraded', 'quiz_grading');
        if ($includeauto) {
            $table->head[] = get_string('automaticallygraded', 'quiz_grading');
        }
        $table->head[] = get_string('inprogress', 'quiz_grading');
        $table->head[] = get_string('total', 'quiz_grading');

        $table->data = $data;
        print_table($table);
    }

    protected function display_grading_interface($qnumber, $questionid, $grade,
            $pagesize, $page, $shownames, $order) {

        // Make sure there is something to do.
        $attemptssql = $this->get_attempts_query();
        $statecounts = quiz_report_get_state_summary($attemptssql, array($qnumber));

        $counts = null;
        foreach ($statecounts as $record) {
            if ($record->questionid == $questionid) {
                $counts = $record;
                break;
            }
        }

        // If not, redirect back to the list.
        if (!$counts || $counts->$grade == 0) {
            redirect($this->list_questions_url(), get_string('alldoneredirecting', 'quiz_grading'));
        }

        if ($counts->$grade < $pagesize * $page) {
            $page = 0;
        }

        list($qubaids, $count) = quiz_report_get_usage_ids_where_question_in_state($attemptssql,
                $grade, $qnumber, $questionid, $order, $page, $pagesize);
        $attemptsbyid = get_records_list('quiz_attempts', 'uniqueid', implode(',', $qubaids));
        $attempts = array();
        foreach ($attemptsbyid as $attempt) {
            $attempts[$attempt->uniqueid] = $attempt;
        }

        // Prepare the form.
        $actionurl = 
        $hidden = array(
            'id' => $this->cm->id,
            'mode' => 'grading',
            'qnumber' => $qnumber,
            'qid' => $questionid,
            'page' => $page,
        );
        if (array_key_exists('includeauto', $this->viewoptions)) {
            $hidden['includeauto'] = $this->viewoptions['includeauto'];
        }
        $mform = new quiz_grading_settings($hidden, $counts);

        // Tell the form the current settings.
        $settings = new stdClass;
        $settings->grade = $grade;
        $settings->pagesize = $pagesize;
        $settings->shownames = $shownames;
        $settings->order = $order;
        $mform->set_data($settings);

        // Print the heading and form.
        $a = new stdClass;
        $a->number = $this->questions[$qnumber]->number;
        $a->questionname = format_string($counts->name);
        print_heading(get_string('gradingquestionx', 'quiz_grading', $a));
        echo '<p class="mdl-align"><a href="' . $this->list_questions_url() .
                '">' . get_string('backtothelistofquestions', 'quiz_grading') . '</a></p>';
        $mform->display();

        // Paging info.
        $a = new stdClass;
        $a->from = $page * $pagesize + 1;
        $a->to = min(($page + 1) * $pagesize, $count);
        $a->of = $count;
        print_heading(get_string('gradingattemptsxtoyofz', 'quiz_grading', $a), '', 3);

        if ($count > $pagesize && $order != 'random') {
            print_paging_bar($count, $page, $pagesize,
                    $this->grade_question_url($qnumber, $questionid, $grade, false) . '&');
        }

        // Display the form with one section for each attempt.
        $usehtmleditor = can_use_html_editor();
        $sesskey = sesskey();
        echo <<<END
<form method="post" action="{$this->grade_question_url($qnumber, $questionid, $grade)}" class="mform" id="manualgradingform">
<div>
<input type="hidden" name="sesskey" value="$sesskey" />
END;

        foreach ($qubaids as $qubaid) {
            $attempt = $attempts[$qubaid];
            $quba = question_engine::load_questions_usage_by_activity($qubaid);
            $displayoptions = quiz_get_reviewoptions($this->quiz, $attempt, $this->context);
            $displayoptions->hide_all_feedback();

            echo $quba->render_question($qnumber, $displayoptions, $this->questions[$qnumber]->number);

            // TODO comment fields.
//            // The print the comment and grade fields, putting back the previous comment.
//            $state->manualcomment = $copy;
//            question_print_comment_fields($question, $state, 'manualgrades[' . $attempt->uniqueid . ']',
//                    $quiz, get_string('manualgrading', 'quiz'));

        }
        echo '<div class="boxaligncenter"><input type="submit" value="'.get_string('savechanges').'" /></div>'.
            '</div></form>';
    }

    protected function process_submitted_data($data, $questions, $quiz) {
        // TODO

        // now go through all of the responses and save them.
        $allok = true;
        foreach ($data->manualgrades as $uniqueid => $response) {
            // get our attempt
            $uniqueid = clean_param($uniqueid, PARAM_INT);
            list($usql, $params) = get_in_or_equal(array_keys($this->users));

            if (!$attempt = get_record_sql("
                    SELECT * FROM {$CFG->prefix}quiz_attempts
                            WHERE uniqueid = $uniqueid AND
                            userid $usql AND
                            quiz = $quiz->id")) {
                print_error('invalidattemptid', 'quiz_grading');
            }

            // Load the state for this attempt (The questions array was created earlier)
            $states = get_question_states($questions, $quiz, $attempt);
            // The $states array is indexed by question id but because we are dealing
            // with only one question there is only one entry in this array
            $state = $states[$question->id];

            // the following will update the state and attempt
            $error = question_process_comment($question, $state, $attempt, $response['comment'], $response['grade']);
            if (is_string($error)) {
                notify($error);
                $allok = false;
            } else if ($state->changed) {
                // If the state has changed save it and update the quiz grade
                save_question_session($question, $state);
                quiz_save_best_grade($quiz, $attempt->userid);
            }
        }

        if ($allok) {
            notify(get_string('changessaved', 'quiz'), 'notifysuccess');
        } else {
            notify(get_string('changessavedwitherrors', 'quiz'), 'notifysuccess');
        }
    }
}
