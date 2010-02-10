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
 * Defines the renderer base classes for question types.
 *
 * @package moodlecore
 * @subpackage questiontypes
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Renderer base classes for question types.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_renderer extends moodle_renderer_base {
    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the quetsion text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {
        return $qa->get_question()->questiontext;
    }

    /**
     * Output hidden form fields to clear any wrong parts of the student's response.
     *
     * This method will only be called if the question is in read-only mode.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function clear_wrong(question_attempt $qa) {
        $response = $qa->get_last_qt_data();
        if (!$response) {
            return '';
        }
        $cleanresponse = $qa->get_question()->clear_wrong_from_response($response);
        $output = '';
        foreach ($cleanresponse as $name => $value) {
            $attr = array(
                'type' => 'hidden',
                'name' => $qa->get_qt_field_name($name),
                'value' => s($value),
            );
            $output .= $this->output_empty_tag('input', $attr);
        }
        return $output;
    }

    /**
     * Generate the display of the outcome part of the question. This is the
     * area that contains the various forms of feedback. This function generates
     * the content of this area belonging to the question type.
     *
     * Subclasses will normally want to override the more specific methods
     * {specific_feedback()}, {general_feedback()} and {correct_response()}
     * that this method calls.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function feedback(question_attempt $qa, question_display_options $options) {
        $output = '';
        if ($options->feedback) {
            $output .= $this->output_nonempty_tag('div', array('class' => 'specificfeedback'),
                    $this->specific_feedback($qa));
            $hint = $qa->get_applicable_hint();
            if ($hint) {
                $output .= $this->hint($qa->get_question(), $hint);
            }
        }
        if ($options->numpartscorrect) {
            $output .= $this->output_nonempty_tag('div', array('class' => 'numpartscorrect'),
                    $this->num_parts_correct($qa));
        }
        if ($options->generalfeedback) {
            $output .= $this->output_nonempty_tag('div', array('class' => 'generalfeedback'),
                    $this->general_feedback($qa));
        }
        if ($options->correctresponse) {
            $output .= $this->output_nonempty_tag('div', array('class' => 'correctresponse'),
                    $this->correct_response($qa));
        }
        return $output;
    }

    /**
     * Gereate the specific feedback. This is feedback that varies accordin to
     * the reponse the student gave.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function specific_feedback(question_attempt $qa) {
        return '';
    }

    /**
     * Gereate a brief statement of how many sub-parts of this question the
     * student got right.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function num_parts_correct(question_attempt $qa) {
        $a = new stdClass;
        list($a->num, $a->outof) = $qa->get_question()->get_num_parts_right(
                $qa->get_last_qt_data());
        if (is_null($a->outof)) {
            return '';
        } else {
            return get_string('yougotnright', 'question', $a);
        }
    }

    /**
     * Gereate the specific feedback. This is feedback that varies accordin to
     * the reponse the student gave.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function hint(question_definition $question, question_hint $hint) {
        return $this->output_nonempty_tag('div', array('class' => 'hint'),
                $question->format_text($hint->hint));
    }

    /**
     * Gereate the general feedback. This is feedback is shown ot all students.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function general_feedback(question_attempt $qa) {
        return $qa->get_question()->format_generalfeedback();
    }

    /**
     * Gereate an automatic description of the correct response to this question.
     * Not all question types can do this. If it is not possible, this method
     * should just return an empty string.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function correct_response(question_attempt $qa) {
        return '';
    }

    /**
     * Return any HTML that needs to be included in the page's <head> when this
     * question is used.
     * @param $qa the question attempt that will be displayed on the page.
     * @return string HTML fragment.
     */
    public function head_code(question_attempt $qa) {
        return implode("\n", $qa->get_question()->qtype->find_standard_scripts_and_css());
    }
}
