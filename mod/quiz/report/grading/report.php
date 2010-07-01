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
        global $CFG;

        $this->quiz = $quiz;
        $this->cm = $cm;
        $this->course = $course;

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

        // Get the list of questions in this quiz.
        $this->questions = quiz_report_get_significant_questions($quiz);
        if ($qnumber && !array_key_exists($qnumber, $this->questions)) {
            throw new moodle_exception('unknownquestion', 'quiz_grading');
        }

        // Process any submitted data.
        if ($data = data_submitted() && confirm_sesskey() && $this->validate_submitted_marks()) {
            $this->process_submitted_data();

            redirect($this->grade_question_url($qnumber, $questionid, $grade, $page + 1));
        }

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
        $where = "quiza.quiz = {$this->cm->instance} AND quiza.preview = 0 AND quiza.timefinish <> 0";

        if ($this->currentgroup) {
            $where .= ' AND quiza.userid IN (' . implode(',', $this->userids) . ')';
        }

        $sql = new stdClass;
        $sql->from = $from;
        $sql->where = $where;
        $sql->usageidcolumn = 'quiza.uniqueid';

        return $sql;
    }

    protected function get_qubaids_condition() {
        global $CFG;

        $where = "quiza.quiz = {$this->cm->instance} AND
                quiza.preview = 0 AND
                quiza.timefinish <> 0";
        if ($this->currentgroup) {
            $where .= ' AND
                quiza.userid IN (' . implode(',', $this->userids) . ')';
        }

        return new qubaid_join("{$CFG->prefix}quiz_attempts quiza",
                'quiza.uniqueid', $where);
    }

    protected function load_attempts_by_usage_ids($qubaids) {
        global $CFG;

        list($asql, $params) = get_in_or_equal($qubaids);

        $attemptsbyid = get_records_sql("
            SELECT quiza.*, u.firstname, u.lastname
            FROM {$CFG->prefix}quiz_attempts quiza
            JOIN {$CFG->prefix}user u ON u.id = quiza.userid
            WHERE quiza.uniqueid $asql AND quiza.timefinish <> 0 AND quiza.quiz = {$this->quiz->id}
        ");

        $attempts = array();
        foreach ($attemptsbyid as $attempt) {
            $attempts[$attempt->uniqueid] = $attempt;
        }
        return $attempts;
    }

    /**
     * Get the URL of the front page of the report that lists all the questions.
     * @param $includeauto if not given, use the current setting, otherwise,
     *      force a paricular value of includeauto in the URL.
     * @return string the URL.
     */
    protected function base_url() {
        global $CFG;
        return $CFG->wwwroot . '/mod/quiz/report.php?id=' . $this->cm->id .
                '&mode=grading';
    }

    /**
     * Get the URL of the front page of the report that lists all the questions.
     * @param $includeauto if not given, use the current setting, otherwise,
     *      force a paricular value of includeauto in the URL.
     * @return string the URL.
     */
    protected function list_questions_url($includeauto = null) {
        $url = $this->base_url();

        $options = $this->viewoptions;
        if (!is_null($includeauto)) {
            $options['includeauto'] = $includeauto;
        }
        foreach ($options as $name => $value) {
            $url .= '&' . $name . '=' . $value;
        }

        return $url;
    }

    /**
     * @param integer $qnumber
     * @param integer $questionid
     * @param string $grade
     * @param mixed $page = true, link to current page. false = omit page.
     *      number = link to specific page.
     */
    protected function grade_question_url($qnumber, $questionid, $grade, $page = true) {

        $url = $this->base_url() . '&qnumber=' . $qnumber . '&qid=' . $questionid .
                '&grade=' . $grade;

        $options = $this->viewoptions;
        if (!$page) {
            unset($options['page']);
        } else if (is_integer($page)) {
            $options['page'] = $page;
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

        $statecounts = $this->get_question_state_summary(array_keys($this->questions));

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
        $table->head[] = get_string('total', 'quiz_grading');

        $table->data = $data;
        print_table($table);
    }

    protected function display_grading_interface($qnumber, $questionid, $grade,
            $pagesize, $page, $shownames, $order) {

        // Make sure there is something to do.
        $statecounts = $this->get_question_state_summary(array($qnumber));

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

        if ($pagesize * $page >= $counts->$grade) {
            $page = 0;
        }

        list($qubaids, $count) = $this->get_usage_ids_where_question_in_state(
                $grade, $qnumber, $questionid, $order, $page, $pagesize);
        $attempts = $this->load_attempts_by_usage_ids($qubaids);

        // Prepare the form.
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
        echo question_engine::initialise_js();

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
        $qubaidlist = implode(',', $qubaids);
        echo <<<END
<form method="post" action="{$this->grade_question_url($qnumber, $questionid, $grade, $page)}" class="mform" id="manualgradingform">
<div>
<input type="hidden" name="qubaids" value="$qubaidlist" />
<input type="hidden" name="qnumbers" value="$qnumber" />
<input type="hidden" name="sesskey" value="$sesskey" />
END;

        foreach ($qubaids as $qubaid) {
            $attempt = $attempts[$qubaid];
            $quba = question_engine::load_questions_usage_by_activity($qubaid);
            $displayoptions = quiz_get_reviewoptions($this->quiz, $attempt, $this->context);
            $displayoptions->hide_all_feedback();
            $displayoptions->history = question_display_options::HIDDEN;
            $displayoptions->manualcomment = question_display_options::EDITABLE;

            if ($shownames) {
                $a = new stdClass;
                $a->fullname = fullname($attempt);
                $a->attempt = $attempt->attempt;
                print_heading(get_string('gradingattempt', 'quiz_grading', $a), '', 4);
            }
            echo $quba->render_question($qnumber, $displayoptions, $this->questions[$qnumber]->number);
        }

        echo '<div class="mdl-align"><input type="submit" value="' .
                get_string('saveandnext', 'quiz_grading') . '" /></div>'.
            '</div></form>';
        use_html_editor();
    }

    protected function validate_submitted_marks() {

        $qubaids = optional_param('qubaids', null, PARAM_SEQUENCE);
        if (!$qubaids) {
            return false;
        }
        $qubaids = clean_param(explode(',', $qubaids), PARAM_INT);

        $qnumbers = optional_param('qnumbers', '', PARAM_SEQUENCE);
        if (!$qnumbers) {
            $qnumbers = array();
        } else {
            $qnumbers = explode(',', $qnumbers);
        }

        foreach ($qubaids as $qubaid) {
            foreach ($qnumbers as $qnumber) {
                $prefix = 'q' . $qubaid . ':' . $qnumber . '_';
                $mark = optional_param($prefix . '-mark', null, PARAM_NUMBER);
                $maxmark = optional_param($prefix . '-maxmark', null, PARAM_NUMBER);
                $minfraction = optional_param($prefix . ':minfraction', null, PARAM_NUMBER);
                if (!is_null($mark) && ($mark < $minfraction * $maxmark || $mark > $maxmark)) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function process_submitted_data() {
        $qubaids = optional_param('qubaids', null, PARAM_SEQUENCE);
        if (!$qubaids) {
            return;
        }

        $qubaids = clean_param(explode(',', $qubaids), PARAM_INT);
        $attempts = $this->load_attempts_by_usage_ids($qubaids);

        foreach ($qubaids as $qubaid) {
            $attempt = $attempts[$qubaid];
            $quba = question_engine::load_questions_usage_by_activity($qubaid);
            $attemptobj = new quiz_attempt($attempt, $this->quiz, $this->cm, $this->course);
            $attemptobj->process_all_actions(time());
        }
    }

    /**
     * Load information about the number of attempts at various questions in each
     * summarystate.
     *
     * The results are returned as an two dimensional array $qubaid => $qnumber => $dataobject
     *
     * @param array $qnumbers A list of qnumbers for the questions you want to konw about.
     * @return array The array keys are qnumber,qestionid. The values are objects with
     * fields $qnumber, $questionid, $inprogress, $name, $needsgrading, $autograded,
     * $manuallygraded and $all.
     */
    protected function get_question_state_summary($qnumbers) {
        $dm = new question_engine_data_mapper();
        return $dm->load_questions_usages_question_state_summary(
                $this->get_qubaids_condition(), $qnumbers);
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
     * @param integer $qnumber The qnumber for the questions you want to konw about.
     * @param integer $questionid (optional) Only return attempts that were of this specific question.
     * @param string $summarystate 'all', 'needsgrading', 'autograded' or 'manuallygraded'.
     * @param string $orderby 'random', 'date' or 'student'.
     * @param integer $page implements paging of the results.
     *      Ignored if $orderby = random or $pagesize is null.
     * @param integer $pagesize implements paging of the results. null = all.
     */
    function get_usage_ids_where_question_in_state($summarystate, $qnumber,
            $questionid = null, $orderby = 'random', $page = 0, $pagesize = null) {
        global $CFG;
        $dm = new question_engine_data_mapper();

        if ($pagesize && $orderby != 'random') {
            $limitfrom = $page * $pagesize;
        } else {
            $limitfrom = 0;
        }

        $qubaids = $this->get_qubaids_condition();

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
}
