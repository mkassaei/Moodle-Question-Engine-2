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
            'name' => $this->quba->get_field_prefix($this->qnumber) . '-tryagain',
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
                $this->quba->get_field_prefix($this->qnumber) . '-tryagain"/');
    }

    public function test_interactive_feedback_multichoice_right() {

        // Create a multichoice single question.
        $mc = test_question_maker::make_a_multichoice_single_question();
        $mc->maxmark = 1;
        $mc->hints = array(
            new question_hint_with_parts('This is the first hint.', false, false),
            new question_hint_with_parts('This is the second hint.', true, true),
        );
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
                $this->get_tries_remaining_expectation(3),
                $this->get_no_hint_visible_expectation());

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
                $this->get_tries_remaining_expectation(3),
                $this->get_no_hint_visible_expectation());

        // Submit the wrong answer.
        $this->process_submission(array('answer' => $wrongindex, '-submit' => 1));

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
                $this->get_tries_remaining_expectation(2),
                $this->get_contains_hint_expectation('This is the first hint'));

        // Check that, if we review in this state, the try again button is disabled.
        $displayoptions = new question_display_options();
        $displayoptions->readonly = true;
        $html = $this->quba->render_question($this->qnumber, $displayoptions);
        $this->assert($this->get_contains_try_again_button_expectation(false), $html);

        // Do try again.
        $this->process_submission(array('-tryagain' => 1));

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
                $this->get_tries_remaining_expectation(2),
                $this->get_no_hint_visible_expectation());

        // Submit the right answer.
        $this->process_submission(array('answer' => $rightindex, '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(0.6666667);
        $this->check_current_output(
                $this->get_contains_mc_radio_expectation($rightindex, false, true),
                $this->get_contains_mc_radio_expectation(($rightindex + 1) % 3, false, false),
                $this->get_contains_mc_radio_expectation(($rightindex + 1) % 3, false, false),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_correct_expectation(),
                $this->get_no_hint_visible_expectation());

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
                $this->get_contains_correct_expectation(),
                $this->get_no_hint_visible_expectation());

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

        // Create a multichoice single question.
        $mc = test_question_maker::make_a_multichoice_single_question();
        $mc->maxmark = 1;
        $mc->hints = array(
            new question_hint_with_parts('This is the first hint.', false, false),
        );
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
                $this->get_tries_remaining_expectation(2),
                $this->get_no_hint_visible_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('selectone', 'qtype_multichoice'), '/') . '/'));

        // Submit the wrong answer.
        $this->process_submission(array('answer' => $wrongindex, '-submit' => 1));

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
                $this->get_tries_remaining_expectation(1),
                $this->get_contains_hint_expectation('This is the first hint'));

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gradedwrong);
        $this->check_current_mark(0);
        $this->check_current_output(
                $this->get_contains_mc_radio_expectation($wrongindex, false, true),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, false, false),
                $this->get_contains_mc_radio_expectation(($wrongindex + 1) % 3, false, false),
                $this->get_contains_incorrect_expectation(),
                $this->get_no_hint_visible_expectation());
    }

    public function test_interactive_shortanswer_try_to_submit_blank() {

        // Create a short answer question.
        $sa = test_question_maker::make_a_shortanswer_question();
        $sa->hints = array(
            new question_hint('This is the first hint.'),
            new question_hint('This is the second hint.'),
        );
        $this->start_attempt_at_question($sa, 'interactive');

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_no_hint_visible_expectation());

        // Submit blank.
        $this->process_submission(array('-submit' => 1, 'answer' => ''));

        // Verify.
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_contains_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_no_hint_visible_expectation());

        // Now get it wrong.
        $this->process_submission(array('-submit' => 1, 'answer' => 'newt'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_incorrect_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_contains_hint_expectation('This is the first hint'));
        $this->assertEqual('newt',
                $this->quba->get_response_summary($this->qnumber));

        // Try again.
        $this->process_submission(array('-tryagain' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_no_hint_visible_expectation());

        // Now submit blank again.
        $this->process_submission(array('-submit' => 1, 'answer' => ''));

        // Verify.
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_contains_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_no_hint_visible_expectation());

        // Now get it right.
        $this->process_submission(array('-submit' => 1, 'answer' => 'frog'));

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(0.6666667);
        $this->check_current_output(
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_correct_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_no_hint_visible_expectation());
        $this->assertEqual('frog',
                $this->quba->get_response_summary($this->qnumber));
    }

    public function test_interactive_feedback_multichoice_multiple_reset() {

        // Create a multichoice multiple question.
        $mc = test_question_maker::make_a_multichoice_multi_question();
        $mc->hints = array(
            new question_hint_with_parts('This is the first hint.', true, true),
            new question_hint_with_parts('This is the second hint.', true, true),
        );
        $this->start_attempt_at_question($mc, 'interactive', 2);

        $right = array_keys($mc->get_correct_response());
        $wrong = array_diff(array('choice0', 'choice1', 'choice2', 'choice3'), $right);
        $wrong = array_values(array_diff(array('choice0', 'choice1', 'choice2', 'choice3'), $right));

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_question_text_expectation($mc),
                $this->get_contains_mc_checkbox_expectation('choice0', true, false),
                $this->get_contains_mc_checkbox_expectation('choice1', true, false),
                $this->get_contains_mc_checkbox_expectation('choice2', true, false),
                $this->get_contains_mc_checkbox_expectation('choice3', true, false),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_tries_remaining_expectation(3),
                $this->get_no_hint_visible_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('selectmulti', 'qtype_multichoice'), '/') . '/'));

        // Submit an answer with one right, and one wrong.
        $this->process_submission(array($right[0] => 1, $wrong[0] => 1, '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_mc_checkbox_expectation($right[0], false, true),
                $this->get_contains_mc_checkbox_expectation($right[1], false, false),
                $this->get_contains_mc_checkbox_expectation($wrong[0], false, true),
                $this->get_contains_mc_checkbox_expectation($wrong[1], false, false),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_tries_remaining_expectation(2),
                $this->get_contains_hint_expectation('This is the first hint'),
                $this->get_contains_num_parts_correct(1),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . $right[0], '1'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . $right[1]),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . $wrong[0]),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . $wrong[1]));

        // Do try again.
        $this->process_submission(array($right[0] => 1, '-tryagain' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_mc_checkbox_expectation($right[0], true, true),
                $this->get_contains_mc_checkbox_expectation($right[1], true, false),
                $this->get_contains_mc_checkbox_expectation($wrong[0], true, false),
                $this->get_contains_mc_checkbox_expectation($wrong[1], true, false),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(2),
                $this->get_no_hint_visible_expectation());
    }

    public function test_interactive_feedback_match_reset() {

        // Create a multichoice multiple question.
        $m = test_question_maker::make_a_matching_question();
        $m->shufflestems = false;
        $m->hints = array(
            new question_hint_with_parts('This is the first hint.', true, true),
            new question_hint_with_parts('This is the second hint.', true, true),
        );
        $this->start_attempt_at_question($m, 'interactive', 12);

        $choiceorder = $m->get_choice_order();
        $orderforchoice = array_combine(array_values($choiceorder), array_keys($choiceorder));
        $choices = array(0 => get_string('choose') . '...');
        foreach ($choiceorder as $key => $choice) {
            $choices[$key] = $m->choices[$choice];
        }

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_select_expectation('sub0', $choices, null, true),
                $this->get_contains_select_expectation('sub1', $choices, null, true),
                $this->get_contains_select_expectation('sub2', $choices, null, true),
                $this->get_contains_select_expectation('sub3', $choices, null, true),
                $this->get_contains_question_text_expectation($m),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(3),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_no_hint_visible_expectation());

        // Submit an answer with one right, and one wrong.
        $this->process_submission(array('sub0' => $orderforchoice[1],
                'sub1' => $orderforchoice[1], 'sub2' => $orderforchoice[1],
                'sub3' => $orderforchoice[1], '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_select_expectation('sub0', $choices, $orderforchoice[1], false),
                $this->get_contains_select_expectation('sub1', $choices, $orderforchoice[1], false),
                $this->get_contains_select_expectation('sub2', $choices, $orderforchoice[1], false),
                $this->get_contains_select_expectation('sub3', $choices, $orderforchoice[1], false),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_tries_remaining_expectation(2),
                $this->get_contains_hint_expectation('This is the first hint'),
                $this->get_contains_num_parts_correct(2),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'sub0', $orderforchoice[1]),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'sub3', $orderforchoice[1]),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'sub1'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'sub2'));

        // Check that extract responses will return the reset data.
        $prefix = $this->quba->get_field_prefix($this->qnumber);
        $this->assertEqual(array('sub0' => 1),
                $this->quba->extract_responses($this->qnumber, array($prefix . 'sub0' => 1)));

        // Do try again.
        $this->process_submission(array('sub0' => $orderforchoice[1], 'sub3' => $orderforchoice[1], '-tryagain' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_select_expectation('sub0', $choices, $orderforchoice[1], true),
                $this->get_contains_select_expectation('sub1', $choices, null, true),
                $this->get_contains_select_expectation('sub2', $choices, null, true),
                $this->get_contains_select_expectation('sub3', $choices, $orderforchoice[1], true),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(2),
                $this->get_no_hint_visible_expectation());

        // Submit an answer with one right, and one wrong.
        $this->process_submission(array('sub0' => $orderforchoice[1],
                'sub1' => $orderforchoice[2], 'sub2' => $orderforchoice[2],
                'sub3' => $orderforchoice[1], '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(8);
        $this->check_current_output(
                $this->get_contains_select_expectation('sub0', $choices, $orderforchoice[1], false),
                $this->get_contains_select_expectation('sub1', $choices, $orderforchoice[2], false),
                $this->get_contains_select_expectation('sub2', $choices, $orderforchoice[2], false),
                $this->get_contains_select_expectation('sub3', $choices, $orderforchoice[1], false),
                $this->get_contains_submit_button_expectation(false),
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_contains_correct_expectation());
    }
}
