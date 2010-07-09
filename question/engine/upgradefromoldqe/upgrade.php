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
 * This file contains the code required to upgrade all the attempt data from
 * old versions of Moodle into the tables used by the new question engine.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


global $CFG;
require_once($CFG->libdir . '/questionlib.php');


/**
 * This class manages upgrading all the question attempts from the old database
 * structure to the new question engine.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_engine_attempt_upgrader {
    /** @var question_engine_upgrade_question_loader */
    protected $questionloader;

    public function __construct() {
        $this->questionloader = new question_engine_upgrade_question_loader();
    }

    protected function print_progress($done, $outof) {
        print_progress($done, $outof);
    }

    public function convert_all_quiz_attempts() {
        $quizids = get_records_menu('quiz', '', '', 'id', 'id,1');
        $done = 0;
        $outof = count($quizids);

        foreach ($quizids as $quizid => $notused) {
            $this->print_progress($done, $outof);

            $quiz = get_record('quiz', 'id', $quizid);
            $this->update_all_attemtps_at_quiz($quiz);

            $done += 1;
        }

        $this->print_progress($outof, $outof);
    }

    public function update_all_attemtps_at_quiz($quiz) {
        global $CFG;
        begin_sql();

        $quizattemptsrs = get_recordset('quiz_attempts', 'quiz', $quiz->id, 'uniqueid');
        $questionsessionsrs = get_recordset_sql("
                SELECT *
                FROM {$CFG->prefix}question_sessions
                WHERE attemptid IN (SELECT uniqueid FROM {$CFG->prefix}quiz_attempts
                    WHERE quiz = {$quiz->id})
                ORDER BY attemptid, questionid
        ");

        $questionsstatesrs = get_recordset_sql("
                SELECT *
                FROM {$CFG->prefix}question_states
                WHERE attempt IN (SELECT uniqueid FROM {$CFG->prefix}quiz_attempts
                    WHERE quiz = {$quiz->id})
                ORDER BY attempt, question, seq_number
        ");

        while ($attempt = rs_fetch_next_record($quizattemptsrs)) {
            while ($qsession = $this->get_next_question_session($attempt, $questionsessionsrs)) {
                $question = $this->questionloader->load_question($qsession->questionid);
                $qstates = $this->get_question_states($attempt, $question, $questionsstatesrs);
                $this->convert_attempt($quiz, $attempt, $question, $qsession, $qstates);
            }
        }

        rs_close($quizattemptsrs);
        rs_close($questionsessionsrs);
        rs_close($questionsstatesrs);

        commit_sql();

        return false; // Signal failure, since no work was acutally done.
    }

    public function get_next_question_session($attempt, $questionsessionsrs) {
        $qsession = rs_fetch_record($questionsessionsrs);

        if (!$qsession || $qsession->attemptid != $attempt->uniqueid) {
            // No more question sessions belonging to this attempt.
            return false;
        }

        // Session found, move the pointer in the RS and return the record.
        rs_next_record($questionsessionsrs);
        return $qsession;
    }

    public function get_question_states($attempt, $question, $questionsstatesrs) {
        $qstates = array();

        while ($state = rs_fetch_record($questionsstatesrs)) {
            if (!$state || $state->attempt != $attempt->uniqueid ||
                    $state->question != $question->id) {
                // We have found all the states for this attempt. Stop.
                break;
            }

            // Add the new state to the array, and advance.
            $qstates[$state->seq_number] = $state;
            rs_next_record($questionsstatesrs);
        }

        return $qstates;
    }

    public function convert_attempt($quiz, $attempt, $question, $qsession, $qstates) {
        print_object($attempt);
        print_object($question);
        print_object($qsession);
        print_object($qstates);
        // TODO
    }
}

/**
 * This class deals with loading (and caching) question definitions during the
 * question engine upgrade.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_engine_upgrade_question_loader {
    private $cache = array();

    public function load_question($questionid) {
        global $QTYPES;

        if (isset($this->cache[$questionid])) {
            return $this->cache[$questionid];
        }

        $question = get_record('question', 'id', $questionid);

        if (!array_key_exists($question->qtype, $QTYPES)) {
            $question->qtype = 'missingtype';
            $question->questiontext = '<p>' . get_string('warningmissingtype', 'quiz') . '</p>' . $question->questiontext;
        }

        $QTYPES[$question->qtype]->get_question_options($question);

        $this->cache[$questionid] = $question;

        return $this->cache[$questionid];
    }
}
