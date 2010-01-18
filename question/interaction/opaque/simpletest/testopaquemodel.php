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
 * This file contains tests for the Opaque interaction model.
 *
 * @package qim_opaque
 * @copyright © 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once(dirname(__FILE__) . '/../../../engine/simpletest/helpers.php');
require_once(dirname(__FILE__) . '/../model.php');

class qim_opaque_test extends qim_walkthrough_test_base {
    /**
     * Makes an Opaque question that refers to one of the sample questions
     * supplied by OpenMark.
     * @return unknown_type
     */
    protected function make_standard_om_question() {
        $engineid = get_field('question_opaque_engines', 'MIN(id)', '', '');
        if (empty($engineid)) {
            throw new Exception('Cannot test Opaque. No question engines configured.');
        }

        question_bank::load_question_definition_classes('opaque');
        $q = new qtype_opaque_question();
        test_question_maker::initialise_a_question($q);

        $q->name = 'samples.mu120.module5.question01';
        $q->qtype = question_bank::get_qtype('opaque');
        $q->defaultgrade = 3;

        $q->engineid = $engineid;
        $q->remoteid = 'samples.mu120.module5.question01';
        $q->remoteversion = '1.0';

        return $q;
    }

    public function test_something() {
        $q = $this->make_standard_om_question();
        $this->start_attempt_at_question($q, 'interactive');

        // Check the initial state.
        $this->check_current_state(question_state::INCOMPLETE);
        $this->check_current_mark(null);
        $this->check_current_output(
                new PatternExpectation('/Below is a plan of a proposed garden/'),
                new PatternExpectation('/You have 3 attempts/'));

        // Save the wrong answer.
        $this->process_submission(array('omval_response1' => 1, 'omval_response2' => 666,
                'omact_gen_14' => 'Check'));

        // Verify.
        $this->check_current_state(question_state::INCOMPLETE);
        $this->check_current_mark(null);
        $this->check_current_output(
                new PatternExpectation('/Below is a plan of a proposed garden/'),
                new PatternExpectation('/incorrect/'),
                new PatternExpectation('/' . preg_quote(get_string('submissionnotcorrect', 'qim_opaque')) . '/'));

        // TODO
    }

    public function test_gave_up() {
        $q = $this->make_standard_om_question();
        $this->start_attempt_at_question($q, 'interactive');

        $this->quba->finish_all_questions();

        $this->check_current_state(question_state::GAVE_UP);
        $this->check_current_mark(null);
        $this->check_current_output(
                new PatternExpectation('/' .
                        preg_quote(get_string('notcompletedmessage', 'qtype_opaque')) . '/'));
    }
}
