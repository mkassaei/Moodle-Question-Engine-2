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
 * Quiz statistics report calculations class.
 *
 * @package quiz_statistics
 * @copyright 2008 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * This class has methods to compute the question statistics from the raw data.
 *
 * @copyright 2008 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_statistics_question_stats {
    public $questions;
    public $subquestions = array();
    public $responses = array();

    protected $s;
    protected $summarksavg;
    protected $allattempts;

    /** @var mixed states from which to calculate stats - iteratable. */
    protected $states;

    protected $sumofmarkvariance = 0;
    protected $randomselectors = array();

    /**
     * Constructor.
     * @param $questions the questions.
     * @param $s Number of attempts included in the stats.
     * @param $summarksavg average quiz summarks.
     */
    public function __construct($questions, $s, $summarksavg) {
        $this->s = $s;
        $this->summarksavg = $summarksavg;

        foreach ($questions as $qnumber => $question) {
            $question->_stats = $this->make_blank_question_stats();
            $question->_stats->questionid = $question->id;
            $question->_stats->qnumber = $qnumber;
        }

        $this->questions = $questions;
    }

    /**
     * @return object ready to hold all the question statistics.
     */
    protected function make_blank_question_stats() {
        $statsinit = new stdClass;
        $statsinit->qnumber = null;
        $statsinit->s = 0;
        $statsinit->totalmarks = 0;
        $statsinit->totalothermarks = 0;
        $statsinit->markvariancesum = 0;
        $statsinit->othermarkvariancesum = 0;
        $statsinit->covariancesum = 0;
        $statsinit->covariancemaxsum = 0;
        $statsinit->subquestion = false;
        $statsinit->subquestions = '';
        $statsinit->covariancewithoverallmarksum = 0;
        $statsinit->randomguessscore = null;
        $statsinit->markarray = array();
        $statsinit->othermarksarray = array();
        return $statsinit;
    }

    /**
     * Load the data that will be needed to perform the calculations.
     *
     * @param integer $quizid the quiz id.
     * @param integer $currentgroup the current group. 0 for none.
     * @param array $groupstudents students in this group.
     * @param boolean $allattempts use all attempts, or just first attempts.
     */
    public function get_records($quizid, $currentgroup, $groupstudents, $allattempts) {
        global $CFG;

        $this->allattempts = $allattempts;

        list($qsql, $qparams) = get_in_or_equal(array_keys($this->questions), SQL_PARAMS_NAMED, 'q0000');
        list($fromqa, $whereqa, $qaparams) = quiz_report_attempts_sql(
                $quizid, $currentgroup, $groupstudents, $allattempts, false);

        $this->states = get_records_sql("
                SELECT
                    qas.id,
                    quiza.sumgrades,
                    qa.questionid,
                    qa.numberinusage,
                    qa.maxmark,
                    qas.fraction * qa.maxmark as mark

                FROM $fromqa
                JOIN {$CFG->prefix}question_attempts_new qa ON qa.questionusageid = quiza.uniqueid
                JOIN (
                    SELECT questionattemptid, MAX(id) AS latestid FROM {$CFG->prefix}question_attempt_steps GROUP BY questionattemptid
                ) lateststepid ON lateststepid.questionattemptid = qa.id
                JOIN {$CFG->prefix}question_attempt_steps qas ON qas.id = lateststepid.latestid

                WHERE
                    qa.numberinusage $qsql AND
                    $whereqa");

        if ($this->states === false) {
            throw new moodle_exception('errorstatisticsquestions', 'quiz_statistics');
        }
    }

    public function process_states() {
        set_time_limit(0);

        $subquestionstats = array();

        // Compute the statistics of position, and for random questions, work
        // out which questions appear in which positions.
        foreach ($this->states as $state) {
            $this->initial_states_walker($state, $this->questions[$state->numberinusage]->_stats);

            // If this is a random question what is the real item being used?
            if ($state->questionid != $this->questions[$state->numberinusage]->id) {
                if (!isset($subquestionstats[$state->questionid])) {
                    $subquestionstats[$state->questionid] = $this->make_blank_question_stats();
                    $subquestionstats[$state->questionid]->questionid = $state->questionid;
                    $subquestionstats[$state->questionid]->allattempts = $this->allattempts;
                    $subquestionstats[$state->questionid]->usedin = array();
                    $subquestionstats[$state->questionid]->subquestion = true;
                    $subquestionstats[$state->questionid]->differentweights = false;
                    $subquestionstats[$state->questionid]->maxmark = $state->maxmark;
                } else if ($subquestionstats[$state->questionid]->maxmark != $state->maxmark) {
                    $subquestionstats[$state->questionid]->differentweights = true;
                }

                $this->initial_states_walker($state,
                        $subquestionstats[$state->questionid], false);

                $number = $this->questions[$state->numberinusage]->number;
                $subquestionstats[$state->questionid]->usedin[$number] = $number;

                $randomselectorstring = $this->questions[$state->numberinusage]->category .
                        '/' . $this->questions[$state->numberinusage]->questiontext;
                if (!isset($this->randomselectors[$randomselectorstring])) {
                    $this->randomselectors[$randomselectorstring] = array();
                }
                $this->randomselectors[$randomselectorstring][$state->questionid] =
                        $state->questionid;
            }
        }

        foreach ($this->randomselectors as $key => $notused) {
            ksort($this->randomselectors[$key]);
        }

        // Compute the statistics of question id, if we need any.
        $this->subquestions = question_load_questions(array_keys($subquestionstats));
        foreach ($this->subquestions as $qid => $subquestion) {
            $subquestion->_stats = $subquestionstats[$qid];
            $subquestion->maxmark = $subquestion->_stats->maxmark;
            $subquestion->randomguessscore = $this->get_random_guess_score($subquestion);

            $this->initial_question_walker($subquestion->_stats);

            if ($subquestionstats[$qid]->differentweights) {
                notify(get_string('erroritemappearsmorethanoncewithdifferentweight', 'quiz_statistics', $this->subquestions[$qid]->name));
            }

            if ($subquestion->_stats->usedin) {
                sort($subquestion->_stats->usedin, SORT_NUMERIC);
                $subquestion->_stats->positions = implode(',', $subquestion->_stats->usedin);
            } else {
                $subquestion->_stats->positions = '';
            }
        }

        // Finish computing the averages, and put the subquestion data into the
        // corresponding questions.

        // This cannot be a foreach loop because we need to have both
        // $question and $nextquestion available, but apart from that it is
        // foreach ($this->questions as $qid => $question) {
        reset($this->questions);
        while (list($qnumber, $question) = each($this->questions)) {
            $nextquestion = current($this->questions);
            $question->_stats->allattempts = $this->allattempts;
            $question->_stats->positions = $question->number;
            $question->_stats->maxmark = $question->maxmark;
            $question->_stats->randomguessscore = $this->get_random_guess_score($question);

            $this->initial_question_walker($question->_stats);

            if ($question->qtype == 'random') {
                $randomselectorstring = $question->category.'/'.$question->questiontext;
                if ($nextquestion && $nextquestion->qtype == 'random') {
                    $nextrandomselectorstring = $nextquestion->category.'/'.$nextquestion->questiontext;
                    if ($randomselectorstring == $nextrandomselectorstring) {
                        continue; // Next loop iteration
                    }
                }
                if (isset($this->randomselectors[$randomselectorstring])) {
                    $question->_stats->subquestions = implode(',', $this->randomselectors[$randomselectorstring]);
                }
            }
        }

        // Go through the records one more time
        foreach ($this->states as $state) {
            $this->secondary_states_walker($state,
                    $this->questions[$state->numberinusage]->_stats);

            if ($this->questions[$state->numberinusage]->qtype == 'random') {
                $this->secondary_states_walker($state,
                        $this->subquestions[$state->questionid]->_stats);
            }
        }

        $sumofcovariancewithoverallmark = 0;
        foreach ($this->questions as $qnumber => $question) {
            $this->secondary_question_walker($question->_stats);

            $this->sumofmarkvariance += $question->_stats->markvariance;

            if ($question->_stats->covariancewithoverallmark >= 0) {
                $sumofcovariancewithoverallmark +=
                        sqrt($question->_stats->covariancewithoverallmark);
                $question->_stats->negcovar = 0;
            } else {
                $question->_stats->negcovar = 1;
            }
        }

        foreach ($this->subquestions as $subquestion) {
            $this->secondary_question_walker($subquestion->_stats);
        }

        foreach ($this->questions as $question) {
            if ($sumofcovariancewithoverallmark) {
                if ($question->_stats->negcovar) {
                    $question->_stats->effectiveweight = null;
                } else {
                    $question->_stats->effectiveweight = 100 *
                            sqrt($question->_stats->covariancewithoverallmark) /
                            $sumofcovariancewithoverallmark;
                }
            } else {
                $question->_stats->effectiveweight = null;
            }
        }
    }

    /**
     * Update $stats->totalmarks, $stats->markarray, $stats->totalothermarks
     * and $stats->othermarksarray to include another state.
     *
     * @param object $state the state to add to the statistics.
     * @param object $stats the question statistics we are accumulating.
     * @param boolean $positionstat whether this is a statistic of position of question.
     */
    protected function initial_states_walker($state, &$stats, $positionstat = true) {
        $stats->s++;
        $stats->totalmarks += $state->mark;
        $stats->markarray[] = $state->mark;

        if ($positionstat) {
            $stats->totalothermarks += $state->sumgrades - $state->mark;
            $stats->othermarksarray[] = $state->sumgrades - $state->mark;

        } else {
            $stats->totalothermarks += $state->sumgrades;
            $stats->othermarksarray[] = $state->sumgrades;
        }
    }

    /**
     * Perform some computations on the per-question statistics calculations after
     * we have been through all the states.
     *
     * @param object $stats quetsion stats to update.
     */
    protected function initial_question_walker(&$stats) {
        $stats->markaverage = $stats->totalmarks / $stats->s;

        if ($stats->maxmark != 0) {
            $stats->facility = $stats->markaverage / $stats->maxmark;
        } else {
            $stats->facility = null;
        }

        $stats->othermarkaverage = $stats->totalothermarks / $stats->s;

        sort($stats->markarray, SORT_NUMERIC);
        sort($stats->othermarksarray, SORT_NUMERIC);
    }

    /**
     * Now we know the averages, accumulate the date needed to compute the higher
     * moments of the question scores.
     *
     * @param object $state the state to add to the statistics.
     * @param object $stats the question statistics we are accumulating.
     * @param boolean $positionstat whether this is a statistic of position of question.
     */
    protected function secondary_states_walker($state, &$stats) {
        $markdifference = $state->mark - $stats->markaverage;
        if ($stats->subquestion) {
            $othermarkdifference = $state->sumgrades - $stats->othermarkaverage;
        } else {
            $othermarkdifference = $state->sumgrades - $state->mark - $stats->othermarkaverage;
        }
        $overallmarkdifference = $state->sumgrades - $this->summarksavg;

        $sortedmarkdifference = array_shift($stats->markarray) - $stats->markaverage;
        $sortedothermarkdifference = array_shift($stats->othermarksarray) - $stats->othermarkaverage;

        $stats->markvariancesum += pow($markdifference, 2);
        $stats->othermarkvariancesum += pow($othermarkdifference, 2);
        $stats->covariancesum += $markdifference * $othermarkdifference;
        $stats->covariancemaxsum += $sortedmarkdifference * $sortedothermarkdifference;
        $stats->covariancewithoverallmarksum += $markdifference * $overallmarkdifference;
    }

    /**
     * Perform more per-question statistics calculations.
     *
     * @param object $stats quetsion stats to update.
     */
    protected function secondary_question_walker(&$stats) {
        if ($stats->s > 1) {
            $stats->markvariance = $stats->markvariancesum / ($stats->s - 1);
            $stats->othermarkvariance = $stats->othermarkvariancesum / ($stats->s - 1);
            $stats->covariance = $stats->covariancesum / ($stats->s - 1);
            $stats->covariancemax = $stats->covariancemaxsum / ($stats->s - 1);
            $stats->covariancewithoverallmark = $stats->covariancewithoverallmarksum / ($stats->s - 1);
            $stats->sd = sqrt($stats->markvariancesum / ($stats->s - 1));

        } else {
            $stats->markvariance = null;
            $stats->othermarkvariance = null;
            $stats->covariance = null;
            $stats->covariancemax = null;
            $stats->covariancewithoverallmark = null;
            $stats->sd = null;
        }

        if ($stats->markvariance * $stats->othermarkvariance) {
            $stats->discriminationindex = 100 * $stats->covariance /
                    sqrt($stats->markvariance * $stats->othermarkvariance);
        } else {
            $stats->discriminationindex = null;
        }

        if ($stats->covariancemax) {
            $stats->discriminativeefficiency = 100 * $stats->covariance /
                    $stats->covariancemax;
        } else {
            $stats->discriminativeefficiency = null;
        }
    }

    public function process_responses() {
        return; // TODO
        foreach ($this->states as $state) {
            if ($this->questions[$state->question]->qtype == 'random') {
                if ($realstate = question_get_real_state($state)) {
                    $this->process_actual_responses($this->subquestions[$realstate->question], $realstate);
                }
            } else {
                $this->process_actual_responses($this->questions[$state->question], $state);
            }
        }
        $this->responses = quiz_report_unindex($this->responses);
    }

    protected function add_response_detail_to_array($responsedetail) {
        $responsedetail->rcount = 1;
        if (isset($this->responses[$responsedetail->subqid])) {
            if (isset($this->responses[$responsedetail->subqid][$responsedetail->aid])) {
                if (isset($this->responses[$responsedetail->subqid][$responsedetail->aid][$responsedetail->response])) {
                    $this->responses[$responsedetail->subqid][$responsedetail->aid][$responsedetail->response]->rcount++;
                } else {
                    $this->responses[$responsedetail->subqid][$responsedetail->aid][$responsedetail->response] = $responsedetail;
                }
            } else {
                $this->responses[$responsedetail->subqid][$responsedetail->aid] = array($responsedetail->response => $responsedetail);
            }
        } else {
            $this->responses[$responsedetail->subqid] = array();
            $this->responses[$responsedetail->subqid][$responsedetail->aid] = array($responsedetail->response => $responsedetail);
        }
    }

    /** 
     * Get the data for the individual question response analysis table.
     */
    protected function process_actual_responses($question, $state) {
        global $QTYPES;
        if ($question->qtype != 'random' && 
                $QTYPES[$question->qtype]->show_analysis_of_responses()) {
            $restoredstate = clone($state);
            restore_question_state($question, $restoredstate);
            $responsedetails = $QTYPES[$question->qtype]->get_actual_response_details($question, $restoredstate);
            foreach ($responsedetails as $responsedetail) {
                $responsedetail->questionid = $question->id;
                $this->add_response_detail_to_array($responsedetail);
            }
        }
    }

    /**
     * @param object $question
     * @return number the random guess score for this question.
     */
    protected function get_random_guess_score($questiondata) {
        global $QTYPES;
        return $QTYPES[$questiondata->qtype]->get_random_guess_score($questiondata);
    }

    /**
     * Used when computing CIC.
     * @return number
     */
    public function get_sum_of_mark_variance() {
        return $this->sumofmarkvariance;
    }
}

