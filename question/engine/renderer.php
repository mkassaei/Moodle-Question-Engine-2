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
 * Output the generic stuff common to all questions.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_question_renderer extends moodle_renderer_base {

    protected function number($number) {
        return $this->output_tag('h2', array('class' => 'no'),
                get_string('questionx', 'question', $this->output_tag('span', array('class' => 'qno'), $number)));
    }

    public function question(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options, $number) {

        $output = '';
        $output .= $this->output_start_tag('div', array(
            'id' => 'q' . $qa->get_question()->id,
            'class' => 'que clearfix ' . $qa->get_question()->qtype->name(),
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

    public function info(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options, $number) {
        $output = '';
        $output .= $this->number($number);
        $output .= $this->status($qa, $qimoutput, $options);
        $output .= $this->mark_summary($qa, $options);
        $output .= $this->question_flag($qa, $options->flags);
        return $output;
    }

    public function status(question_attempt $qa, qim_renderer $qimoutput, question_display_options $options) {
        if ($options->correctness) {
            return $this->output_tag('div', array('class' => 'state'),
                    $qimoutput->get_state_string($qa));
        } else {
            return '';
        }
    }

    public function mark_summary(question_attempt $qa, question_display_options $options) {
        if (!$options->marks) {
            return '';
        }

        if ($qa->get_max_mark() == 0) {
            $summary = get_string('notgraded', 'question');

        } else if ($options->marks == question_display_options::MAX_ONLY ||
                !question_state::is_graded($qa->get_state())) {
            $summary = get_string('markedoutofmax', 'question', $qa->format_max_mark($options->markdp));

        } else {
            $a = new stdClass;
            $a->mark = $qa->format_mark($options->markdp);
            $a->max = $qa->format_max_mark($options->markdp);
            $summary = get_string('markoutofmax', 'question', $a);
        }

        return $this->output_tag('div', array('class' => 'grade'), $summary);
    }

    public function formulation(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options) {
        $output = '';
        $output .= $qtoutput->formulation_and_controls($qa, $options);
        $output .= $qimoutput->controls($qa, $options);
        return $output;
    }

    public function outcome(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options) {
        $output = '';
        $output .= $qtoutput->feedback($qa, $options);
        $output .= $qimoutput->feedback($qa, $options);
        return $output;
    }

    public function response_history(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options) {
        $output = '';
        // TODO
        return $output;
    }

    /**
     * Render the question flag, assuming $flagsoption allows it. You will probably
     * never need to override this method.
     *
     * @param object $question the question
     * @param object $state its current state
     * @param integer $flagsoption the option that says whether flags should be displayed.
     */
    protected function question_flag(question_attempt $qa, $flagsoption) {
        global $CFG;
        switch ($flagsoption) {
            case question_display_options::VISIBLE:
                $flagcontent = $this->get_flag_html($qa->is_flagged());
                break;
            case question_display_options::EDITABLE:
                $id = $question->name_prefix . '_flagged';
                if ($qa->is_flagged()) {
                    $checked = 'checked="checked" ';
                } else {
                    $checked = '';
                }
                $qsid = $state->questionsessionid;
                $aid = $state->attempt;
                $qid = $state->question;
                $checksum = question_get_toggleflag_checksum($aid, $qid, $qsid);
                $postdata = "qsid=$qsid&amp;aid=$aid&amp;qid=$qid&amp;checksum=$checksum&amp;sesskey=" . sesskey();
                $flagcontent = '<input type="checkbox" id="' . $id . '" name="' . $id .
                        '" value="1" ' . $checked . ' />' .
                        '<label id="' . $id . 'label" for="' . $id . '">' . $this->get_flag_html(
                        $qa->is_flagged(), $id . 'img') . '</label>' . "\n" .
                        print_js_call('question_flag_changer.init_flag', array($id, $postdata), true);
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
}


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
