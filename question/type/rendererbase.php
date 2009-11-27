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
 * Renderer base classes for question types.
 *
 * @package moodlecore
 * @subpackage questiontypes
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


abstract class qtype_renderer extends moodle_renderer_base {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {
        return $qa->get_question()->questiontext;
    }

    public function feedback(question_attempt $qa, question_display_options $options) {
        $output = '';
        if ($options->feedback) {
            $output .= $this->specific_feedback($qa);
        }
        if ($options->generalfeedback) {
            $output .= $this->general_feedback($qa);
        }
        if ($options->correctresponse) {
            $output .= $this->correct_response($qa);
        }
        return $output;
    }

    public function specific_feedback(question_attempt $qa) {
        return '';
    }

    public function general_feedback(question_attempt $qa) {
        return $this->output_nonempty_tag('div', array('class' => 'generalfeedback'),
                $qa->get_question()->format_generalfeedback());
    }

    public function correct_response(question_attempt $qa) {
        return '';
    }
}
