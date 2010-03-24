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
 * Quiz statistics report, table for showing statistics about a particular question.
 *
 * @package quiz_statistics
 * @copyright 2008 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir . '/tablelib.php');


/**
 * This table shows statistics about a particular question.
 *
 * Lists the responses that students made to this question, with frequency counts.
 *
 * The responses may be grouped, either by subpart of the question, or by the
 * answer they match.
 *
 * @copyright 2008 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_report_statistics_question_table extends flexible_table {
    /** @var object this question with a _stats field. */
    protected $question;

    /**
     * Constructor.
     * @param $qid the id of the particular question whose statistics are being
     * displayed.
     */
    public function __construct($qid) {
        parent::__construct('mod-quiz-report-statistics-question-table' . $qid);
    }

    /**
     * Setup the columns and headers and other properties of the table and then
     * call flexible_table::setup() method.
     *
     * @param moodle_url $reporturl the URL to redisplay this report.
     * @param object $question a question with a _stats field
     * @param boolean $hassubqs
     */
    public function setup($reporturl, $question, $hassubqs) {
        $this->question = $question;

        // Define table columns
        $columns = array();
        $headers = array();

        if ($hassubqs) {
            $columns[] = 'subq';
            $headers[] = '';
        }

        $columns[] = 'response';
        $headers[] = get_string('response', 'quiz_statistics');

        $columns[] = 'credit';
        $headers[] = get_string('optiongrade', 'quiz_statistics');

        $columns[] = 'rcount';
        $headers[] = get_string('count', 'quiz_statistics');

        $columns[] = 'frequency';
        $headers[] = get_string('frequency', 'quiz_statistics');

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->sortable(false);

        $this->column_class('credit', 'numcol');
        $this->column_class('rcount', 'numcol');
        $this->column_class('frequency', 'numcol');

        // Set up the table
        $this->define_baseurl($reporturl->out());

        $this->collapsible(false);

        $this->set_attribute('class', 'generaltable generalbox boxaligncenter');

        parent::setup();
    }

    /**
     * If the question has sub-parts, this column identifies the part.
     * @param object $response containst the data to display.
     * @return string contents of this table cell.
     */
    protected function col_subq($response) {
        return $response->subq;
    }

    /**
     * The response the student gave.
     * @param object $response containst the data to display.
     * @return string contents of this table cell.
     */
    protected function col_response($response) {
        global $QTYPES;
        if (!$this->is_downloading() || $this->is_downloading() == 'xhtml') {
            return $QTYPES[$this->question->qtype]->format_response($response->response, $this->question->questiontextformat);
        } else {
            return $response->response;
        }
    }

    /**
     * The mark fraction that this response earns.
     * @param object $response containst the data to display.
     * @return string contents of this table cell.
     */
    protected function col_credit($response) {
        if (is_null($response->credit)) {
            return '';
        }

        return ($response->credit * 100) . '%';
    }

    /**
     * The frequency with which this response was given.
     * @param object $response containst the data to display.
     * @return string contents of this table cell.
     */
    protected function col_frequency($response) {
        if (!$this->question->_stats->s) {
            return '';
        }

        return format_float($response->rcount / $this->question->_stats->s * 100, 2) . '%';
    }
}
