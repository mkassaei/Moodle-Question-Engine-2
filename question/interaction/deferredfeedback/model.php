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
 * Question iteraction model for the case when the student's answer is just
 * saved until they submit the whole attempt, and then it is graded.
 *
 * @package qim_delayedfeedback
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Question interaction model for deferred feedback.
 *
 * The student enters their feedback during the attempt, and it is saved. Later,
 * when the whole attempt is finished, their answer is graded.
 *
 * @copyright © 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_deferredfeedback_model extends question_interaction_model {
    public function get_min_fraction() {
        return $this->question->get_min_fraction();
    }

    public function process_action(question_attempt_step $pendingstep) {
        if ($pendingstep->has_im_var('comment')) {
            return $this->process_comment($pendingstep);
        } else if ($pendingstep->has_im_var('finish')) {
            return $this->process_finish($pendingstep);
        } else {
            return $this->process_save($pendingstep);
        }
    }

    public function process_save(question_attempt_step $pendingstep) {
        if (!question_state::is_active($this->qa->get_state())) {
            throw new Exception('Question is already closed, cannot process_actions.');
        }
        if ($this->question->is_same_response(
                $this->qa->get_last_step()->get_qt_data(), $pendingstep->get_qt_data())) {
            return question_attempt::DISCARD;
        }

        if ($this->question->is_complete_response($pendingstep->get_qt_data())) {
            $pendingstep->set_state(question_state::COMPLETE);
        } else {
            $pendingstep->set_state(question_state::INCOMPLETE);
        }
        return question_attempt::KEEP;
    }

    public function process_finish(question_attempt_step $pendingstep) {
        if (question_state::is_finished($this->qa->get_state())) {
            return question_attempt::DISCARD;
        }

        $response = $this->qa->get_last_step()->get_qt_data();
        if (!$this->question->is_gradable_response($response)) {
            $pendingstep->set_state(question_state::GAVE_UP);
        } else {
            list($fraction, $state) = $this->question->grade_response(
                    $this->question, $response);
            $pendingstep->set_fraction($fraction);
            $pendingstep->set_state($state);
        }
        return question_attempt::KEEP;
    }
}
