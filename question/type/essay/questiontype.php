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
 * Question type class for the essay question type.
 *
 * @package qtype_essay
 * @copyright © 2005 Mark Nielsen
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * The essay question type.
 *
 * @copyright © 2005 Mark Nielsen
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_essay extends question_type {
    function is_manual_graded() {
        return true;
    }

    function is_usable_by_random() {
        return true;
    }

    function save_question_options($question) {
        $result = true;
        $update = true;
        $answer = get_record("question_answers", "question", $question->id);
        if (!$answer) {
            $answer = new stdClass;
            $answer->question = $question->id;
            $update = false;
        }
        $answer->answer   = $question->feedback;
        $answer->feedback = $question->feedback;
        $answer->fraction = $question->fraction;
        if ($update) {
            if (!update_record("question_answers", $answer)) {
                $result = new stdClass;
                $result->error = "Could not update quiz answer!";
            }
        } else {
            if (!$answer->id = insert_record("question_answers", $answer)) {
                $result = new stdClass;
                $result->error = "Could not insert quiz answer!";
            }
        }
        return $result;
    }

    function response_summary($question, $state, $length = 80) {
        $responses = $this->get_actual_response($question, $state);
        $response = reset($responses);
        return shorten_text($response, $length);
    }

    /**
     * Backup the extra information specific to an essay question - over and above
     * what is in the mdl_question table.
     *
     * @param file $bf The backup file to write to.
     * @param object $preferences the blackup options controlling this backup.
     * @param $questionid the id of the question being backed up.
     * @param $level indent level in the backup file - so it can be formatted nicely.
     */
    function backup($bf, $preferences, $questionid, $level = 6) {
        return question_backup_answers($bf, $preferences, $questionid, $level);
    }

    /**
     * Runs all the code required to set up and save an essay question for testing purposes.
     * Alternate DB table prefix may be used to facilitate data deletion.
     */
    function generate_test($name, $courseid = null) {
        list($form, $question) = parent::generate_test($name, $courseid);
        $form->questiontext = "What is the purpose of life?";
        $form->feedback = "feedback";
        $form->generalfeedback = "General feedback";
        $form->fraction = 0;
        $form->penalty = 0;

        if ($courseid) {
            $course = get_record('course', 'id', $courseid);
        }

        return $this->save_question($question, $form, $course);
    }
}
question_register_questiontype(question_bank::get_qtype('essay'));
