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
 * Defines the editing form for the true-false question type.
 *
 * @package qtype_truefalse
 * @copyright &copy; 2007 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * True-false question editing form definition.
 *
 * @copyright &copy; 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_edit_truefalse_form extends question_edit_form {
    /**
     * Add question-type specific form fields.
     *
     * @param object $mform the form being built.
     */
    protected function definition_inner($mform) {
        $mform->addElement('select', 'correctanswer', get_string('correctanswer', 'qtype_truefalse'),
                array(0 => get_string('false', 'qtype_truefalse'), 1 => get_string('true', 'qtype_truefalse')));

        $mform->addElement('htmleditor', 'feedbacktrue', get_string('feedbacktrue', 'qtype_truefalse'),
                                array('course' => $this->coursefilesid));;
        $mform->setType('feedbacktrue', PARAM_RAW);

        $mform->addElement('htmleditor', 'feedbackfalse', get_string('feedbackfalse', 'qtype_truefalse'),
                                array('course' => $this->coursefilesid));
        $mform->setType('feedbackfalse', PARAM_RAW);

        $mform->addElement('header', 'multitriesheader', get_string('settingsformultipletries', 'question'));

        $mform->addElement('hidden', 'penalty', 1);

        $mform->addElement('static', 'penaltymessage', get_string('penaltyforeachincorrecttry', 'question'), 1);
        $mform->setHelpButton('penaltymessage', array('penalty', get_string('penaltyforeachincorrecttry', 'question'), 'question'));
    }

    public function set_data($question) {
        if (!empty($question->options->trueanswer)) {
            $trueanswer = $question->options->answers[$question->options->trueanswer];
            $question->correctanswer = ($trueanswer->fraction != 0);
            $question->feedbacktrue = $trueanswer->feedback;
            $question->feedbackfalse = $question->options->answers[$question->options->falseanswer]->feedback;
        }
        parent::set_data($question);
    }

    public function qtype() {
        return 'truefalse';
    }
}
