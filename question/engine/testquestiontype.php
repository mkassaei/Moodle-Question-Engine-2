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


class question_truefalse extends question_definition {
    public $rightanswer = true;
    public $truefeedback = 'This is the right answer.';
    public $falsefeedback = 'This is the wrong answer.';

    public function get_interaction_model(question_attempt $qa, $preferredmodel) {
        question_engine::load_interaction_model_class($preferredmodel);
        $class = 'qim_' . $preferredmodel;
        return new $class($qa);
    }

    public function get_renderer() {
        return renderer_factory::get_renderer('qtype_truefalse');
    }

    public function get_min_fraction() {
        return 0;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        // Check that the two arrays have exactly the same keys and values.
        $diff1 = array_diff_assoc($prevresponse, $newresponse);
        if (!empty($diff1)) {
            return false;
        }
        $diff2 = array_diff_assoc($newresponse, $prevresponse);
        return empty($diff2);
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response);
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    public function grade_response($question, array $response) {
        if ($this->rightanswer == true && $response['answer'] == true) {
            $fraction = 1;
        } else if ($this->rightanswer == false && $response['answer'] == false) {
            $fraction = 1;
        } else {
            $fraction = 0;
        }
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }
}


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
        if (($options->feedback || $options->correct_responses) && $response !== '') {
            $truefeedbackimg = question_get_feedback_image($response, $truechecked && $options->feedback);
            $falsefeedbackimg = question_get_feedback_image(!$response, $falsechecked && $options->feedback);
        }

        $radiotrue = $this->output_empty_tag('input', $trueattributes) .
                $this->output_tag('label', array('for' => $trueattributes['id']),
                get_string('true', 'qtype_truefalse'));
        $radiofalse = $this->output_empty_tag('input', $falseattributes) .
                $this->output_tag('label', array('for' => $falseattributes['id']),
                get_string('false', 'qtype_truefalse'));

        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->para = false;

        $feedback = '';
        if ($truechecked) {
            $feedback = format_text($question->truefeedback, true, $formatoptions);
        } else {
            $feedback = format_text($question->falsefeedback, true, $formatoptions);
        }

        $result = '';
        $result .= $this->output_tag('div', array('class' => 'qtext'),
                format_text($question->questiontext, true, $formatoptions));

        $result .= $this->output_start_tag('div', array('class' => 'ablock clearfix'));
        $result .= $this->output_tag('div', array('class' => 'prompt'),
                get_string('answer', 'question'));

        $result .= $this->output_start_tag('div', array('class' => 'answer'));
        $result .= $this->output_tag('span', array('class' => 'r0' . $trueclass),
                $radiotrue . $truefeedbackimg);
        $result .= $this->output_tag('span', array('class' => 'r0' . $falseclass),
                $radiofalse . $falsefeedbackimg);
        $result .= $this->output_end_tag('div'); // answer

        if ($feedback) {
            $result .= $this->output_tag('div', array('class' => 'feedback'), $feedback);
        }

        $result .= $this->output_end_tag('div'); // ablock

        return $result;
    }
}


class question_essay extends question_definition {
    public function get_interaction_model(question_attempt $qa, $preferredmodel) {
        question_engine::load_interaction_model_class('manualgraded');
        return new qim_manualgraded($qa);
    }

    public function get_renderer() {
        return renderer_factory::get_renderer('qtype_essay');
    }

    public function get_min_fraction() {
        return 0;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        // Check that the two arrays have exactly the same keys and values.
        $diff1 = array_diff_assoc($prevresponse, $newresponse);
        if (!empty($diff1)) {
            return false;
        }
        $diff2 = array_diff_assoc($newresponse, $prevresponse);
        return empty($diff2);
    }

    public function is_complete_response(array $response) {
        return !empty($response['answer']);
    }
}


class qtype_essay_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $response = $qa->get_last_qt_var('answer', '');


        $formatoptions          = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->para    = false;

        $safeformatoptions = new stdClass;
        $safeformatoptions->para = false;

        $stranswer = get_string('answer', 'question');

        /// set question text and media
        $questiontext = format_text($question->questiontext,
                $question->questiontextformat, $formatoptions);

        // Answer field.
        $inputname = $qa->get_qt_field_name('answer');
        if (empty($options->readonly)) {
            // the student needs to type in their answer so print out a text editor
            $answer = print_textarea(can_use_html_editor(), 18, 80, 630, 400, $inputname, $response, 0, true);
        } else {
            // it is read only, so just format the students answer and output it
            $answer = format_text($response, FORMAT_MOODLE,
                                  $safeformatoptions, $cmoptions->course);
            $answer = '<div class="answerreview">' . $answer . '</div>';
        }

        $result = '';
        $result .= $this->output_tag('div', array('class' => 'qtext'),
                format_text($question->questiontext, true, $formatoptions));

        $result .= $this->output_start_tag('div', array('class' => 'ablock clearfix'));
        $result .= $this->output_tag('div', array('class' => 'prompt'),
                get_string('answer', 'question'));
        $result .= $this->output_tag('div', array('class' => 'answer'), $answer);
        $result .= $this->output_end_tag('div'); // ablock

        return $result;
    }
}
