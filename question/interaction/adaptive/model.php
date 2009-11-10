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
 * @package qim_adaptive
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Question interaction model for deferred feedback.
 *
 * The student enters their response during the attempt, and it is saved. Later,
 * when the whole attempt is finished, their answer is graded.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qim_adaptive extends question_interaction_model {
    public function get_expected_data() {
        if (question_state::is_active($this->qa->get_state())) {
            return array('submit' => PARAM_BOOL);
        }
    }

    public function adjust_display_options(question_display_options $options) {
        if (question_state::is_finished($this->qa->get_state())) {
            $options->readonly = true;
        } else {
            $options->hide_all_feedback();
            if ($this->qa->get_last_im_var('_try')) {
                $options->feedback = true;
            }
        }
    }

    public function process_action(question_attempt_step $pendingstep) {
        if ($pendingstep->has_im_var('comment')) {
            return $this->process_comment($pendingstep);
        } else if ($pendingstep->has_im_var('finish')) {
            return $this->process_finish($pendingstep);
        } else if ($pendingstep->has_im_var('submit')) {
            return $this->process_submit($pendingstep);
        } else {
            return $this->process_save($pendingstep);
        }
    }

    public function process_save(question_attempt_step $pendingstep) {
        $status = parent::process_save($pendingstep);
        $prevgrade = $this->qa->get_fraction();
        if (!is_null($prevgrade)) {
            $pendingstep->set_fraction($prevgrade);
        }
        $pendingstep->set_state(question_state::INCOMPLETE);
        return $status;
    }

    public function process_submit(question_attempt_step $pendingstep) {
        $status = $this->process_save($pendingstep);

        $response = $pendingstep->get_qt_data();
        if (!$this->question->is_gradable_response($response)) {
            return $status;
        }

        $prevtries = $this->qa->get_last_im_var('_try', 0);
        $prevbest = $pendingstep->get_fraction();
        if (is_null($prevbest)) {
            $prevbest = 0;
        }

        list($fraction, $state) = $this->question->grade_response($response);

        $pendingstep->set_fraction(max($prevbest, $fraction - $this->question->penalty * $prevtries));
        if ($state == question_state::GRADED_CORRECT) {
            $pendingstep->set_state(question_state::COMPLETE);
        } else {
            $pendingstep->set_state(question_state::INCOMPLETE);
        }
        $pendingstep->set_im_var('_try', $prevtries + 1);
        $pendingstep->set_im_var('_rawfraction', $fraction);

        return question_attempt::KEEP;
    }

    public function process_finish(question_attempt_step $pendingstep) {
        if (question_state::is_finished($this->qa->get_state())) {
            return question_attempt::DISCARD;
        }

        $laststep = $this->qa->get_last_step();
        $response = $laststep->get_qt_data();
        if (!$this->question->is_gradable_response($response)) {
            $pendingstep->set_state(question_state::GAVE_UP);
            return question_attempt::KEEP;
        }

        $prevtries = $this->qa->get_last_im_var('_try', 0);
        $prevbest = $pendingstep->get_fraction();
        if (is_null($prevbest)) {
            $prevbest = 0;
        }

        if ($laststep->has_im_var('_try')) {
            // Last answer was graded, we want to regrade it. Otherwise the answer
            // has changed, and we are grading a new try.
            $prevtries -= 1;
        }

        list($fraction, $state) = $this->question->grade_response($response);

        $pendingstep->set_fraction(max($prevbest, $fraction - $this->question->penalty * $prevtries));
        $pendingstep->set_state($state);
        $pendingstep->set_im_var('_try', $prevtries + 1);
        $pendingstep->set_im_var('_rawfraction', $fraction);
        return question_attempt::KEEP;
    }
}
