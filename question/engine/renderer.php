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
 * Renderers for outputting parts of the question engine.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * This renderer controls the overall output of questions. It works with a
 * {@link qim_renderer} and a {@link qtype_renderer} to output the
 * type-specific bits. The main entry point is the {@link question()} method.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_question_renderer extends moodle_renderer_base {

    /**
     * Generate the display of a question in a particular state, and with certain
     * display options. Normally you do not call this method directly. Intsead
     * you call {@link question_usage_by_activity::render_question()} which will
     * call this method with appropriate arguments.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qim_renderer $qimoutput the renderer to output the interaction model
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return string HTML representation of the question.
     */
    public function question(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options, $number) {

        $output = '';
        $output .= $this->output_start_tag('div', array(
            'id' => 'q' . $qa->get_number_in_usage(),
            'class' => 'que ' . $qa->get_question()->qtype->name() . ' ' .
                    $qa->get_interaction_model_name(),
        ));

        $output .= $this->output_tag('div', array('class' => 'info'),
                $this->info($qa, $qimoutput, $qtoutput, $options, $number));

        $output .= $this->output_start_tag('div', array('class' => 'content'));

        $output .= $this->output_tag('div', array('class' => 'formulation'),
                $this->formulation($qa, $qimoutput, $qtoutput, $options));
        $output .= $this->output_nonempty_tag('div', array('class' => 'outcome'),
                $this->outcome($qa, $qimoutput, $qtoutput, $options));
        $output .= $this->output_nonempty_tag('div', array('class' => 'comment'),
                $qimoutput->manual_comment($qa, $options));
        $output .= $this->output_nonempty_tag('div', array('class' => 'history'),
                $this->response_history($qa, $qimoutput, $qtoutput, $options));

        $output .= $this->output_end_tag('div');
        $output .= $this->output_end_tag('div');
        return $output;
    }

    /**
     * Generate the information bit of the question display that contains the
     * metadata like the question number, current state, and mark.
     * @param question_attempt $qa the question attempt to display.
     * @param qim_renderer $qimoutput the renderer to output the interaction model
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return HTML fragment.
     */
    protected function info(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options, $number) {
        $output = '';
        $output .= $this->number($number);
        $output .= $this->status($qa, $qimoutput, $options);
        $output .= $this->mark_summary($qa, $options);
        $output .= $this->question_flag($qa, $options->flags);
        return $output;
    }

    /**
     * Generate the display of the question number.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return HTML fragment.
     */
    protected function number($number) {
        $numbertext = '';
        if (is_numeric($number)) {
            $numbertext = get_string('questionx', 'question',
                    $this->output_tag('span', array('class' => 'qno'), $number));
        } else if ($number == 'i') {
            $numbertext = get_string('information', 'question');
        }
        if (!$numbertext) {
            return '';
        }
        return $this->output_tag('h2', array('class' => 'no'), $numbertext);
    }

    /**
     * Generate the display of the status line that gives the current state of
     * the question.
     * @param question_attempt $qa the question attempt to display.
     * @param qim_renderer $qimoutput the renderer to output the interaction model
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function status(question_attempt $qa, qim_renderer $qimoutput, question_display_options $options) {
        if ($options->correctness) {
            return $this->output_tag('div', array('class' => 'state'),
                    $qimoutput->get_state_string($qa));
        } else {
            return '';
        }
    }

    /**
     * Generate the display of the marks for this question.
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function mark_summary(question_attempt $qa, question_display_options $options) {
        if (!$options->marks) {
            return '';
        }

        if ($qa->get_max_mark() == 0) {
            $summary = get_string('notgraded', 'question');

        } else if ($options->marks == question_display_options::MAX_ONLY ||
                !$qa->get_state()->is_graded()) {
            $summary = get_string('markedoutofmax', 'question', $qa->format_max_mark($options->markdp));

        } else {
            $a = new stdClass;
            $a->mark = $qa->format_mark($options->markdp);
            $a->max = $qa->format_max_mark($options->markdp);
            $summary = get_string('markoutofmax', 'question', $a);
        }

        return $this->output_tag('div', array('class' => 'grade'), $summary);
    }

    /**
     * Render the question flag, assuming $flagsoption allows it.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param integer $flagsoption the option that says whether flags should be displayed.
     */
    protected function question_flag(question_attempt $qa, $flagsoption) {
        global $CFG;
        switch ($flagsoption) {
            case question_display_options::VISIBLE:
                $flagcontent = $this->get_flag_html($qa->is_flagged());
                break;
            case question_display_options::EDITABLE:
                $id = $qa->get_flag_field_name();
                if ($qa->is_flagged()) {
                    $checked = 'checked="checked" ';
                } else {
                    $checked = '';
                }
                $postdata = question_flags::get_postdate($qa);
                $flagcontent = '<input type="hidden" name="' . $id . '" value="0" />' .
                        '<input type="checkbox" id="' . $id . '" name="' . $id . '" value="1" ' . $checked . ' />' .
                        '<label id="' . $id . 'label" for="' . $id . '">' . $this->get_flag_html(
                        $qa->is_flagged(), $id . 'img') . '</label>' . "\n" .
                        print_js_call('question_flag_changer.init_flag', array($id, $postdata, $qa->get_number_in_usage()), true);
                break;
            default:
                $flagcontent = '';
        }
        if ($flagcontent) {
            return '<div class="questionflag">' . $flagcontent . "</div>\n";
        }
    }

    /**
     * Work out the actual img tag needed for the flag
     *
     * @param boolean $flagged whether the question is currently flagged.
     * @param string $id an id to be added as an attribute to the img (optional).
     * @return string the img tag.
     */
    protected function get_flag_html($flagged, $id = '') {
        global $CFG;
        if ($id) {
            $id = 'id="' . $id . '" ';
        }
        if ($flagged) {
            $img = 'flagged.png';
        } else {
            $img = 'unflagged.png';
        }
        return '<img ' . $id . 'src="' . $CFG->pixpath . '/i/' . $img .
                '" alt="' . get_string('flagthisquestion', 'question') . '" />';
    }

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the quetsion text, and the controls for students to
     * input their answers. Some question types also embed feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qim_renderer $qimoutput the renderer to output the interaction model
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function formulation(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options) {
        $output = '';
        $output .= $qtoutput->formulation_and_controls($qa, $options);
        if ($options->clearwrong) {
            $output .= $qtoutput->clear_wrong($qa);
        }
        $output .= $this->output_nonempty_tag('div', array('class' => 'im-controls'),
                $qimoutput->controls($qa, $options));
        return $output;
    }

    /**
     * Generate the display of the outcome part of the question. This is the
     * area that contains the various forms of feedback.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qim_renderer $qimoutput the renderer to output the interaction model
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function outcome(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options) {
        $output = '';
        $output .= $this->output_nonempty_tag('div', array('class' => 'feedback'),
                $qtoutput->feedback($qa, $options));
        $output .= $this->output_nonempty_tag('div', array('class' => 'im-feedback'),
                $qimoutput->feedback($qa, $options));
        return $output;
    }

    /**
     * Generate the display of the response history part of the question. This
     * is the table showing all the steps the question has been through.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qim_renderer $qimoutput the renderer to output the interaction model
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function response_history(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options) {
        $output = '';
        // TODO
        return $output;
    }

}
