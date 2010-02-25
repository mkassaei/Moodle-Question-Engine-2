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
 * Question iteraction model where the student can submit questions one at a
 * time for immediate feedback.
 *
 * @package qim_immediatefeedback
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Question interaction model for immediate feedback.
 *
 * Each question has a submit button next to it which the student can use to
 * submit it. Once the qustion is submitted, it is not possible for the
 * student to change their answer any more, but the student gets full feedback
 * straight away.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qim_immediatefeedback extends question_interaction_model_with_save {
    const IS_ARCHETYPAL = true;

    public function required_question_definition_type() {
        return 'question_automatically_gradable';
    }

    public function get_min_fraction() {
        return $this->question->get_min_fraction();
    }

    public function get_expected_data() {
        if ($this->qa->get_state()->is_active()) {
            return array(
                'submit' => PARAM_BOOL,
            );
        }
        return array();
    }

    public function get_right_answer_summary() {
        return $this->question->get_right_answer_summary();
    }

    public function process_action(question_attempt_step $pendingstep) {
        if ($pendingstep->has_im_var('comment')) {
            return $this->process_comment($pendingstep);
        } else if ($pendingstep->has_im_var('submit')) {
            return $this->process_submit($pendingstep);
        } else if ($pendingstep->has_im_var('finish')) {
            return $this->process_finish($pendingstep);
        } else {
            return $this->process_save($pendingstep);
        }
    }

    public function process_submit(question_attempt_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        }

        if (!$this->is_complete_response($pendingstep)) {
            $pendingstep->set_state(question_state::$invalid);

        } else {
            list($fraction, $state) = $this->question->grade_response($pendingstep->get_qt_data());
            $pendingstep->set_fraction($fraction);
            $pendingstep->set_state($state);
        }
        return question_attempt::KEEP;
    }

    public function process_finish(question_attempt_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        }

        $response = $this->qa->get_last_step()->get_qt_data();
        if (!$this->question->is_gradable_response($response)) {
            $pendingstep->set_state(question_state::$gaveup);

        } else {
            list($fraction, $state) = $this->question->grade_response($response);
            $pendingstep->set_fraction($fraction);
            $pendingstep->set_state($state);
        }
        return question_attempt::KEEP;
    }

    public function process_save(question_attempt_step $pendingstep) {
        $status = parent::process_save($pendingstep);
        if ($status == question_attempt::KEEP && $pendingstep->get_state() == question_state::$complete) {
            $pendingstep->set_state(question_state::$todo);
        }
        return $status;
    }
}
