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
 * @copyright © 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_question_renderer extends moodle_renderer_base {
    public function question(question_attempt $qa, qim_renderer $qimoutput,
            qtype_renderer $qtoutput, question_display_options $options, $number) {

    // TODO
    $editlink = '';

    ob_start();
?>
<div id="q<?php echo $qa->get_question()->id; ?>" class="que <?php echo $qa->get_question()->qtype->name(); ?> clearfix">
  <div class="info">
    <h2 class="no"><span class="accesshide">Question </span><?php echo $number;
    if ($editlink) { ?>
      <span class="edit"><?php echo $editlink; ?></span>
    <?php } ?></h2><?php
    if ($qa->get_max_grade()) { ?>
      <div class="grade">
        <?php echo get_string('gradex', 'question', $qa->format_grade_out_of_max($options->gradedp)); ?>
      </div>
    <?php }
    echo $this->question_flag($qa, $options->flags); ?>
  </div>
  <div class="content">
    <?php
    echo $qtoutput->formulation_and_controls($qa, $options);
    echo $qimoutput->controls($qa, $options);
    if ($qa->has_manual_comment()) { ?>
      <div class="comment">
        <?php
          echo get_string('commentx', 'question', $qa->get_manual_comment());
        ?>
      </div>
    <?php }
    echo $this->comment_link($qa, $options); ?>
    <div class="grading">
      <?php echo $qimoutput->grading_details($qa, $options); ?>
    </div><?php
    if ($options->history) { ?>
      <div class="history">
        <?php
          print_string('history', 'question');
          echo $this->history($qa, $options);
        ?>
      </div>
    <?php } ?>
  </div>
</div>
<?php
        $result = ob_get_clean();
        return $result;
    }

    protected function history(question_attempt $qa, question_display_options $options) {
        return ''; // TODO
    }

    protected function comment_link(question_attempt $qa, question_display_options $options) {
        if (!$options->manualcommentlink) {
            return '';
        }
        $strcomment = get_string('commentorgrade', 'quiz');
        $commentlink = link_to_popup_window($options->questioncommentlink .
                '?attempt=' . $state->attempt . '&amp;question=' . $actualquestionid,
                'commentquestion', $strcomment, 480, 750, $strcomment, 'none', true);
        return '<div class="commentlink">'. $commentlink .'</div>';
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
            case QUESTION_FLAGSSHOWN:
                $flagcontent = $this->get_flag_html($qa->is_flagged());
                break;
            case QUESTION_FLAGSEDITABLE:
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
            echo '<div class="questionflag">' . $flagcontent . "</div>\n";
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

    public function general_feedback(question_attempt $qa) {
        return '<div class="generalfeedback">' . $qa->get_question()->generalfeedback .
                '</div>';
    }
}


abstract class qim_renderer extends moodle_renderer_base {
    public function controls(question_attempt $qa, question_display_options $options) {
        return '';
    }

    /**
    * Prints the score obtained and maximum score available plus any penalty
    * information
    *
    * This function prints a summary of the scoring in the most recently
    * graded state (the question may not have been submitted for marking at
    * the current state). The default implementation should be suitable for most
    * question types.
    * @param object $question The question for which the grading details are
    *                         to be rendered. Question type specific information
    *                         is included. The maximum possible grade is in
    *                         ->maxgrade.
    * @param object $state    The state. In particular the grading information
    *                          is in ->grade, ->raw_grade and ->penalty.
    * @param object $cmoptions
    * @param object $options  An object describing the rendering options.
    */
    function grading_details(question_attempt $qa, question_display_options $options) {
        /* The default implementation prints the number of marks if no attempt
        has been made. Otherwise it displays the grade obtained out of the
        maximum grade available and a warning if a penalty was applied for the
        attempt and displays the overall grade obtained counting all previous
        responses (and penalties) */

        if ($qa->get_max_grade() > 0 && $options->scores) {
            if (question_state::is_graded($qa->get_state())) {
                // Display the grading details from the last graded state
                $grade = new stdClass;
                $grade->cur = $qa->format_grade($options->gradedp);
                $grade->max = $qa->format_max_grade($options->gradedp);
                $grade->raw = $qa->format_grade($options->gradedp);

                // let student know wether the answer was correct
                $class = question_state::get_feedback_class($qa->get_state());
                echo '<div class="correctness ' . $class . '">' . get_string($class, 'question') . '</div>';

                echo '<div class="gradingdetails">';
                // print grade for this submission
                print_string('gradingdetails', 'question', $grade);
                echo '</div>';
            }
        }
    }
}
