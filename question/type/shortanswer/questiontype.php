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
 * Question type class for the short answer question type.
 *
 * @package qtype_shortanswer
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * The short answer question type.
 *
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_shortanswer extends question_type {
    public function extra_question_fields() {
        return array('question_shortanswer','answers','usecase');
    }

    protected function questionid_column_name() {
        return 'question';
    }

    public function save_question_options($question) {
        $result = new stdClass;

        if (!$oldanswers = get_records('question_answers', 'question', $question->id, 'id ASC')) {
            $oldanswers = array();
        }

        $answers = array();
        $maxfraction = -1;

        // Insert all the new answers
        foreach ($question->answer as $key => $dataanswer) {
            // Check for, and ingore, completely blank answer from the form.
            if (trim($dataanswer) == '' && $question->fraction[$key] == 0 &&
                    html_is_blank($question->feedback[$key])) {
                continue;
            }

            if ($oldanswer = array_shift($oldanswers)) {  // Existing answer, so reuse it
                $answer = $oldanswer;
                $answer->answer   = trim($dataanswer);
                $answer->fraction = $question->fraction[$key];
                $answer->feedback = $question->feedback[$key];
                if (!update_record("question_answers", $answer)) {
                    $result->error = "Could not update quiz answer! (id=$answer->id)";
                    return $result;
                }
            } else {    // This is a completely new answer
                $answer = new stdClass;
                $answer->answer   = trim($dataanswer);
                $answer->question = $question->id;
                $answer->fraction = $question->fraction[$key];
                $answer->feedback = $question->feedback[$key];
                if (!$answer->id = insert_record("question_answers", $answer)) {
                    $result->error = "Could not insert quiz answer!";
                    return $result;
                }
            }
            $answers[] = $answer->id;
            if ($question->fraction[$key] > $maxfraction) {
                $maxfraction = $question->fraction[$key];
            }
        }

        $question->answers = implode(',', $answers);
        $parentresult = parent::save_question_options($question);
        if($parentresult !== null) { // Parent function returns null if all is OK
            return $parentresult;
        }

        // delete old answer records
        if (!empty($oldanswers)) {
            foreach($oldanswers as $oa) {
                delete_records('question_answers', 'id', $oa->id);
            }
        }

        $this->save_hints($question);

        /// Perform sanity checks on fractional grades
        if ($maxfraction != 1) {
            $maxfraction = $maxfraction * 100;
            $result->noticeyesno = get_string("fractionsnomax", "quiz", $maxfraction);
            return $result;
        } else {
            return true;
        }
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->usecase = $questiondata->options->usecase;
        $this->initialise_question_answers($question, $questiondata);
    }

    /*
     * Override the parent class method, to remove escaping from asterisks.
     */
    public function get_correct_responses(&$question, &$state) {
        $response = parent::get_correct_responses($question, $state);
        if (is_array($response)) {
            $response[''] = addslashes(str_replace('\*', '*', stripslashes($response[''])));
        }
        return $response;
    }

    function get_random_guess_score($questiondata) {
        foreach ($questiondata->options->answers as $aid => $answer) {
            if ('*' == trim($answer->answer)) {
                return $answer->fraction;
            }
        }
        return 0;
    }

    function get_possible_responses($questiondata) {
        $responses = array();

        foreach ($questiondata->options->answers as $aid => $answer) {
            $r = new stdClass;
            $r->responseclass = $answer->answer;
            $r->fraction = $answer->fraction;
            $responses[$aid] = $r;
        }

        return array($questiondata->id => $responses);
    }

/// RESTORE FUNCTIONS /////////////////

    /*
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    public function restore($old_question_id,$new_question_id,$info,$restore) {

        $status = parent::restore($old_question_id, $new_question_id, $info, $restore);

        if ($status) {
            $extraquestionfields = $this->extra_question_fields();
            $questionextensiontable = array_shift($extraquestionfields);

            //We have to recode the answers field (a list of answers id)
            $questionextradata = get_record($questionextensiontable, $this->questionid_column_name(), $new_question_id);
            if (isset($questionextradata->answers)) {
                $answers_field = "";
                $in_first = true;
                $tok = strtok($questionextradata->answers, ",");
                while ($tok) {
                    // Get the answer from backup_ids
                    $answer = backup_getid($restore->backup_unique_code,"question_answers",$tok);
                    if ($answer) {
                        if ($in_first) {
                            $answers_field .= $answer->new_id;
                            $in_first = false;
                        } else {
                            $answers_field .= ",".$answer->new_id;
                        }
                    }
                    // Check for next
                    $tok = strtok(",");
                }
                // We have the answers field recoded to its new ids
                $questionextradata->answers = $answers_field;
                // Update the question
                $status = $status && update_record($questionextensiontable, $questionextradata);
            }
        }

        return $status;
    }

    /**
     * Runs all the code required to set up and save an essay question for testing purposes.
     * Alternate DB table prefix may be used to facilitate data deletion.
     */
    public function generate_test($name, $courseid = null) {
        list($form, $question) = parent::generate_test($name, $courseid);
        $question->category = $form->category;

        $form->questiontext = "What is the purpose of life, the universe, and everything";
        $form->generalfeedback = "Congratulations, you may have solved my biggest problem!";
        $form->penalty = 0.1;
        $form->usecase = false;
        $form->defaultgrade = 1;
        $form->noanswers = 3;
        $form->answer = array('42', 'who cares?', 'Be happy');
        $form->fraction = array(1, 0.6, 0.8);
        $form->feedback = array('True, but what does that mean?', 'Well you do, dont you?', 'Yes, but thats not funny...');
        $form->correctfeedback = 'Excellent!';
        $form->incorrectfeedback = 'Nope!';
        $form->partiallycorrectfeedback = 'Not bad';

        if ($courseid) {
            $course = get_record('course', 'id', $courseid);
        }

        return $this->save_question($question, $form, $course);
    }
}
