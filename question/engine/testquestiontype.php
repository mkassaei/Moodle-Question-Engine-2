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
 * Simple test question type, for working out the new qtype API.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class test_question_type {
    public function get_interaction_model(question_attempt $qa, $preferredmodel) {
        question_engine::load_interaction_model_class('deferredfeedback');
        return new question_deferredfeedback_model($qa);
    }

    public function get_renderer($question) {
        return renderer_factory::get_renderer('qtype_truefalse');
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        // Check that the two arrays have exactly the same keys and values.
        $diff1 = array_diff_assoc($prevresponse, $newresponse);
        if (!empty($diff1)) {
            return false;
        }
        $diff2 = array_diff_assoc($newresponse, $prevresponse);
        return empty($diff2);
    }

    public function is_complete_response(array $response) {
        return isset($response['true']) || isset($response['false']);
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    public function grade_response($question, array $response) {
        if (isset($response['true']) && $response['true']) {
            $grade = $question->options->answers[$question->options->trueanswer]->fraction;
        } else {
            $grade = $question->options->answers[$question->options->falseanswer]->fraction;
        }
        return array($grade, question_state::graded_state_for_grade($grade));
    }
}


class qtype_truefalse_renderer extends qtype_renderer {
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {
        $readonly = $options->readonly ? ' disabled="disabled"' : '';

        $question = $qa->get_question();
        $answers = $question->options->answers;
        $trueanswer = $answers[$question->options->trueanswer];
        $falseanswer = $answers[$question->options->falseanswer];
        $correctanswer = ($trueanswer->fraction == 1) ? $trueanswer : $falseanswer;

        $trueclass = '';
        $falseclass = '';
        $truefeedbackimg = '';
        $falsefeedbackimg = '';

        // Work out which radio button to select (if any)
        if (isset($state->responses[''])) {
            $response = $state->responses[''];
        } else {
            $response = '';
        }
        $truechecked = ($response == $trueanswer->id) ? ' checked="checked"' : '';
        $falsechecked = ($response == $falseanswer->id) ? ' checked="checked"' : '';

        // Work out visual feedback for answer correctness.
        if ($options->feedback) {
            if ($truechecked) {
                $trueclass = question_get_feedback_class($trueanswer->fraction);
            } else if ($falsechecked) {
                $falseclass = question_get_feedback_class($falseanswer->fraction);
            }
        }
        if ($options->feedback || $options->correct_responses) {
            if (isset($answers[$response])) {
                $truefeedbackimg = question_get_feedback_image($trueanswer->fraction, !empty($truechecked) && $options->feedback);
                $falsefeedbackimg = question_get_feedback_image($falseanswer->fraction, !empty($falsechecked) && $options->feedback);
            }
        }

        $inputname = ' name="'.$qa->get_field_prefix().'true" ';
        $trueid    = $qa->get_field_prefix().'true';
        $falseid   = $qa->get_field_prefix().'false';

        $radiotrue = '<input type="radio"' . $truechecked . $readonly . $inputname
            . 'id="'.$trueid . '" value="' . $trueanswer->id . '" /><label for="'.$trueid . '">'
            . s($trueanswer->answer) . '</label>';
        $radiofalse = '<input type="radio"' . $falsechecked . $readonly . $inputname
            . 'id="'.$falseid . '" value="' . $falseanswer->id . '" /><label for="'.$falseid . '">'
            . s($falseanswer->answer) . '</label>';

        $feedback = '';
        if ($options->feedback and isset($answers[$response])) {
            $chosenanswer = $answers[$response];
            $feedback = format_text($chosenanswer->feedback, true, $formatoptions, $cmoptions->course);
        }

        ob_start();
?>
<div class="qtext">
  <?php echo $qa->get_question()->questiontext; ?>
</div>

<div class="ablock clearfix">
  <div class="prompt">
    <?php print_string('answer', 'question') ?>:
  </div>

  <div class="answer">
    <span <?php echo 'class="r0 '.$trueclass.'"'; ?>>
        <?php echo $radiotrue ?>
        <?php echo $truefeedbackimg; ?>
    </span>
    <span <?php echo 'class="r1 '.$falseclass.'"'; ?>>
        <?php echo $radiofalse ?>
        <?php echo $falsefeedbackimg; ?>
    </span>
  </div>
    <?php if ($feedback) { ?>
        <div class="feedback">
            <?php echo $feedback ?>
        </div>
    <?php } ?>
</div>
<?php
        $result = ob_get_clean();
        return $result;
    }
}


