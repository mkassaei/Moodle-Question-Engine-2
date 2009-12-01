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
 * True-false question renderer class.
 *
 * @package qtype_truefalse
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Generates the output for true-false questions.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_truefalse_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $response = $qa->get_last_qt_var('answer', '');

        $inputname = $qa->get_qt_field_name('answer');
        $trueattributes = array(
            'type' => 'radio',
            'name' => $inputname,
            'value' => 1,
            'id' => $inputname . 'true',
        );
        $falseattributes = array(
            'type' => 'radio',
            'name' => $inputname,
            'value' => 0,
            'id' => $inputname . 'false',
        );

        if ($options->readonly) {
            $trueattributes['disabled'] = 'disabled';
            $falseattributes['disabled'] = 'disabled';
        }

        // Work out which radio button to select (if any)
        $truechecked = false;
        $falsechecked = false;
        if ($response) {
            $trueattributes['checked'] = 'checked';
            $truechecked = true;
        } else if ($response !== '') {
            $falseattributes['checked'] = 'checked';
            $falsechecked = true;
        }

        // Work out visual feedback for answer correctness.
        $trueclass = '';
        $falseclass = '';
        if ($options->feedback) {
            if ($truechecked) {
                $trueclass = ' ' . question_get_feedback_class($question->rightanswer);
            } else if ($falsechecked) {
                $falseclass = ' ' . question_get_feedback_class(!$question->rightanswer);
            }
        }
        $truefeedbackimg = '';
        $falsefeedbackimg = '';
        if (($options->feedback || $options->correctresponse) && $response !== '') {
            $truefeedbackimg = question_get_feedback_image($response, $truechecked && $options->feedback);
            $falsefeedbackimg = question_get_feedback_image(!$response, $falsechecked && $options->feedback);
        }

        $radiotrue = $this->output_empty_tag('input', $trueattributes) .
                $this->output_tag('label', array('for' => $trueattributes['id']),
                get_string('true', 'qtype_truefalse'));
        $radiofalse = $this->output_empty_tag('input', $falseattributes) .
                $this->output_tag('label', array('for' => $falseattributes['id']),
                get_string('false', 'qtype_truefalse'));

        $result = '';
        $result .= $this->output_tag('div', array('class' => 'qtext'),
                $question->format_questiontext());

        $result .= $this->output_start_tag('div', array('class' => 'ablock'));
        $result .= $this->output_tag('div', array('class' => 'prompt'),
                get_string('selectone', 'qtype_truefalse'));

        $result .= $this->output_start_tag('div', array('class' => 'answer'));
        $result .= $this->output_tag('span', array('class' => 'r0' . $trueclass),
                $radiotrue . $truefeedbackimg);
        $result .= $this->output_tag('span', array('class' => 'r1' . $falseclass),
                $radiofalse . $falsefeedbackimg);
        $result .= $this->output_end_tag('div'); // answer

        $result .= $this->output_end_tag('div'); // ablock

        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_var('answer', '');

        if ($response) {
            return $question->format_text($question->truefeedback);
        } else {
            return $question->format_text($question->falsefeedback);
        }
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        if ($question->rightanswer) {
            return get_string('correctanswertrue', 'qtype_truefalse');
        } else {
            return get_string('correctanswerfalse', 'qtype_truefalse');
        }
    }
}
