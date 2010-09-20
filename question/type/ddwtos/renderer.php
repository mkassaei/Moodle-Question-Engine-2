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
 * Drag-and-drop words into sentences question renderer class.
 *
 * @package qtype_ddwtos
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Generates the output for drag-and-drop words into sentences questions.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddwtos_renderer extends qtype_with_combined_feedback_renderer {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();

        $questiontext = '';
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $questiontext .= $this->drop_box($qa, $i, $options);
            }
            $questiontext .= $fragment;
        }

        $dragboxs = '';
        foreach ($question->choices as $group => $choices) {
            $dragboxs .= $this->drag_boxes($qa, $group,
                    $question->get_ordered_choices($group), $options);
        }

        $result = '';
        $result .= html_writer::tag('div', $question->format_text($questiontext),
                array('class' => 'qtext ddwtos_questionid_for_javascript', 'id' => $qa->get_qt_field_name('')));
        $result .= html_writer::tag('div', $dragboxs,
                array('class' => 'answercontainer'));

        // We abuse the clear_wrong method to output the hidden form fields we
        // want irrespective of whether we are actually clearing the wrong
        // bits of the response.
        if (!$options->clearwrong) {
            $result .= $this->clear_wrong($qa, false);
        }

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error($qa->get_last_qt_data()),
                    array('class' => 'validationerror'));
        }

        return $result;
    }

    /**
     * Modify the contents of a drag/drop box to fix some IE-related problem.
     * Unfortunately I don't have more details than that.
     * @param string $string the box contents.
     * @return string the box contents modified.
     */
    protected function dodgy_ie_fix($string) {
        return '<sub>&#160;</sub>' . $string . '<sup>&#160;</sup>';
    }

    protected function drop_box(question_attempt $qa, $place, question_display_options $options) {
        $question = $qa->get_question();
        $group = $question->places[$place];
        $boxcontents = $this->dodgy_ie_fix('&#160;');

        $value = $qa->get_last_qt_var($question->field($place));

        $attributes = array(
            'id' => $this->box_id($qa, 'p' . $place, $group),
            'class' => 'slot group' . $group
        );

        if ($options->readonly) {
            $attributes['class'] .= ' readonly';
        } else {
            $attributes['tabindex'] = '0';
        }

        $feedbackimage = '';
        if ($options->correctness) {
            
            $attributes['class'] .= ' readonly';
        }

        return html_writer::tag('span', $boxcontents, $attributes);
    }

    protected function drag_boxes($qa, $group, $choices, question_display_options $options) {
        $readonly = '';
        if ($options->readonly) {
            $readonly = ' readonly';
        }

        $boxes = '';
        foreach ($choices as $key => $choice) {
            //Bug 8632 -  long text entry causes bug in drag and drop field in IE
            $content = str_replace('-', '&#x2011;', $choice->text);
            $content = $this->dodgy_ie_fix(str_replace(' ', '&#160;', $content));

            $infinite = '';
            if ($choice->isinfinite) {
                $infinite = ' infinite';
            }

            $boxes .= html_writer::tag('span', $content, array(
                    'id' => $this->box_id($qa, $key, $choice->draggroup),
                    'class' => 'player group' . $choice->draggroup . $infinite . $readonly)) . ' ';
        }

        return html_writer::nonempty_tag('div', $boxes, array('class' => 'answertext'));
    }

    protected function box_id(question_attempt $qa, $place, $group) {
        return $qa->get_qt_field_name($place) . '_' . $group;
    }

    public function specific_feedback(question_attempt $qa) {
        return $this->combined_feedback($qa);
    }

    public function head_code(question_attempt $qa) {
        require_js(array('yui_dom-event', 'yui_dragdrop'));
        return parent::head_code($qa);
    }

    /**
     * Actually, this question type abuses this method to always ouptut the
     * hidden fields it needs.
     */
    public function clear_wrong(question_attempt $qa, $reallyclear = true) {
        $question = $qa->get_question();
        $response = $qa->get_last_qt_data();

        if (!empty($response) && $reallyclear) {
            $cleanresponse = $question->clear_wrong_from_response($response);
        } else {
            $cleanresponse = $response;
        }

        $output = '';
        foreach ($qa->get_question()->places as $place => $group) {
            $fieldname = $question->field($place);
            if (array_key_exists($fieldname, $response)) {
                $value = $response[$fieldname];
            } else {
                $value = '';
            }
            if (array_key_exists($fieldname, $cleanresponse)) {
                $cleanvalue = $cleanresponse[$fieldname];
            } else {
                $cleanvalue = '';
            }
            if ($cleanvalue != $value) {
                $output .= html_writer::empty_tag('input', array(
                        'type' => 'hidden',
                        'id' => $this->box_id($qa, 'p' . $place, $group) . '_hidden',
                        'value' => s($value))) .
                        html_writer::empty_tag('input', array(
                        'type' => 'hidden',
                        'name' => $qa->get_qt_field_name($fieldname),
                        'value' => s($cleanvalue)));
            } else {
                $output .= html_writer::empty_tag('input', array(
                        'type' => 'hidden',
                        'id' => $this->box_id($qa, 'p' . $place, $group) . '_hidden',
                        'name' => $qa->get_qt_field_name($fieldname),
                        'value' => s($value)));
            }
        }
        return $output;
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();

        $correctanswer = '';
        foreach ($question->textfragments as $i => $fragment) {
            if ($i > 0) {
                $group = $question->places[$i];
                $choice = $question->choices[$group][$question->get_right_choice_for($i)];
                $correctanswer .= '[' . str_replace('-', '&#x2011;',
                        $choice->text) . ']';
            }
            $correctanswer .= $fragment;
        }

        if (!empty($correctanswer)) {
            return get_string('correctansweris', 'qtype_match', $correctanswer);
        }
    }
}
