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
 * Question iteraction model that is like the deferred feedback model, but with
 * certainly based marking. That is, in addition to the other controls, there are
 * where the student can indicate how certain they are that their answer is right.
 *
 * @package qim_deferredcbm
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../deferredfeedback/model.php');

/**
 * Question interaction model for deferred feedback with certainty based marking.
 *
 * The student enters their response during the attempt, along with a certainty,
 * that is, how sure they are that they are right, and it is saved. Later,
 * when the whole attempt is finished, their answer is graded. Their degree
 * of certainty affects their score.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qim_deferredcbm extends qim_deferredfeedback {
    const IS_ARCHETYPAL = true;

    public function get_min_fraction() {
        return question_cbm::adjust_fraction(parent::get_min_fraction(), question_cbm::HIGH);
    }

    public function get_expected_data() {
        if ($this->qa->get_state()->is_active()) {
            return array('certainty' => PARAM_INT);
        }
        return array();
    }

    public function get_correct_response() {
        if ($this->qa->get_state()->is_active()) {
            return array('certainty' => question_cbm::HIGH);
        }
        return array();
    }

    protected function get_our_resume_data() {
        $lastcertainty = $this->qa->get_last_im_var('certainty');
        if ($lastcertainty) {
            return array('!certainty' => $lastcertainty);
        } else {
            return array();
        }
    }

    protected function is_same_response($pendingstep) {
        return parent::is_same_response($pendingstep) &&
                $this->qa->get_last_im_var('certainty') == $pendingstep->get_im_var('certainty');
    }

    protected function is_complete_response($pendingstep) {
        return parent::is_complete_response($pendingstep) && $pendingstep->has_im_var('certainty');
    }

    protected function get_certainty() {

    }

    public function process_finish(question_attempt_step $pendingstep) {
        $status = parent::process_finish($pendingstep);
        if ($status == question_attempt::KEEP) {
            $fraction = $pendingstep->get_fraction();
            if ($this->qa->get_last_step()->has_im_var('certainty')) {
                $certainty = $this->qa->get_last_step()->get_im_var('certainty');
            } else {
                $certainty = question_cbm::default_certainty();
                $pendingstep->set_im_var('_assumedcertainty', $certainty);
            }
            if (!is_null($fraction)) {
                $pendingstep->set_im_var('_rawfraction', $fraction);
                $pendingstep->set_fraction(question_cbm::adjust_fraction($fraction, $certainty));
            }
        }
        return $status;
    }
}
