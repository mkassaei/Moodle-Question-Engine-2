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
 * Multiple choice question renderer classes.
 *
 * @package qtype_shortanswer
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Generates the output for the multiple choice questions.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
