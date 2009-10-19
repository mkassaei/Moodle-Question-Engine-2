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
 * Renderer for outputting parts of a question belonging to the legacy
 * adaptive interaction model.
 *
 * @package qim_adaptive
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class qim_adaptive_renderer extends qim_renderer {
    public function controls(question_attempt $qa, question_display_options $options) {
        if (!question_state::is_active($qa->get_state())) {
            return '';
        }
        return $this->output_empty_tag('input', array(
            'type' => 'submit',
            'name' => $qa->get_im_field_name('submit'),
            'value' => get_string('submit', 'qim_adaptive'),
            'class' => 'submit btn',
        ));
    }

    public function grading_details(question_attempt $qa, question_display_options $options) {
        // Try to find the last graded step.
        $gradedstep = null;
        foreach ($qa->get_reverse_step_iterator() as $step) {
            if ($step->has_im_var('_try')) {
                $gradedstep = $step;
                break;
            }
        }

        if (is_null($gradedstep) || $qa->get_max_mark() == 0 || !$options->marks) {
            return '';
        }

        // Display the grading details from the last graded state
        $mark = new stdClass;
        $mark->max = $qa->format_max_mark($options->markdp);

        $actualmark = $gradedstep->get_fraction() * $qa->get_max_mark();
        $mark->cur = round($actualmark, $options->markdp);

        $rawmark = $gradedstep->get_im_var('_rawfraction') * $qa->get_max_mark();
        $mark->raw = round($rawmark, $options->markdp);

        // let student know wether the answer was correct
        if (question_state::is_commented($qa->get_state())) {
            $class = question_state::get_feedback_class($qa->get_state());
        } else {
            $class = question_state::get_feedback_class(
                    question_state::graded_state_for_fraction($gradedstep->get_im_var('_rawfraction')));
        }

        $gradingdetails = get_string('gradingdetails', 'question', $mark);

        if ($qa->get_question()->penalty) {
            // print details of grade adjustment due to penalties
            if ($mark->raw != $mark->cur){
                $gradingdetails .= ' ' . get_string('gradingdetailsadjustment', 'quiz', $mark);
            }
            // print info about new penalty
            // penalty is relevant only if the answer is not correct and further attempts are possible
            if (!question_state::is_finished($qa->get_state())) {
                $gradingdetails .= ' ' . get_string('gradingdetailspenalty', 'quiz', $qa->get_question()->penalty);
            }
        }

        $output = '';
        $output .= $this->output_tag('div', array('class' => 'correctness ' . $class),
                get_string($class, 'question'));
        $output .= $this->output_tag('div', array('class' => 'gradingdetails'),
                $gradingdetails);
        return $output;
    }
}
