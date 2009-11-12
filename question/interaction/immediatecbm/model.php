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
 * time for immediate feedback, with certainty based marking.
 *
 * @package qim_immediatecbm
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../immediatefeedback/model.php');

/**
 * Question interaction model for immediate feedback with CBM.
 *
 * Each question has a submit button next to it along with some radio buttons
 * to input a certainly, that is, how sure they are that they are right.
 * The student can submit their answer at any time for immediate feedback.
 * Once the qustion is submitted, it is not possible for the student to change
 * their answer any more. The student's degree of certainly affects their score.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qim_immediatecbm extends qim_immediatefeedback {
    const IS_ARCHETYPAL = true;

    public function get_min_fraction() {
        return question_cbm::adjust_fraction(parent::get_min_fraction(), question_cbm::HIGH);
    }

    public function get_expected_data() {
        if (question_state::is_active($this->qa->get_state())) {
            return array(
                'submit' => PARAM_BOOL,
                'certainty' => PARAM_INT,
            );
        }
        return array();
    }

    protected function is_same_response($pendingstep) {
        return parent::is_same_response($pendingstep) &&
                $this->qa->get_last_im_var('certainty') == $pendingstep->get_im_var('certainty');
    }

    protected function is_complete_response($pendingstep) {
        return parent::is_complete_response($pendingstep) && $pendingstep->has_im_var('certainty');
    }

    public function process_submit(question_attempt_step $pendingstep) {
        if (question_state::is_finished($this->qa->get_state())) {
            return question_attempt::DISCARD;
        }

        if (!$this->is_complete_response($pendingstep)) {
            $pendingstep->set_state(question_state::INCOMPLETE);

        } else {
            list($fraction, $state) = $this->question->grade_response($pendingstep->get_qt_data());
            $pendingstep->set_fraction(question_cbm::adjust_fraction($fraction,
                    $pendingstep->get_im_var('certainty')));
            $pendingstep->set_state($state);
        }
        return question_attempt::KEEP;
    }

    public function process_finish(question_attempt_step $pendingstep) {
        if (question_state::is_finished($this->qa->get_state())) {
            return question_attempt::DISCARD;
        }

        $laststep = $this->qa->get_last_step();
        if (!$this->question->is_gradable_response($laststep->get_qt_data())) {
            $pendingstep->set_state(question_state::GAVE_UP);

        } else {
            list($fraction, $state) = $this->question->grade_response($laststep->get_qt_data());

            if ($laststep->has_im_var('certainty')) {
                $certainty = $laststep->get_im_var('certainty');
            } else {
                $certainty = self::LOW;
                $pendingstep->set_im_var('_assumedcertainty', $certainty);
            }

            $pendingstep->set_fraction(question_cbm::adjust_fraction($fraction, $certainty));
            $pendingstep->set_state($state);
        }
        return question_attempt::KEEP;
    }
}
