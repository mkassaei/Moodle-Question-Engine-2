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
 * Numerical question renderer class.
 *
 * @package qtype_numerical
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Generates the output for short answer questions.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_numerical_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');

        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => 'text',
            'name' => $inputname,
            'value' => $currentanswer,
            'id' => $inputname,
            'size' => 80,
        );

        if ($options->readonly) {
            $inputattributes['readonly'] = 'readonly';
        }

        $class = '';
        $feedbackimg = '';
        if ($options->feedback) {
            $answer = $question->get_matching_answer(array('answer' => $currentanswer));
            if ($answer) {
                $inputattributes['class'] = question_get_feedback_class($answer->fraction);
                $feedbackimg = question_get_feedback_image($answer->fraction);
                if ($answer->feedback) {
                    $feedback = $question->format_text($answer->feedback);
                }
            } else {
                $inputattributes['class'] = question_get_feedback_class(0);
                $feedbackimg = question_get_feedback_image(0);
            }
        }

        $questiontext = $question->format_questiontext();
        $placeholder = false;
        if (preg_match('/_____+/', $questiontext, $matches)) {
            $placeholder = $matches[0];
            $inputattributes['size'] = round(strlen($placeholder) * 1.1);
        }

        $input = $this->output_empty_tag('input', $inputattributes) . $feedbackimg;

        if ($placeholder) {
            $questiontext = substr_replace($questiontext, $input,
                    strpos($questiontext, $placeholder), strlen($placeholder));
        }

        $result = $this->output_tag('div', array('class' => 'qtext'), $questiontext);

        if (!$placeholder) {
            $result .= $this->output_start_tag('div', array('class' => 'ablock'));
            $result .= get_string('answer', 'qtype_shortanswer',
                    $this->output_tag('div', array('class' => 'answer'), $input));
            $result .= $this->output_end_tag('div');
        }

        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();

        $answer = $question->get_matching_answer(array('answer' => $qa->get_last_qt_var('answer')));
        if (!$answer || !$answer->feedback) {
            return '';
        }

        return $question->format_text($answer->feedback);
    }

    public function correct_response(question_attempt $qa) {
        $answer = $qa->get_question()->get_correct_answer();
        if (!$answer) {
            return '';
        }

        return get_string('correctansweris', 'qtype_shortanswer', s($answer->answer));
    }
}
