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
 * Simple test question type, for working out the new qtype API.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class qtype_multichoice_single_question extends question_graded_automatically {
    public $shuffleanswers;
    public $answers;
    public $answernumbering;
    public $correctfeedback;
    public $partiallycorrectfeedback;
    public $incorrectfeedback;

    protected $order = null;

    public function get_renderer() {
        return renderer_factory::get_renderer('qtype_multichoice', 'single');
    }

    public function get_min_fraction() {
        $minfraction = 0;
        foreach ($this->answers as $ans) {
            if ($ans->fraction < $minfraction) {
                $minfraction = $ans->fraction;
            }
        }
        return $minfraction;
    }

    public function init_first_step(question_attempt_step $step) {
        if ($step->has_qt_var('_order')) {
            $this->order = explode(',', $step->get_qt_var('_order'));
        } else {
            $this->order = array_keys($this->answers);
            if ($this->shuffleanswers) {
                shuffle($this->order);
            }
            $step->set_qt_var('_order', implode(',', $this->order));
        }
    }

    /**
     * Return an array of the question type variables that could be submitted
     * as part of a question of this type, with their types, so they can be
     * properly cleaned.
     * @return array variable name => PARAM_... constant.
     */
    public function get_expected_data() {
        return array('answer' => PARAM_INT);
    }

    public function get_correct_response() {
        // TODO
        return array();
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return array_key_exists('answer', $newresponse) == array_key_exists('answer', $prevresponse) &&
            (!array_key_exists('answer', $prevresponse) || $newresponse['answer'] == $prevresponse['answer']);
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response);
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    public function grade_response(array $response) {
        $fraction = $this->answers[$this->order[$response['answer']]]->fraction;
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    public function get_order(question_attempt  $qa) {
        $this->init_order($qa);
        return $this->order;
    }

    protected function init_order(question_attempt  $qa) {
        if (is_null($this->order)) {
            $this->order = explode(',', $qa->get_step(0)->get_qt_var('_order'));
        }
    }
}


class qtype_multichoice_single_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $order = $question->get_order($qa);
        $response = $qa->get_last_qt_var('answer', 123);

        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => 'radio',
            'name' => $inputname,
        );

        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->para = false;

        $radiobuttons = array();
        $feedbackimg = array();
        $feedback = array();
        $classes = array();
        foreach ($order as $value => $ansid) {
            $ans = $question->answers[$ansid];
            $inputattributes['value'] = $value;
            $inputattributes['id'] = $inputname . $value;
            if ($response == $value) {
                $inputattributes['checked'] = 'checked';
            } else {
                unset($inputattributes['checked']);
            }
            $radiobuttons[] = $this->output_empty_tag('input', $inputattributes) .
                    $this->output_tag('label', array('for' => $inputattributes['id']),
                    format_text($ans->answer, true, $formatoptions));

            if (($options->feedback || $options->correctresponse) && $response !== -1) {
                $feedbackimg[] = question_get_feedback_image($response == $value, $response == $value && $options->feedback);
            } else {
                $feedbackimg[] = '';
            }
            if (($options->feedback || $options->correctresponse) && $response == $value) {
                $feedback[] = format_text($ans->feedback, true, $formatoptions);
            } else {
                $feedback[] = '';
            }
            $classes[] = 'r' . ($value % 2);
            if ($options->correctresponse && $ans->fraction > 0) {
                $a->class = question_get_feedback_class($ans->fraction);
            }
        }

        $result = '';
        $result .= $this->output_tag('div', array('class' => 'qtext'),
                format_text($question->questiontext, true, $formatoptions));

        $result .= $this->output_start_tag('div', array('class' => 'ablock clearfix'));
        $result .= $this->output_tag('div', array('class' => 'prompt'),
                get_string('selectoneanswer', 'qtype_multichoice')) . "\n";

        $result .= $this->output_start_tag('div', array('class' => 'answer')) . "\n";
        foreach ($radiobuttons as $key => $radio) {
            $result .= $this->output_tag('span', array('class' => $classes[$key]),
                    $radio . $feedbackimg[$key], $feedback[$key]) . "\n";
        }
        $result .= $this->output_end_tag('div'); // answer

        $result .= $this->output_end_tag('div'); // ablock

        return $result;
    }
}