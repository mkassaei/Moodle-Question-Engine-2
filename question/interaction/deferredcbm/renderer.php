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
 * Renderer for outputting parts of a question belonging to the delayed
 * feedback interaction model.
 *
 * @package qim_deferredcbm
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class qim_deferredcbm_renderer extends qim_renderer {
    protected function certainly_choices($controlname, $selected, $readonly) {
        $attributes = array(
            'type' => 'radio',
            'name' => $controlname,
        );
        if ($readonly) {
            $attributes['disabled'] = 'disabled';
        }

        $choices = '';
        foreach (question_cbm::$certainties as $certainly) {
            $id = $controlname . $certainly;
            $attributes['id'] = $id;
            $attributes['value'] = $certainly;
            if ($selected == $certainly) {
                $attributes['checked'] = 'checked';
            } else {
                unset($attributes['checked']);
            }
            $choices .= ' ' . $this->output_empty_tag('input', $attributes) . ' ' .
                    $this->output_tag('label', array('for' => $id),
                    get_string('certainty' . $certainly, 'qim_deferredcbm'));
        }
        return $choices;
    }

    public function controls(question_attempt $qa, question_display_options $options) {

        return $this->output_tag('div', array('class' => 'controls'),
                get_string('howcertainareyou', 'qim_deferredcbm',
                $this->certainly_choices($qa->get_im_field_name('certainty'),
                $qa->get_last_im_var('certainty'), $options->readonly)));
    }
}