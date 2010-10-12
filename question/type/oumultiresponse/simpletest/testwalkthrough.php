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
 * This file contains tests that walks a OU multiple response question through
 * various interaction models.
 *
 * @package qtype_oumultiresponse
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');
require_once($CFG->dirroot . '/question/type/oumultiresponse/simpletest/helper.php');


class qtype_oumultiresponse_walkthrough_test extends qbehaviour_walkthrough_test_base {

    public function test_interactive_behaviour() {

        // Create a multichoice single question.
        $mc = qtype_oumultiresponse_test_helper::make_an_oumultiresponse_two_of_four();
        $mc->shufflechoices = false;
        $this->start_attempt_at_question($mc, 'interactive', 3);

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

        // Save the wrong answer.
        $this->process_submission(array('choice1' => '1', 'choice3' => '1'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_question_text_expectation($mc),
                $this->get_contains_mc_checkbox_expectation('choice0', true, false),
                $this->get_contains_mc_checkbox_expectation('choice1', true, true),
                $this->get_contains_mc_checkbox_expectation('choice2', true, false),
                $this->get_contains_mc_checkbox_expectation('choice3', true, true),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_tries_remaining_expectation(3),
                $this->get_no_hint_visible_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('selectmulti', 'qtype_multichoice'), '/') . '/'));

        // Submit the wrong answer.
        $this->process_submission(array('choice1' => '1', 'choice3' => '1', '-submit' => '1'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_mc_checkbox_expectation('choice0', false, false),
                $this->get_contains_mc_checkbox_expectation('choice1', false, true),
                $this->get_contains_mc_checkbox_expectation('choice2', false, false),
                $this->get_contains_mc_checkbox_expectation('choice3', false, true),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('notcomplete', 'qbehaviour_interactive')) . '/'),
                $this->get_contains_hint_expectation('Hint 1'),
                $this->get_contains_num_parts_correct(0),
                $this->get_contains_standard_incorrect_combined_feedback_expectation(),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice0'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice1'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice2'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice3'));

        // Do try again.
        $this->process_submission(array('-tryagain' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_question_text_expectation($mc),
                $this->get_contains_mc_checkbox_expectation('choice0', true, false),
                $this->get_contains_mc_checkbox_expectation('choice1', true, true),
                $this->get_contains_mc_checkbox_expectation('choice2', true, false),
                $this->get_contains_mc_checkbox_expectation('choice3', true, true),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_tries_remaining_expectation(2),
                $this->get_no_hint_visible_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('selectmulti', 'qtype_multichoice'), '/') . '/'));

        // Submit a partially right answer.
        $this->process_submission(array('choice0' => '1', 'choice3' => '1', '-submit' => '1'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_mc_checkbox_expectation('choice0', false, true),
                $this->get_contains_mc_checkbox_expectation('choice1', false, false),
                $this->get_contains_mc_checkbox_expectation('choice2', false, false),
                $this->get_contains_mc_checkbox_expectation('choice3', false, true),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('notcomplete', 'qbehaviour_interactive')) . '/'),
                $this->get_contains_hint_expectation('Hint 2'),
                $this->get_contains_num_parts_correct(1),
                $this->get_contains_standard_partiallycorrect_combined_feedback_expectation(),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice0', '1'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice1'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice2'),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice3', '0'));

        // Do try again.
        $this->process_submission(array('choice0' => '1', '-tryagain' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_question_text_expectation($mc),
                $this->get_contains_mc_checkbox_expectation('choice0', true, true),
                $this->get_contains_mc_checkbox_expectation('choice1', true, false),
                $this->get_contains_mc_checkbox_expectation('choice2', true, false),
                $this->get_contains_mc_checkbox_expectation('choice3', true, false),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_tries_remaining_expectation(1),
                $this->get_no_hint_visible_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('selectmulti', 'qtype_multichoice'), '/') . '/'));

        // Submit the right answer.
        $this->process_submission(array('choice0' => '1', 'choice2' => '1', '-submit' => '1'));

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(1.5);
        $this->check_current_output(
                $this->get_contains_mc_checkbox_expectation('choice0', false, true),
                $this->get_contains_mc_checkbox_expectation('choice1', false, false),
                $this->get_contains_mc_checkbox_expectation('choice2', false, true),
                $this->get_contains_mc_checkbox_expectation('choice3', false, false),
                $this->get_contains_submit_button_expectation(false),
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_contains_correct_expectation(),
                $this->get_contains_standard_correct_combined_feedback_expectation());
    }

    public function test_interactive_behaviour2() {

        // Create a multichoice single question.
        $mc = qtype_oumultiresponse_test_helper::make_an_oumultiresponse_two_of_four();
        $mc->hints = array(
            new qtype_oumultiresponse_hint('Hint 1', true, true, true),
            new qtype_oumultiresponse_hint('Hint 2', true, true, true),
        );
        $mc->shufflechoices = false;
        $this->start_attempt_at_question($mc, 'interactive', 3);

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

        // Submit the wrong answer with too manu options selected.
        $this->process_submission(array('choice1' => '1', 'choice2' => '1', 'choice3' => '1', '-submit' => '1'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_mc_checkbox_expectation('choice0', false, false),
                $this->get_contains_mc_checkbox_expectation('choice1', false, true),
                $this->get_contains_mc_checkbox_expectation('choice2', false, true),
                $this->get_contains_mc_checkbox_expectation('choice3', false, true),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('notcomplete', 'qbehaviour_interactive')) . '/'),
                $this->get_contains_hint_expectation('Hint 1'),
                new PatternExpectation('/' . preg_quote(get_string('toomanyselected', 'qtype_multichoice')) . '/'),
                new NoPatternExpectation('/Three is odd/'),
                $this->get_contains_standard_partiallycorrect_combined_feedback_expectation(),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice0'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice1'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice2'),
                $this->get_does_not_contain_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice3'));
    }

    public function test_interactive_clear_wrong() {

        // Create a multichoice single question.
        $mc = qtype_oumultiresponse_test_helper::make_an_oumultiresponse_two_of_four();
        $mc->hints = array(
            new qtype_oumultiresponse_hint('Hint 1', true, true, true),
            new qtype_oumultiresponse_hint('Hint 2', true, true, true),
        );
        $mc->shufflechoices = false;
        $this->start_attempt_at_question($mc, 'interactive', 3);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
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

        // Submit a wrong answer.
        $this->process_submission(array('choice1' => '1', 'choice3' => '1', '-submit' => '1'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_mc_checkbox_expectation('choice0', false, false),
                $this->get_contains_mc_checkbox_expectation('choice1', false, true),
                $this->get_contains_mc_checkbox_expectation('choice2', false, false),
                $this->get_contains_mc_checkbox_expectation('choice3', false, true),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_contains_num_parts_correct(0),
                new PatternExpectation('/' . preg_quote(get_string('notcomplete', 'qbehaviour_interactive')) . '/'),
                $this->get_contains_hint_expectation('Hint 1'),
                $this->get_contains_standard_incorrect_combined_feedback_expectation(),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice1', '0'),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice3', '0'));

        // Try again.
        $this->process_submission(array('choice1' => '0', 'choice3' => '0', '-tryagain' => '1'));

        // Vreify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_question_text_expectation($mc),
                $this->get_contains_mc_checkbox_expectation('choice0', true, false),
                $this->get_contains_mc_checkbox_expectation('choice1', true, false),
                $this->get_contains_mc_checkbox_expectation('choice2', true, false),
                $this->get_contains_mc_checkbox_expectation('choice3', true, false),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_tries_remaining_expectation(2),
                $this->get_no_hint_visible_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('selectmulti', 'qtype_multichoice'), '/') . '/'));

        // Submit a partially right answer.
        $this->process_submission(array('choice0' => '1', 'choice3' => '1', '-submit' => '1'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_mc_checkbox_expectation('choice0', false, true),
                $this->get_contains_mc_checkbox_expectation('choice1', false, false),
                $this->get_contains_mc_checkbox_expectation('choice2', false, false),
                $this->get_contains_mc_checkbox_expectation('choice3', false, true),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_contains_num_parts_correct(1),
                new PatternExpectation('/' . preg_quote(get_string('notcomplete', 'qbehaviour_interactive')) . '/'),
                $this->get_contains_hint_expectation('Hint 2'),
                $this->get_contains_standard_partiallycorrect_combined_feedback_expectation(),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice0', '1'),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->slot) . 'choice3', '0'));

        // Try again.
        $this->process_submission(array('choice0' => '1', 'choice3' => '0', '-tryagain' => '1'));

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_contains_question_text_expectation($mc),
                $this->get_contains_mc_checkbox_expectation('choice0', true, true),
                $this->get_contains_mc_checkbox_expectation('choice1', true, false),
                $this->get_contains_mc_checkbox_expectation('choice2', true, false),
                $this->get_contains_mc_checkbox_expectation('choice3', true, false),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_num_parts_correct(),
                $this->get_tries_remaining_expectation(1),
                $this->get_no_hint_visible_expectation(),
                new PatternExpectation('/' . preg_quote(get_string('selectmulti', 'qtype_multichoice'), '/') . '/'));
    }
}
