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

    public function query_db($pagesize, $useinitialsbar=true) {
        // Add table joins so we can sort by question answer
        // unfortunately can't join all tables necessary to fetch all answers
        // to get the state for one question per attempt row we must join two tables
        // and there is a limit to how many joins you can have in one query. In MySQL it
        // is 61. This means that when having more than 29 questions the query will fail.
        // So we join just the tables needed to sort the attempts.
        if ($sort = $this->get_sql_sort()) {
                $this->sql->from .= ' ';
                $sortparts    = explode(',', $sort);
                $matches = array();
                foreach ($sortparts as $sortpart) {
                    $sortpart = trim($sortpart);
                    if (preg_match('/^qsanswer([0-9]+)/', $sortpart, $matches)) {
                        $qid = intval($matches[1]);
                        // TODO
                        $this->sql->fields .=  ", qs$qid.grade AS qsgrade$qid, qs$qid.answer AS qsanswer$qid, qs$qid.event AS qsevent$qid, qs$qid.id AS qsid$qid";
                        $this->sql->from .= "LEFT JOIN {question_sessions} qns$qid ON qns$qid.attemptid = qa.uniqueid AND qns$qid.questionid = :qid$qid ";
                        $this->sql->from .=  "LEFT JOIN  {question_states} qs$qid ON qs$qid.id = qns$qid.newgraded ";
                        $this->sql->params['qid'.$qid] = $qid;
                    }
                }
        }

        parent::query_db($pagesize, $useinitialsbar);

        // Get all the attempt ids we want to display on this page
        // or to export for download.
        if (!$this->is_downloading()) {
            $qubaids = array();
            foreach ($this->rawdata as $attempt) {
                if ($attempt->usageid > 0) {
                    $qubaids[] = $attempt->usageid;
                }
            }
            $this->lateststeps = quiz_report_get_latest_steps(
                    new qubaid_list($qubaids), array_keys($this->questions));

        } else {
            $from = substr($this->sql->from, 5); // Strip of 'FROM '.
            $qubaids = new qubaid_join($from, 'usageid', $this->sql->where);
            $this->lateststeps = quiz_report_get_latest_steps($qubaids, array_keys($this->questions));
        }
    }
}
