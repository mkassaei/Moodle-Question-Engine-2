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
 * This file contains tests that walks a question through the manual graded
 * behaviour.
 *
 * @package qbehaviour_manualgraded
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once(dirname(__FILE__) . '/../../../engine/simpletest/helpers.php');

class qbehaviour_manualgraded_walkthrough_test extends qbehaviour_walkthrough_test_base {
    public function test_manual_graded_essay() {

        // Create a true-false question with correct answer true.
        $essay = test_question_maker::make_an_essay_question();
        $this->start_attempt_at_question($essay, 'deferredfeedback', 10);

        // Check the right model is being used.
        $this->assertEqual('manualgraded', $this->quba->
                get_question_attempt($this->qnumber)->get_behaviour_name());

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output($this->get_contains_question_text_expectation($essay),
                $this->get_does_not_contain_feedback_expectation());

        // Simulate some data submitted by the student.
        $this->process_submission(array('answer' => 'This is my wonderful essay!'));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_current_output(
                new ContainsTagWithAttribute('textarea', 'name',
                $this->quba->get_question_attempt($this->qnumber)->get_qt_field_name('answer')),
                $this->get_does_not_contain_feedback_expectation());

        // Process the same data again, check it does not create a new step.
        $numsteps = $this->get_step_count();
        $this->process_submission(array('answer' => 'This is my wonderful essay!'));
        $this->check_step_count($numsteps);

        // Process different data, check it creates a new step.
        $this->process_submission(array('answer' => ''));
        $this->check_step_count($numsteps + 1);
        $this->check_current_state(question_state::$todo);

        // Change back, check it creates a new step.
        $this->process_submission(array('answer' => 'This is my wonderful essay!'));
        $this->check_step_count($numsteps + 2);

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$needsgrading);
        $this->check_current_mark(null);
        $this->assertEqual('This is my wonderful essay!',
                $this->quba->get_response_summary($this->qnumber));

        // Process a manual comment.
        $this->manual_grade('Not good enough!', 10);

        // Verify.
        $this->check_current_state(question_state::$mangrright);
        $this->check_current_mark(10);
        $this->check_current_output(
                new PatternExpectation('/' . preg_quote('Not good enough!') . '/'));

        // Now change the max mark for the question and regrade.
        $this->quba->regrade_question($this->qnumber, 1);

        // Verify.
        $this->check_current_state(question_state::$mangrright);
        $this->check_current_mark(1);
    }

    public function test_manual_graded_truefalse() {

        // Create a true-false question with correct answer true.
        $tf = test_question_maker::make_a_truefalse_question();
        $this->start_attempt_at_question($tf, 'manualgraded', 2);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_question_text_expectation($tf),
                $this->get_does_not_contain_feedback_expectation());

        // Process a true answer and check the expected result.
        $this->process_submission(array('answer' => 1));

        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_tf_true_radio_expectation(true, true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_does_not_contain_feedback_expectation());

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$needsgrading);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_does_not_contain_specific_feedback_expectation());

        // Process a manual comment.
        $this->manual_grade('Not good enough!', 1);

        $this->check_current_state(question_state::$mangrpartial);
        $this->check_current_mark(1);
        $this->check_current_output(
            $this->get_does_not_contain_correctness_expectation(),
            $this->get_does_not_contain_specific_feedback_expectation(),
            new PatternExpectation('/' . preg_quote('Not good enough!') . '/'));
    }
}
