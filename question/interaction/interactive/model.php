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
 * @package qim_interactive
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Question interaction model for the interactive model.
 *
 * Each question has a submit button next to it which the student can use to
 * submit it. Once the qustion is submitted, it is not possible for the
 * student to change their answer any more, but the student gets full feedback
 * straight away.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qim_interactive extends question_interaction_model_with_save {
    const IS_ARCHETYPAL = true;

    /**
     * Special value used for {@link question_display_options::$readonly when
     * we are showing the try again button to the student during an attempt.
     * The particular number was chosen randomly. PHP will treat it the same
     * as true, but in the renderer we reconginse it display the try again
     * button enabled even though the rest of the question is disabled..
     * @var integer
     */
    const READONLY_EXCEPT_TRY_AGAIN = 23485299;

    public function required_question_definition_type() {
        return 'question_automatically_gradable';
    }

    /**
     * @return boolean are we are currently in the try_again state.
     */
    protected function is_try_again_state() {
        $laststep = $this->qa->get_last_step();
        return $this->qa->get_state()->is_active() &&
                $laststep->has_im_var('submit') && $laststep->has_im_var('_triesleft');
    }

    public function adjust_display_options(question_display_options $options) {
        $specificfeedback = $options->feedback;
        parent::adjust_display_options($options);
        if ($this->is_try_again_state()) {
            if (!$options->readonly) {
                $options->readonly = self::READONLY_EXCEPT_TRY_AGAIN;
            }
            $hint = $this->get_applicable_hint();
            if ($hint && !empty($hint->clearwrong)) {
                $options->clearwrong = true;
            }
            $options->feedback = $specificfeedback;
        }
    }

    public function get_applicable_hint() {
        if (!$this->is_try_again_state()) {
            return null;
        }
        return $this->question->hints[count($this->question->hints) -
                $this->qa->get_last_im_var('_triesleft')];
    }

    public function get_expected_data() {
        if ($this->is_try_again_state()) {
            return array(
                'tryagain' => PARAM_BOOL,
            );
        } else if ($this->qa->get_state()->is_active()) {
            return array(
                'submit' => PARAM_BOOL,
            );
        }
        return array();
    }

    public function init_first_step(question_attempt_step $step) {
        parent::init_first_step($step);
        $step->set_im_var('_triesleft', count($this->question->hints) + 1);
    }

    public function process_action(question_attempt_step $pendingstep) {
        if ($pendingstep->has_im_var('finish')) {
            return $this->process_finish($pendingstep);
        }
        if ($this->is_try_again_state()) {
            if ($pendingstep->has_im_var('tryagain')) {
                return $this->process_try_again($pendingstep);
            } else {
                return question_attempt::DISCARD;
            }
        } else {
            if ($pendingstep->has_im_var('comment')) {
                return $this->process_comment($pendingstep);
            } else if ($pendingstep->has_im_var('submit')) {
                return $this->process_submit($pendingstep);
            } else {
                return $this->process_save($pendingstep);
            }
        }
    }

    public function process_try_again(question_attempt_step $pendingstep) {
        $pendingstep->set_state(question_state::$todo);
        return question_attempt::KEEP;
    }

    public function process_submit(question_attempt_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        }

        if (!$this->is_complete_response($pendingstep)) {
            $pendingstep->set_state(question_state::$invalid);

        } else {
            $triesleft = $this->qa->get_last_im_var('_triesleft');
            list($fraction, $state) = $this->question->grade_response($pendingstep->get_qt_data());
            if ($state == question_state::$gradedright || $triesleft == 1) {
                $pendingstep->set_state($state);
                $pendingstep->set_fraction($this->adjust_fraction($fraction));

            } else {
                $pendingstep->set_im_var('_triesleft', $triesleft - 1);
                $pendingstep->set_state(question_state::$todo);
            }
        }
        return question_attempt::KEEP;
    }

    protected function adjust_fraction($fraction) {
        $totaltries = $this->qa->get_step(0)->get_im_var('_triesleft');
        $triesleft = $this->qa->get_last_im_var('_triesleft');

        $fraction -= ($totaltries - $triesleft) / $totaltries;
        $fraction = max($fraction, 0);
        return $fraction;
    }

    public function process_finish(question_attempt_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        }

        $response = $this->qa->get_last_qt_data();
        if (!$this->question->is_gradable_response($response)) {
            $pendingstep->set_state(question_state::$gaveup);

        } else {
            list($fraction, $state) = $this->question->grade_response($response);
            $pendingstep->set_fraction($this->adjust_fraction($fraction));
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
