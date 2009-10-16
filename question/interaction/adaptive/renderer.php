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
}