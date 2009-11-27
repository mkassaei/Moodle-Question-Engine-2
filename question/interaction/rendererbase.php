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
 * Renderer base class for question interaction models.
 *
 * @package moodlecore
 * @subpackage questioninteractions
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


abstract class qim_renderer extends moodle_renderer_base {
    public function get_state_string(question_attempt $qa) {
        return question_state::default_string($qa->get_state());
    }

    public function controls(question_attempt $qa, question_display_options $options) {
        return '';
    }

    public function feedback(question_attempt $qa, question_display_options $options) {
        return '';
    }

    public function manual_comment(question_attempt $qa, question_display_options $options) {
        $output = '';

        if ($options->manualcomment && $qa->has_manual_comment()) {
            $output .= get_string('commentx', 'question', $qa->get_manual_comment());
        }

        if ($options->can_edit_comment()) {
            $strcomment = get_string('commentormark', 'quiz');
            $link = link_to_popup_window($options->manualcomment .
                    '?attempt=' . $qa->get_id() . '&amp;question=' . $qa->get_question()->id,
                    'commentquestion', $strcomment, 480, 750, $strcomment, 'none', true);
            $output .= $this->output_tag('div', array('class' => 'commentlink'), $link);
        }

        return $output;
    }

    /**
    * Prints the mark obtained and maximum score available plus any penalty
    * information
    *
    * This function prints a summary of the scoring in the most recently
    * markd state (the question may not have been submitted for marking at
    * the current state). The default implementation should be suitable for most
    * question types.
    * @param object $question The question for which the grading details are
    *                         to be rendered. Question type specific information
    *                         is included. The maximum possible mark is in
    *                         ->maxmark.
    * @param object $state    The state. In particular the grading information
    *                          is in ->mark, ->raw_mark and ->penalty.
    * @param object $cmoptions
    * @param object $options  An object describing the rendering options.
    */
    function grading_details(question_attempt $qa, question_display_options $options) {
        /* The default implementation prints the number of marks if no attempt
        has been made. Otherwise it displays the mark obtained out of the
        maximum mark available and a warning if a penalty was applied for the
        attempt and displays the overall mark obtained counting all previous
        responses (and penalties) */

        if ($qa->get_max_mark() == 0 || !$options->marks || !question_state::is_graded($qa->get_state())) {
            return '';
        }

        // Display the grading details from the last graded state
        $mark = new stdClass;
        $mark->cur = $qa->format_mark($options->markdp);
        $mark->max = $qa->format_max_mark($options->markdp);
        $mark->raw = $qa->format_mark($options->markdp);

        // let student know wether the answer was correct
        $class = question_state::get_feedback_class($qa->get_state());

        $output = '';
        $output .= $this->output_tag('div', array('class' => 'correctness ' . $class),
                get_string($class, 'question'));
        $output .= $this->output_tag('div', array('class' => 'gradingdetails'),
                get_string('gradingdetails', 'question', $mark));

        return $output;
    }
}
