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
 * behaviour.
 *
 * @package qtype_ddwtos
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');
require_once($CFG->dirroot . '/question/type/ddwtos/simpletest/helper.php');


class qtype_match_walkthrough_test extends qbehaviour_walkthrough_test_base {

    public function test_deferred_feedback_unanswered() {

        // Create a matching question.
        $m = test_question_maker::make_a_matching_question();
        $m->shufflestems = false;
        $this->start_attempt_at_question($m, 'deferredfeedback', 4);

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
                $this->get_does_not_contain_feedback_expectation());
        $this->check_step_count(1);

        // Save a blank response.
        $this->process_submission(array('sub0' => '0', 'sub1' => '0',
                'sub2' => '0', 'sub3' => '0'));
        

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_select_expectation('sub0', $choices, null, true),
                $this->get_contains_select_expectation('sub1', $choices, null, true),
                $this->get_contains_select_expectation('sub2', $choices, null, true),
                $this->get_contains_select_expectation('sub3', $choices, null, true),
                $this->get_contains_question_text_expectation($m),
                $this->get_does_not_contain_feedback_expectation());
        $this->check_step_count(1);

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gaveup);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_select_expectation('sub0', $choices, null, false),
                $this->get_contains_select_expectation('sub1', $choices, null, false),
                $this->get_contains_select_expectation('sub2', $choices, null, false),
                $this->get_contains_select_expectation('sub3', $choices, null, false));
    }

    public function test_deferred_feedback_partial_answer() {

        // Create a matching question.
        $m = test_question_maker::make_a_matching_question();
        $m->shufflestems = false;
        $this->start_attempt_at_question($m, 'deferredfeedback', 4);

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
                $this->get_does_not_contain_feedback_expectation());

        // Save a partial response.
        $this->process_submission(array('sub0' => $orderforchoice[1],
                'sub1' => $orderforchoice[2], 'sub2' => '0', 'sub3' => '0'));
        

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_select_expectation('sub0', $choices, 1, true),
                $this->get_contains_select_expectation('sub1', $choices, 2, true),
                $this->get_contains_select_expectation('sub2', $choices, null, true),
                $this->get_contains_select_expectation('sub3', $choices, null, true),
                $this->get_contains_question_text_expectation($m),
                $this->get_does_not_contain_feedback_expectation());

        // Finish the attempt.
        $this->quba->finish_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gradedpartial);
        $this->check_current_mark(2);
        $this->check_current_output(
                $this->get_contains_select_expectation('sub0', $choices, 1, false),
                $this->get_contains_select_expectation('sub1', $choices, 2, false),
                $this->get_contains_select_expectation('sub2', $choices, null, false),
                $this->get_contains_select_expectation('sub3', $choices, null, false),
                $this->get_contains_partcorrect_expectation());
    }
}
