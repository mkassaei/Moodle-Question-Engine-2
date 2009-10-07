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
class question_deferredfeedback_model extends question_interaction_model_base {
    public function process_action(question_attempt $qa, array $submitteddata) {
        if (array_key_exists('!comment', $submitteddata)) {
            $this->process_comment($qa, $submitteddata);
        } else if (array_key_exists('!finish', $submitteddata)) {
            $this->process_finish($qa, $submitteddata);
        } else {
            $this->process_save($qa, $submitteddata);
        }
    }

    public function process_save(question_attempt $qa, array $submitteddata) {
        $currentstate = $qa->get_last_step();

        if (!question_state::is_active($currentstate->get_state())) {
            throw new Exception('Question is already closed, cannot process_actions.');
        }
        if ($qa->get_qtype()->is_same_response(
                $currentstate->get_response(), $submitteddata)) {
            return;
        }

        $newstate = new question_attempt_step();
        $newstate->set_response($submitteddata);
        if ($qa->get_qtype()->is_complete_response($submitteddata)) {
            $newstate->set_state(question_state::COMPLETE);
        } else {
            $newstate->set_state(question_state::INCOMPLETE);
        }
        $qa->add_state($newstate);
    }

    public function process_finish(question_attempt $qa, array $submitteddata) {
        $currentstate = $qa->get_last_step();

        if (question_state::is_finished($currentstate->get_state())) {
            return;
        }

        $newstate = new question_attempt_step();
        $newstate->set_response(array('!submit' => 1));

        if (!$qa->get_qtype()->is_gradable_response($currentstate->get_response())) {
            $newstate->set_state(question_state::GAVE_UP);
        } else {
            list($grade, $state) = $qa->get_qtype()->
                    grade_response($currentstate->get_response());
            $newstate->set_grade($grade);
            $newstate->set_state($state);
        }
        $qa->add_state($newstate);
    }
}
