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
 * This file defines the quiz grades table.
 *
 * @package quiz_overview
 * @copyright 2008 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * This is a table subclass for displaying the quiz grades report.
 *
 * @copyright 2008 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_report_overview_table extends quiz_attempt_report_table {

    protected $candelete;
    protected $regradedqs = array();

    public function __construct($quiz , $qmsubselect, $groupstudents,
            $students, $detailedmarks, $questions, $candelete,
            $reporturl, $displayoptions, $context) {
        parent::__construct('mod-quiz-report-overview-report', $quiz , $qmsubselect, $groupstudents,
                $students, $questions, $candelete, $reporturl, $displayoptions);
        $this->detailedmarks = $detailedmarks;
        $this->context = $context;
    }

    public function build_table() {
        global $CFG;
        if ($this->rawdata) {
            $this->strtimeformat = str_replace(',', '', get_string('strftimedatetime'));
            parent::build_table();

            //end of adding data from attempts data to table / download
            //now add averages at bottom of table :
            $averagesql = "SELECT AVG(qg.grade) AS grade, COUNT(qg.grade) AS numaveraged
                    FROM {$CFG->prefix}quiz_grades qg
                    WHERE quiz = {$this->quiz->id}";

            $this->add_separator();
            if ($this->is_downloading()) {
                $namekey = 'lastname';
            } else {
                $namekey = 'fullname';
            }
            if ($this->groupstudents) {
                $g_usql = ' IN (' . implode(',', $this->groupstudents) . ')';

                $groupaveragesql = $averagesql." AND qg.userid $g_usql";
                $record = get_record_sql($groupaveragesql);
                $groupaveragerow = array(
                        $namekey => get_string('groupavg', 'grades'),
                        'sumgrades' => $this->format_average($record),
                        'feedbacktext'=> strip_tags(quiz_report_feedback_for_grade($record->grade, $this->quiz->id)));
                if($this->detailedmarks && $this->qmsubselect) {
                    $avggradebyq = $this->load_average_question_grades($this->groupstudents);
                    $groupaveragerow += $this->format_average_grade_for_questions($avggradebyq);
                }
                $this->add_data_keyed($groupaveragerow);
            }

            $s_usql = ' IN (' . implode(',', $this->students) . ')';
            $record = get_record_sql($averagesql." AND qg.userid $s_usql");
            $overallaveragerow = array(
                    $namekey => get_string('overallaverage', 'grades'),
                    'sumgrades' => $this->format_average($record),
                    'feedbacktext'=> strip_tags(quiz_report_feedback_for_grade($record->grade, $this->quiz->id)));
            if ($this->detailedmarks && $this->qmsubselect) {
                $avggradebyq = $this->load_average_question_grades($this->students);
                $overallaveragerow += $this->format_average_grade_for_questions($avggradebyq);
            }
            $this->add_data_keyed($overallaveragerow);
        }
    }

    protected function format_average_grade_for_questions($gradeaverages) {
        $row = array();
        if (!$gradeaverages) {
            $gradeaverages = array();
        }
        foreach ($this->questions as $question) {
            if (isset($gradeaverages[$question->qnumber])) {
                $record = $gradeaverages[$question->qnumber];
                $record->grade = quiz_rescale_grade($record->averagefraction * $question->maxmark, $this->quiz, false);
            } else {
                $record = new stdClass;
                $record->grade = null;
                $record->numaveraged = 0;
            }
            $row['qsgrade' . $question->qnumber] = $this->format_average($record, true);
        }
        return $row;
    }

    /**
     * Format an entry in an average row.
     * @param object $record with fields grade and numaveraged
     */
    protected function format_average($record, $question = false) {
        if (is_null($record->grade)) {
            $average = '-';
        } else if ($question) {
            $average = quiz_format_question_grade($this->quiz, $record->grade);
        } else {
            $average = quiz_format_grade($this->quiz, $record->grade);
        }

        if ($this->download) {
            return $average;
        } else {
            return '<span class="avgcell"><span class="average">' . $average . '</span> <span class="count">(' .
                    $record->numaveraged . ')</span></span>';
        }
    }

    public function wrap_html_start() {
        if ($this->is_downloading() || !$this->candelete) {
            return;
        }

        // Start form
        echo '<div id="tablecontainer">';
        echo '<form id="attemptsform" method="post" action="' . $this->reporturl->out(true) .'">';
        echo '<div style="display: none;">';
        echo $this->reporturl->hidden_params_out(array(), 0, $this->displayoptions);
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />' . "\n";
        echo '</div>';
        echo '<div>';
    }

    public function wrap_html_finish() {
        if ($this->is_downloading() || !$this->candelete) {
            return;
        }

        $strreallydel  = addslashes_js(get_string('deleteattemptcheck','quiz'));
        echo '<div id="commands">';
        echo '<a href="javascript:select_all_in(\'DIV\',null,\'tablecontainer\');">'.
                get_string('selectall', 'quiz').'</a> / ';
        echo '<a href="javascript:deselect_all_in(\'DIV\',null,\'tablecontainer\');">'.
                get_string('selectnone', 'quiz').'</a> ';
        echo '&nbsp;&nbsp;';
        if (has_capability('mod/quiz:regrade', $this->context)) {
            echo '<input type="submit" name="regrade" value="'.get_string('regradeselected', 'quiz_overview').'"/>';
        }
        echo '<input type="submit" onclick="return confirm(\''.$strreallydel.'\');" name="delete" value="'.get_string('deleteselected', 'quiz_overview').'"/>';
        echo '</div>';
        // Close form
        echo '</div>';
        echo '</form></div>';
    }

    public function col_sumgrades($attempt) {
        if (!$attempt->timefinish) {
            return '-';
        }

        $grade = quiz_rescale_grade($attempt->sumgrades, $this->quiz);
        if ($this->is_downloading()) {
            return $grade;
        }

        if (isset($this->regradedqs[$attempt->usageid])) {
            $newsumgrade = 0;
            $oldsumgrade = 0;
            foreach ($this->questions as $question) {
                if (isset($this->regradedqs[$attempt->usageid][$question->qnumber])) {
                    $newsumgrade += $this->regradedqs[$attempt->usageid][$question->qnumber]->newfraction * $question->maxmark;
                    $oldsumgrade += $this->regradedqs[$attempt->usageid][$question->qnumber]->newfraction * $question->maxmark;
                } else {
                    $newsumgrade += $this->lateststeps[$attempt->usageid][$question->qnumber]->fraction * $question->maxmark;
                    $oldsumgrade += $this->lateststeps[$attempt->usageid][$question->qnumber]->fraction * $question->maxmark;
                }
            }
            $newsumgrade = quiz_rescale_grade($newsumgrade, $this->quiz);
            $oldsumgrade = quiz_rescale_grade($oldsumgrade, $this->quiz);
            $grade = "<del>$oldsumgrade</del><br />$newsumgrade";
        }
        $gradehtml = '<a href="review.php?q='.$this->quiz->id.'&amp;attempt='.$attempt->attempt.'">'.$grade.'</a>';
        if ($this->qmsubselect && $attempt->gradedattempt) {
            $gradehtml = '<div class="highlight">'.$gradehtml.'</div>';
        }
        return $gradehtml;
    }

    /**
     * @param string $colname the name of the column.
     * @param object $attempt the row of data - see the SQL in display() in
     * mod/quiz/report/overview/report.php to see what fields are present,
     * and what they are called.
     * @return string the contents of the cell.
     */
    public function other_cols($colname, $attempt) {
        global $CFG;

        if (!preg_match('/^qsgrade(\d+)$/', $colname, $matches)) {
            return NULL;
        }
        $qnumber = $matches[1];
        $question = $this->questions[$qnumber];
        if (!isset($this->lateststeps[$attempt->usageid][$qnumber])) {
            return '-';
        }

        $stepdata = $this->lateststeps[$attempt->usageid][$qnumber];
        $state = question_state::get($stepdata->state);

        if (is_null($stepdata->fraction)) {
            if ($state == question_state::$needsgrading) {
                $grade = get_string('requiresgrading', 'question');
            } else {
                $grade = '-';
            }
        } else {
            $grade = quiz_rescale_grade($stepdata->fraction * $question->maxmark, $this->quiz, 'question');
        }

        if ($this->is_downloading()) {
            return $grade;
        }

        if (isset($this->regradedqs[$attempt->usageid][$qnumber])) {
            $gradefromdb = $grade;
            $newgrade = quiz_rescale_grade(
                    $this->regradedqs[$attempt->usageid][$qnumber]->newfraction * $question->maxmark,
                    $this->quiz, 'question');
            $oldgrade = quiz_rescale_grade(
                    $this->regradedqs[$attempt->usageid][$qnumber]->oldfraction * $question->maxmark,
                    $this->quiz, 'question');

            $grade = '<del>'.$oldgrade.'</del><br />' . $newgrade;
        }

        $flag = '';
        if ($stepdata->flagged) {
            $flag = ' <img src="' . $CFG->pixpath . '/i/flagged.png" alt="' .
                    get_string('flagged', 'question') . '" class="questionflag" />';
        }

        $feedbackimg = '';
        if ($state->is_finished() && $state != question_state::$needsgrading) {
            $feedbackimg = question_get_feedback_image($stepdata->fraction);
        }

        $grade = '<span class="que"><span class="' . $state->get_state_class() . '">' .
                $grade . " $feedbackimg $flag</span></span>";

        $grade = link_to_popup_window('/mod/quiz/reviewquestion.php?attempt=' .
                $attempt->attempt . '&amp;qnumber=' . $qnumber,
                'reviewquestion', $grade, 450, 650, get_string('reviewresponse', 'quiz'),
                'none', true);

        return $grade;
    }

    public function col_regraded($attempt) {
        if ($attempt->regraded == '') {
            return '';
        } else if ($attempt->regraded == 0) {
            return get_string('needed', 'quiz_overview');
        } else if ($attempt->regraded == 1) {
            return get_string('done', 'quiz_overview');
        }
    }

    protected function requires_latest_steps_loaded() {
        return $this->detailedmarks;
    }

    protected function is_latest_step_column($column) {
        if (preg_match('/^qsgrade([0-9]+)/', $column, $matches)) {
            return $matches[1];
        }
        return false;
    }

    protected function get_required_latest_state_fields($qnumber, $alias) {
        return "$alias.fraction * $alias.maxmark AS qsgrade$qnumber";
    }

    public function query_db($pagesize, $useinitialsbar = true) {
        parent::query_db($pagesize, $useinitialsbar);

        if ($this->detailedmarks && has_capability('mod/quiz:regrade', $this->context)) {
            $this->regradedqs = $this->get_regraded_questions();
        }
    }

    /**
     * Load the average grade for each question, averaged over particular users.
     * @param array $userids the user ids to average over.
     */
    function load_average_question_grades($userids) {
        global $CFG;

        $qmfilter = quiz_report_qm_filter_select($this->quiz, 'quiza');
        list($usql, $params) = get_in_or_equal($userids);

        $qubaids = new qubaid_join(
                "{$CFG->prefix}quiz_attempts quiza",
                'quiza.uniqueid',
                "($qmfilter) AND
                    quiza.userid $usql AND
                    quiza.quiz = {$this->quiz->id}");

        $dm = new question_engine_data_mapper();
        return $dm->load_average_marks($qubaids, array_keys($this->questions));
    }

    /**
     * Get all the questions in all the attempts being displayed that need regrading.
     * @return array A two dimensional array $questionusageid => $qnumber => $regradeinfo.
     */
    protected function get_regraded_questions() {
        $qubaids = $this->get_qubaids_condition();
        $regradedqs = get_records_select('quiz_question_regrade',
                'questionusageid ' . $qubaids->usage_id_in(), '', '*');
        if (empty($regradedqs)) {
            $regradedqs = array();
        }
        return quiz_report_index_by_keys($regradedqs, array('questionusageid', 'numberinusage'));
    }
}
