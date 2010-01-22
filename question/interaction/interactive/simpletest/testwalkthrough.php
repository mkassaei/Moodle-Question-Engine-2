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
 * This file contains tests that walks a question through the interactive
 * interaction model.
 *
 * @package qim_interactive
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once(dirname(__FILE__) . '/../../../engine/simpletest/helpers.php');

class qim_interactive_walkthrough_test extends qim_walkthrough_test_base {
    protected function get_tries_remaining_expectation($n) {
        return new PatternExpectation('/' . preg_quote(get_string('triesremaining', 'qim_interactive', $n)) . '/');
    }

    protected function get_contains_try_again_button_expectation($enabled = null) {
        $expectedattributes = array(
            'type' => 'submit',
            'name' => $this->quba->get_field_prefix($this->qnumber) . '!tryagain',
        );
        $forbiddenattributes = array();
        if ($enabled === true) {
            $forbiddenattributes['disabled'] = 'disabled';
        } else if ($enabled === false) {
            $expectedattributes['disabled'] = 'disabled';
        }
        return new ContainsTagWithAttributes('input', $expectedattributes, $forbiddenattributes);
    }

    protected function get_does_not_contain_try_again_button_expectation() {
        return new NoPatternExpectation('/name="' .
                $this->quba->get_field_prefix($this->qnumber) . '!tryagain"/');
    }

    public function test_interactive_feedback_multichoice_right() {

        // Create a true-false question with correct answer true.
        $mc = test_question_maker::make_a_multichoice_single_question();
        $mc->maxmark = 1;
        $this->start_attempt_at_question($mc, 'interactive');

        $rightindex = $this->get_mc_right_answer_index($mc);
        $wrongindex = ($rightindex + 1) % 3;

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_question_text_expectation($mc),
                $this->get_contains_mc_radio_expectation(0, true, false),
                $this->get_contains_mc_radio_expectation(1, true, false),
                $this->get_contains_mc_radio_expectation(2, true, false),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(3));

        // Save the wrong answer.
        $this->process_submission(array('answer' => $wrongindex));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_mc_radio_expectation($wrongindex, true, true),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, true, false),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, true, false),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(3));

        // Submit the wrong answer.
        $this->process_submission(array('answer' => $wrongindex, '!submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_mc_radio_expectation($wrongindex, false, true),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, false, false),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, false, false),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_tries_remaining_expectation(2));

        // Check that, if we review in this state, the try again button is disabled.
        $displayoptions = new question_display_options();
        $displayoptions->readonly = true;
        $html = $this->quba->render_question($this->qnumber, $displayoptions);
        $this->assert($this->get_contains_try_again_button_expectation(false), $html);

        // Do try again.
        $this->process_submission(array('!tryagain' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_mc_radio_expectation($wrongindex, true, true),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, true, false),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, true, false),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(2));

        // Submit the wrong answer.
        $this->process_submission(array('answer' => $rightindex, '!submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(0.6666667);
        $this->check_current_output(
                $this->get_contains_mc_radio_expectation($rightindex, false, true),
                $this->get_contains_mc_radio_expectation(($rightindex + 1) % 3, false, false),
                $this->get_contains_mc_radio_expectation(($rightindex + 1) % 3, false, false),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_correct_expectation());

        // Finish the attempt - should not need to add a new state.
        $numsteps = $this->get_step_count();
        $this->quba->finish_all_questions();

        // Verify.
        $this->assertEqual($numsteps, $this->get_step_count());
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(0.6666667);
        $this->check_current_output(
                $this->get_contains_mc_radio_expectation($rightindex, false, true),
                $this->get_contains_mc_radio_expectation(($rightindex + 1) % 3, false, false),
                $this->get_contains_mc_radio_expectation(($rightindex + 1) % 3, false, false),
                $this->get_contains_correct_expectation());

        // Process a manual comment.
        $this->manual_grade('Not good enough!', 0.5);

        // Verify.
        $this->check_current_state(question_state::$mangrpartial);
        $this->check_current_mark(0.5);
        $this->check_current_output(
                $this->get_contains_partcorrect_expectation(),
                new PatternExpectation('/' . preg_quote('Not good enough!') . '/'));

        // Check regrading does not mess anything up.
        $this->quba->regrade_all_questions();

        // Verify.
        $this->check_current_state(question_state::$mangrpartial);
        $this->check_current_mark(0.5);
        $this->check_current_output(
                $this->get_contains_partcorrect_expectation());

        $autogradedstep = $this->get_step($this->get_step_count() - 2);
        $this->assertWithinMargin($autogradedstep->get_fraction(), 0.6666667, 0.0000001);
    }

    public function test_interactive_finish_when_try_again_showing() {

        // Create a true-false question with correct answer true.
        $mc = test_question_maker::make_a_multichoice_single_question();
        $mc->maxmark = 1;
        $this->start_attempt_at_question($mc, 'interactive');

        $rightindex = $this->get_mc_right_answer_index($mc);
        $wrongindex = ($rightindex + 1) % 3;

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_question_text_expectation($mc),
                $this->get_contains_mc_radio_expectation(0, true, false),
                $this->get_contains_mc_radio_expectation(1, true, false),
                $this->get_contains_mc_radio_expectation(2, true, false),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(3));

        // Submit the wrong answer.
        $this->process_submission(array('answer' => $wrongindex, '!submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_mc_radio_expectation($wrongindex, false, true),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, false, false),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, false, false),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_tries_remaining_expectation(2));

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->check_current_output(
                $this->get_contains_mc_radio_expectation($wrongindex, false, true),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, false, false),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, false, false),
                $this->get_contains_incorrect_expectation());
    }

    public function test_interactive_shortanswer_try_to_submit_blank() {

        // Create a short answer question with correct answer true.
        $sa = test_question_maker::make_a_shortanswer_question();
        $this->start_attempt_at_question($sa, 'interactive');

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation());

        // Submit with certainty missing.
        $this->process_submission(array('!submit' => 1, 'answer' => ''));

        // Verify.
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_contains_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation());

        // Now get it wrong.
        $this->process_submission(array('!submit' => 1, 'answer' => 'newt'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_incorrect_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_contains_try_again_button_expectation(true));

        // Try again.
        $this->process_submission(array('!tryagain' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation());

        // Now submit blank again.
        $this->process_submission(array('!submit' => 1, 'answer' => ''));

        // Verify.
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_contains_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation());

        // Now get it right.
        $this->process_submission(array('!submit' => 1, 'answer' => 'frog'));

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(0.6666667);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_correct_expectation(),
                $this->get_does_not_contain_validation_error_expectation());
    }
}
