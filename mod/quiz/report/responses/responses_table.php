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
 * This file defines the quiz responses report class.
 *
 * @package quiz_responses
 * @copyright 2008 Jean-Michel Vedrine
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * This is a table subclass for displaying the quiz responses report.
 *
 * @copyright 2008 Jean-Michel Vedrine
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_report_responses_table extends quiz_attempt_report_table {

    public function __construct($quiz , $qmsubselect, $groupstudents,
            $students, $questions, $candelete, $reporturl, $displayoptions) {
        parent::__construct('mod-quiz-report-responses-report', $quiz , $qmsubselect, $groupstudents,
                $students, $questions, $candelete, $reporturl, $displayoptions);
    }

    public function build_table() {
        if ($this->rawdata) {
            $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
            parent::build_table();
        }
    }

    public function wrap_html_start() {
        if ($this->is_downloading() || !$this->candelete) {
            return;
        }

        // Start form
        $strreallydel  = addslashes_js(get_string('deleteattemptcheck','quiz'));
        echo '<div id="tablecontainer">';
        echo '<form id="attemptsform" method="post" action="' . $this->reporturl->out(true) .
                '" onsubmit="confirm(\''.$strreallydel.'\');">';
        echo '<div style="display: none;">';
        echo $this->reporturl->hidden_params_out(array(), 0, $this->displayoptions);
        echo '</div>';
        echo '<div>';
    }

    public function wrap_html_finish() {
        if ($this->is_downloading() || !$this->candelete) {
            return;
        }

        echo '<div id="commands">';
        echo '<a href="javascript:select_all_in(\'DIV\',null,\'tablecontainer\');">'.
                get_string('selectall', 'quiz').'</a> / ';
        echo '<a href="javascript:deselect_all_in(\'DIV\',null,\'tablecontainer\');">'.
                get_string('selectnone', 'quiz').'</a> ';
        echo '&nbsp;&nbsp;';
        echo '<input type="submit" value="'.get_string('deleteselected', 'quiz_overview').'"/>';
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

        $gradehtml = '<a href="review.php?q='.$this->quiz->id.'&amp;attempt='.$attempt->attempt.'">'.$grade.'</a>';
        if ($this->qmsubselect && $attempt->gradedattempt) {
            $gradehtml = '<div class="highlight">'.$gradehtml.'</div>';
        }
        return $gradehtml;
    }

    public function data_col($qnumber, $field, $attempt) {
        global $CFG;

        if ($attempt->usageid == 0) {
            return '-';
        }

        $question = $this->questions[$qnumber];
        if (!isset($this->lateststeps[$attempt->usageid][$qnumber])) {
            return '-';
        }

        $stepdata = $this->lateststeps[$attempt->usageid][$qnumber];
        $state = question_state::get($stepdata->state);

        if (is_null($stepdata->$field)) {
            $summary = '-';
        } else {
            $summary = $stepdata->$field;
        }

        if ($this->is_downloading() || $field != 'responsesummary') {
            return $summary;
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

        $summary = '<span class="que"><span class="' . $state->get_state_class() . '">' .
                $summary . " $feedbackimg $flag</span></span>";

        $summary = link_to_popup_window('/mod/quiz/reviewquestion.php?attempt=' .
                $attempt->attempt . '&amp;question=' . $qnumber,
                'reviewquestion', $summary, 450, 650, get_string('reviewresponse', 'quiz'),
                'none', true);

        return $summary;
    }

    public function other_cols($colname, $attempt) {
        if (preg_match('/^question(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'questionsummary', $attempt);

        } else if (preg_match('/^response(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'responsesummary', $attempt);

        } else if (preg_match('/^right(\d+)$/', $colname, $matches)) {
            return $this->data_col($matches[1], 'rightanswer', $attempt);

        } else {
            return NULL;
        }
    }

    protected function requires_latest_steps_loaded() {
        return true;
    }

    protected function is_latest_step_column($column) {
        if (preg_match('/^(?:question|response|right)([0-9]+)/', $column, $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * Get any fields that might be needed when sorting on date for a particular qnumber.
     * @param integer $qnumber the qnumber for the column we want.
     * @param string $alias the table alias for latest state information relating to that qnumber.
     */
    protected function get_required_latest_state_fields($qnumber, $alias) {
        return "$alias.questionsummary AS question$qnumber,
                $alias.rightanswer AS right$qnumber,
                $alias.responsesummary AS response$qnumber";
    }
}
