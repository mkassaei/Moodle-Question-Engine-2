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
 * @package qtype_multichoice
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Base class for generating the bits of output common to multiple choice
 * single and multiple questions.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_multichoice_renderer_base extends qtype_renderer {
    abstract protected function get_input_type();

    abstract protected function get_input_name(question_attempt $qa, $value);

    abstract protected function get_input_value($value);

    abstract protected function get_input_id(question_attempt $qa, $value);

    abstract protected function is_choice_selected($response, $value);

    abstract protected function is_right(question_answer $ans);

    abstract protected function get_response(question_attempt $qa);

    abstract protected function prompt();

    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $order = $question->get_order($qa);
        $response = $this->get_response($qa);

        $inputname = $qa->get_qt_field_name('answer');
        $inputattributes = array(
            'type' => $this->get_input_type(),
            'name' => $inputname,
        );

        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $radiobuttons = array();
        $feedbackimg = array();
        $feedback = array();
        $classes = array();
        foreach ($order as $value => $ansid) {
            $ans = $question->answers[$ansid];
            $inputattributes['name'] = $this->get_input_name($qa, $value);
            $inputattributes['value'] = $this->get_input_value($value);
            $inputattributes['id'] = $this->get_input_id($qa, $value);
            $isselected = $this->is_choice_selected($response, $value);
            if ($isselected) {
                $inputattributes['checked'] = 'checked';
            } else {
                unset($inputattributes['checked']);
            }
            $radiobuttons[] = $this->output_empty_tag('input', $inputattributes) .
                    $this->output_tag('label', array('for' => $inputattributes['id']),
                    $this->number_in_style($value, $question->answernumbering) .
                    $question->format_text($ans->answer));

            if (($options->feedback || $options->correctresponse) && $response !== -1) {
                $feedbackimg[] = question_get_feedback_image($this->is_right($ans), $isselected && $options->feedback);
            } else {
                $feedbackimg[] = '';
            }
            if (($options->feedback || $options->correctresponse) && $isselected) {
                $feedback[] = $question->format_text($ans->feedback);
            } else {
                $feedback[] = '';
            }
            $class = 'r' . ($value % 2);
            if ($options->correctresponse && $ans->fraction > 0) {
                $class .= ' ' . question_get_feedback_class($ans->fraction);
            }
            $classes[] = $class;
        }

        $result = '';
        $result .= $this->output_tag('div', array('class' => 'qtext'),
                $question->format_questiontext());

        $result .= $this->output_start_tag('div', array('class' => 'ablock'));
        $result .= $this->output_tag('div', array('class' => 'prompt'), $this->prompt());

        $result .= $this->output_start_tag('div', array('class' => 'answer'));
        foreach ($radiobuttons as $key => $radio) {
            $result .= $this->output_tag('span', array('class' => $classes[$key]),
                    $radio . $feedbackimg[$key] . $feedback[$key]) . "\n";
        }
        $result .= $this->output_end_tag('div'); // answer

        $result .= $this->output_end_tag('div'); // ablock

        if ($qa->get_state() == question_state::$invalid) {
            $result .= $this->output_nonempty_tag('div', array('class' => 'validationerror'),
                    $question->get_validation_error($qa->get_last_qt_data()));
        }

        return $result;
    }

    protected function number_html($qnum) {
        return $qnum . '. ';
    }

    /**
     * @param int $num The number, starting at 0.
     * @param string $style The style to render the number in. One of the ones returned by $numberingoptions.
     * @return string the number $num in the requested style.
     */
    protected function number_in_style($num, $style) {
        switch($style) {
            case 'abc':
                return $this->number_html(chr(ord('a') + $num));
            case 'ABCD':
                return $this->number_html(chr(ord('A') + $num));
            case '123':
                return $this->number_html(($num + 1));
            case 'none':
                return '';
            default:
                return 'ERR';
        }
    }

    public function specific_feedback(question_attempt $qa) {
        $question = $qa->get_question();

        $feedback = '';
        if ($qa->get_state()->is_correct()) {
            $feedback = $question->correctfeedback;
        } else if ($qa->get_state()->is_partially_correct()) {
            $feedback = $question->partiallycorrectfeedback;
        } else if ($qa->get_state()->is_incorrect()) {
            $feedback = $question->incorrectfeedback;
        }

        if ($feedback) {
            $feedback = $question->format_text($feedback);
        }

        return $feedback;
    }
}


/**
 * Subclass for generating the bits of output specific to multiple choice
 * single questions.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_multichoice_single_renderer extends qtype_multichoice_renderer_base {
    protected function get_input_type() {
        return 'radio';
    }

    protected function get_input_name(question_attempt $qa, $value) {
        return $qa->get_qt_field_name('answer');
    }

    protected function get_input_value($value) {
        return $value;
    }

    protected function get_input_id(question_attempt $qa, $value) {
        return $qa->get_qt_field_name('answer' . $value);
    }

    protected function get_response(question_attempt $qa) {
        return $qa->get_last_qt_var('answer', -1);
    }

    protected function is_choice_selected($response, $value) {
        return $response == $value;
    }

    protected function is_right(question_answer $ans) {
        return $ans->fraction > 0.9999999;
    }

    protected function prompt() {
        return get_string('selectone', 'qtype_multichoice');
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        foreach ($question->answers as $ans) {
            if ($ans->fraction > 0.9999999) {
                return get_string('correctansweris', 'qtype_multichoice',
                        $question->format_text($ans->answer));
            }
        }

        return '';
    }
}

/**
 * Subclass for generating the bits of output specific to multiple choice
 * multi=select questions.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_multichoice_multi_renderer extends qtype_multichoice_renderer_base {
    protected function get_input_type() {
        return 'checkbox';
    }

    protected function get_input_name(question_attempt $qa, $value) {
        return $qa->get_qt_field_name('choice' . $value);
    }

    protected function get_input_value($value) {
        return 1;
    }

    protected function get_input_id(question_attempt $qa, $value) {
        return $this->get_input_name($qa, $value);
    }

    protected function get_response(question_attempt $qa) {
        return $qa->get_last_qt_data();
    }

    protected function is_choice_selected($response, $value) {
        return isset($response['choice' . $value]);
    }

    protected function is_right(question_answer $ans) {
        return $ans->fraction > 0;
    }

    protected function prompt() {
        return get_string('selectmulti', 'qtype_multichoice');
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        $right = array();
        foreach ($question->answers as $ans) {
            if ($ans->fraction > 0) {
                $right[] = $question->format_text($ans->answer);
            }
        }

        if (!empty($right)) {
                return get_string('correctansweris', 'qtype_multichoice',
                        implode(', ', $right));
            
        }
        return '';
    }
}
