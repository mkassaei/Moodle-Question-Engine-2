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
 * Defines the quetsion interaction model base class
 *
 * @package moodlecore
 * @subpackage questioninteractions
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * The base class for question interaction models.
 *
 * A question interaction model controls the flow of actions a student can
 * take as they work through a question, and later, as a teacher manually grades it.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_interaction_model {
    const IS_ARCHETYPAL = false;

    /** @var question_attempt */
    protected $qa;
    /** @var question_definition */
    protected $question;

    public function __construct(question_attempt $qa) {
        $this->qa = $qa;
        $this->question = $qa->get_question();
        $requiredclass = $this->required_question_definition_class();
        if (!$this->question instanceof $requiredclass) {
            throw new Exception('This interaction model (' . $this->get_name() .
                    ') cannot work with this question (' . get_class($this->question) . ')');
        }
    }

    public function get_name() {
        return substr(get_class($this), 4);
    }

    public function get_renderer() {
        list($ignored, $type) = explode('_', get_class($this), 3);
        return renderer_factory::get_renderer('qim_' . $type);
    }

    /**
     * Most interaction models can only work with a particular subclass of
     * question_definition. This method lets the interaction model document
     * that. The type of question passed to the constructor is then checked
     * against this.
     * @return string class name.
     */
    public abstract function required_question_definition_class();

    public function adjust_display_options(question_display_options $options) {
        if (question_state::is_finished($this->qa->get_state())) {
            $options->readonly = true;
        } else {
            $options->hide_all_feedback();
        }
    }

    public function render(question_display_options $options, $number,
            core_question_renderer $qoutput, qtype_renderer $qtoutput) {
        $qimoutput = $this->get_renderer();
        $options = clone($options);
        $this->adjust_display_options($options);
        return $qoutput->question($this->qa, $qimoutput, $qtoutput, $options, $number);
    }

    public function get_min_fraction() {
        return 0;
    }

    public function init_first_step(question_attempt_step $step) {
        $this->question->init_first_step($step);
    }

    /**
     * Return an array of the interaction model variables that could be submitted
     * as part of a question of this type, with their types, so they can be
     * properly cleaned.
     * @return array variable name => PARAM_... constant.
     */
    public function get_expected_data() {
        return array();
    }

    public abstract function process_action(question_attempt_step $pendingstep);

    protected function is_same_response($pendingstep) {
        return $this->question->is_same_response(
                $this->qa->get_last_step()->get_qt_data(), $pendingstep->get_qt_data());
    }

    protected function is_complete_response($pendingstep) {
        return $this->question->is_complete_response($pendingstep->get_qt_data());
    }

    public function process_save(question_attempt_step $pendingstep) {
        if (!question_state::is_active($this->qa->get_state())) {
            throw new Exception('Question is already closed, cannot process_actions.');
        }
        if ($this->is_same_response($pendingstep)) {
            return question_attempt::DISCARD;
        }

        if ($this->is_complete_response($pendingstep)) {
            $pendingstep->set_state(question_state::COMPLETE);
        } else {
            $pendingstep->set_state(question_state::INCOMPLETE);
        }
        return question_attempt::KEEP;
    }

    public function process_comment(question_attempt_step $pendingstep) {
        $laststep = $this->qa->get_last_step();

        if ($pendingstep->has_im_var('mark')) {
            $pendingstep->set_fraction($pendingstep->get_im_var('mark') /
                    $pendingstep->get_im_var('maxmark'));
        }

        $pendingstep->set_state(question_state::manually_graded_state_for_other_state(
                $laststep->get_state(), $pendingstep->get_fraction()));
        return question_attempt::KEEP;
    }
}


/**
 * This class contains the constants and methods required for manipulating scores
 * for certainly based marking.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_cbm {
    const LOW = 1;
    const MED = 2;
    const HIGH = 3;
    const LOW_OFFSET = 0;
    const LOW_FACTOR = 0.333333333333333;
    const MED_OFFSET = -0.666666666666667;
    const MED_FACTOR = 1.333333333333333;
    const HIGH_OFFSET = -2;
    const HIGH_FACTOR = 3;

    public static $certainties = array(self::LOW, self::MED, self::HIGH);

    protected static $factor = array(
        self::LOW => self::LOW_FACTOR,
        self::MED => self::MED_FACTOR,
        self::HIGH => self::HIGH_FACTOR,
    );

    protected static $offset = array(
        self::LOW => self::LOW_OFFSET,
        self::MED => self::MED_OFFSET,
        self::HIGH => self::HIGH_OFFSET,
    );

    public static function adjust_fraction($fraction, $certainty) {
        return self::$offset[$certainty] + self::$factor[$certainty] * $fraction;
    }
}
