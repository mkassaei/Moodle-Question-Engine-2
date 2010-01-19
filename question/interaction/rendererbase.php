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
 * Defines the renderer base class for question interaction models.
 *
 * @package moodlecore
 * @subpackage questioninteractions
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Renderer base class for question interaction models.
 *
 * The methods in this class are mostly called from {@link core_question_renderer}
 * which coordinates the overall output of questions.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qim_renderer extends moodle_renderer_base {
    /**
     * Generate a brief textual description of the current state of the question,
     * normally displayed under the question number.
     *
     * TODO should this acutally be a method on $qa instead?
     *
     * @param question_attempt $qa a question attempt.
     * @return string a brief summary of the current state of the qestion attempt.
     */
    public function get_state_string(question_attempt $qa) {
        return question_state::default_string($qa->get_state());
    }

    /**
     * Generate some HTML (which may be blank) that appears in the question
     * formulation area, afer the question type generated output.
     *
     * For example.
     * immediatefeedback and interactive mode use this to show the Submit button,
     * and CBM use this to display the certainty choices.
     *
     * @param question_attempt $qa a question attempt.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function controls(question_attempt $qa, question_display_options $options) {
        return '';
    }

    /**
     * Generate some HTML (which may be blank) that appears in the outcome area,
     * after the question-type generated output.
     *
     * For example, the CBM models use this to display an explanation of the score
     * adjustment that was made based on the certainty selected.
     *
     * @param question_attempt $qa a question attempt.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function feedback(question_attempt $qa, question_display_options $options) {
        return '';
    }

    /**
     * Display the manual comment, and a link to edit it, if appropriate.
     *
     * @param question_attempt $qa a question attempt.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function manual_comment(question_attempt $qa, question_display_options $options) {
        $output = '';

        if ($options->manualcomment && $qa->has_manual_comment()) {
            $output .= get_string('commentx', 'question', $qa->get_manual_comment());
        }

        if ($options->can_edit_comment()) {
            $strcomment = get_string('commentormark', 'question');
            $link = link_to_popup_window($options->manualcomment .
                    '&amp;qnumber=' . $qa->get_number_in_usage(),
                    'commentquestion', $strcomment, 480, 750, $strcomment, 'none', true);
            $output .= $this->output_tag('div', array('class' => 'commentlink'), $link);
        }

        return $output;
    }

    /**
     * Several interaction models need a submit button, so put the common code here.
     * The button is disabled if the question is displayed read-only.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    protected function submit_button(question_attempt $qa, question_display_options $options) {
        $attributes = array(
            'type' => 'submit',
            'id' => $qa->get_im_field_name('submit'),
            'name' => $qa->get_im_field_name('submit'),
            'value' => get_string('submit', 'question'),
            'class' => 'submit btn',
        );
        if ($options->readonly) {
            $attributes['disabled'] = 'disabled';
        }
        $output = $this->output_empty_tag('input', $attributes);
        if (!$options->readonly) {
            $output .= print_js_call('question_init_submit_button',
                    array($attributes['id'], $qa->get_number_in_usage()), true);
        }
        return $output;
    }

    /**
     * Return any HTML that needs to be included in the page's <head> when
     * questions using this model are used.
     * @param $qa the question attempt that will be displayed on the page.
     * @return string HTML fragment.
     */
    public function head_code(question_attempt $qa) {
        return '';
    }
}
