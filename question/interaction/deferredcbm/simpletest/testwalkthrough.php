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
 * This file contains tests that walks a question through the deferred feedback
 * interaction model.
 *
 * @package qim_deferredcbm
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once(dirname(__FILE__) . '/../../../engine/simpletest/helpers.php');

class qim_deferredcbm_walkthrough_test extends qim_walkthrough_test_base {
    public function test_deferred_cbm_truefalse_high_certainty() {

        // Create a true-false question with correct answer true.
        $tf = test_question_maker::make_a_truefalse_question();
        $this->start_attempt_at_question($tf, 'deferredcbm', 2);

        // Verify.
        $this->check_current_state(question_state::INCOMPLETE);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_question_text_expectation($tf),
                $this->get_contains_tf_true_radio_expectation(true, false),
                $this->get_contains_tf_false_radio_expectation(true, false),
                $this->get_contains_cbm_radio_expectation(1, true, false),
                $this->get_contains_cbm_radio_expectation(2, true, false),
                $this->get_contains_cbm_radio_expectation(3, true, false),
                $this->get_does_not_contain_feedback_expectation());

        // Process the data extracted for this question.
        $this->process_submission(array('answer' => 1, '!certainty' => 3));

        // Verify.
        $this->check_current_state(question_state::COMPLETE);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_tf_true_radio_expectation(true, true),
                $this->get_contains_cbm_radio_expectation(3, true, true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_does_not_contain_feedback_expectation());

        // Process the same data again, check it does not create a new step.
        $numsteps = $this->get_step_count();
        $this->process_submission(array('answer' => 1, '!certainty' => 3));
        $this->check_step_count($numsteps);

        // Process different data, check it creates a new step.
        $this->process_submission(array('answer' => 1, '!certainty' => 1));
        $this->check_step_count($numsteps + 1);
        $this->check_current_state(question_state::COMPLETE);

        // Change back, check it creates a new step.
        $this->process_submission(array('answer' => 1, '!certainty' => 3));
        $this->check_step_count($numsteps + 2);

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::GRADED_CORRECT);
        $this->check_current_mark(2);
        $this->check_current_output(
                $this->get_contains_tf_true_radio_expectation(false, true),
                $this->get_contains_cbm_radio_expectation(3, false, true),
                $this->get_contains_correct_expectation());

        // Process a manual comment.
        $this->manual_grade(1, 'Not good enough!');

        // Verify.
        $this->check_current_state(question_state::MANUALLY_GRADED_PARTCORRECT);
        $this->check_current_mark(1);
        $this->check_current_output(new PatternExpectation('/' . preg_quote('Not good enough!') . '/'));

        // Now change the correct answer to the question, and regrade.
        $tf->rightanswer = false;
        $this->quba->regrade_all_questions();

        // Verify.
        $this->check_current_state(question_state::MANUALLY_GRADED_PARTCORRECT);
        $this->check_current_mark(1);
        $autogradedstep = $this->get_step($this->get_step_count() - 2);
        $this->assertWithinMargin($autogradedstep->get_fraction(), -2, 0.0000001);
    }

    public function test_deferred_cbm_truefalse_low_certainty() {

        // Create a true-false question with correct answer true.
        $tf = test_question_maker::make_a_truefalse_question();
        $this->start_attempt_at_question($tf, 'deferredcbm', 2);

        // Verify.
        $this->check_current_state(question_state::INCOMPLETE);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_contains_cbm_radio_expectation(1, true, false),
                $this->get_does_not_contain_feedback_expectation());

        // Submit ansewer with low certainty.
        $this->process_submission(array('answer' => 1, '!certainty' => 1));

        // Verify.
        $this->check_current_state(question_state::COMPLETE);
        $this->check_current_mark(null);
        $this->check_current_output($this->get_does_not_contain_correctness_expectation(),
                $this->get_contains_cbm_radio_expectation(1, true, true),
                $this->get_does_not_contain_feedback_expectation());

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::GRADED_CORRECT);
        $this->check_current_mark(0.6666667);
        $this->check_current_output($this->get_contains_correct_expectation(),
                $this->get_contains_cbm_radio_expectation(1, false, true));
    }
}
