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


class qtype_ddwtos_walkthrough_test extends qbehaviour_walkthrough_test_base {

    protected function get_contains_drop_box_expectation($place, $group, $readonly) {
        $qa = $this->quba->get_question_attempt($this->qnumber);

        $readonlyclass = '';
        if ($readonly) {
            $readonlyclass = ' readonly';
        }

        return new ContainsTagWithAttributes('span', array(
            'id' => $qa->get_qt_field_name($place . '_' . $group),
            'class' => 'slot group' . $group . $readonlyclass,
            'tabindex' => 0
        ));
    }

    public function test_interactive_behaviour() {

        // Create a multichoice single question.
        $dd = qtype_ddwtos_test_helper::make_a_ddwtos_question();
        $dd->hints = array(
            new question_hint_with_parts('This is the first hint.', false, false),
            new question_hint_with_parts('This is the second hint.', true, true),
        );
        $dd->shufflechoices = false;
        $this->start_attempt_at_question($dd, 'interactive', 3);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_drop_box_expectation('p1', 1, false),
                $this->get_contains_drop_box_expectation('p2', 2, false),
                $this->get_contains_drop_box_expectation('p3', 3, false),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'p1', ''),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'p2', ''),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'p3', ''),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(3),
                $this->get_no_hint_visible_expectation());

        // Save the wrong answer.
        $this->process_submission(array('p1' => '2', 'p2' => '2', 'p3' => '2'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_drop_box_expectation('p1', 1, false),
                $this->get_contains_drop_box_expectation('p2', 2, false),
                $this->get_contains_drop_box_expectation('p3', 3, false),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'p1', '2'),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'p2', '2'),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'p3', '2'),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(3),
                $this->get_no_hint_visible_expectation());

        // Submit the wrong answer.
        $this->process_submission(array('p1' => '2', 'p2' => '2', 'p3' => '2', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_drop_box_expectation('p1', 1, true),
                $this->get_contains_drop_box_expectation('p2', 2, true),
                $this->get_contains_drop_box_expectation('p3', 3, true),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_try_again_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_tries_remaining_expectation(2),
                $this->get_contains_hint_expectation('This is the first hint'));

        // Do try again.
        $this->process_submission(array('-tryagain' => 1));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_drop_box_expectation('p1', 1, false),
                $this->get_contains_drop_box_expectation('p2', 2, false),
                $this->get_contains_drop_box_expectation('p3', 3, false),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'p1', '2'),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'p2', '2'),
                $this->get_contains_hidden_expectation($this->quba->get_field_prefix($this->qnumber) . 'p3', '2'),
                $this->get_contains_submit_button_expectation(true),
                $this->get_does_not_contain_correctness_expectation(),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_tries_remaining_expectation(2),
                $this->get_no_hint_visible_expectation());

        // Submit the right answer.
        $this->process_submission(array('p1' => '1', 'p2' => '1', 'p3' => '1', '-submit' => 1));

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(2);
        $this->check_current_output(
                $this->get_contains_drop_box_expectation('p1', 1, true),
                $this->get_contains_drop_box_expectation('p2', 2, true),
                $this->get_contains_drop_box_expectation('p3', 3, true),
                $this->get_contains_submit_button_expectation(false),
                $this->get_contains_correct_expectation(),
                $this->get_no_hint_visible_expectation());

        // Check regrading does not mess anything up.
        $this->quba->regrade_all_questions();

        // Verify.
        $this->check_current_state(question_state::$gradedright);
        $this->check_current_mark(2);
    }
}
