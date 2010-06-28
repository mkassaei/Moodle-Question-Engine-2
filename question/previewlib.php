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
 * Helper code for the question preview UI.
 *
 * @package core
 * @subpackage questionbank
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Settings form for the preview options.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview_options_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        $hiddenofvisible = array(
            question_display_options::HIDDEN => get_string('notshown', 'question'),
            question_display_options::VISIBLE => get_string('shown', 'question'),
        );

        $mform->addElement('header', 'optionsheader', get_string('changeoptions', 'question'));

        $mform->addElement('select', 'behaviour', get_string('howquestionsbehave', 'question'),
                question_engine::get_archetypal_behaviours());
        $mform->setHelpButton('behaviour', array('howquestionsbehave', get_string('howquestionsbehave', 'question'), 'question'));

        $mform->addElement('text', 'maxmark', get_string('markedoutof', 'question'), array('size' => '5'));
        $mform->setType('maxmark', PARAM_NUMBER);

        $mform->addElement('select', 'correctness', get_string('whethercorrect', 'question'), $hiddenofvisible);

        $marksoptions = array(
            question_display_options::HIDDEN => get_string('notshown', 'question'),
            question_display_options::MAX_ONLY => get_string('showmaxmarkonly', 'question'),
            question_display_options::MARK_AND_MAX => get_string('showmarkandmax', 'question'),
        );
        $mform->addElement('select', 'marks', get_string('marks', 'question'), $marksoptions);

        $mform->addElement('select', 'markdp', get_string('decimalplacesingrades', 'question'),
                question_engine::get_dp_options());

        $mform->addElement('select', 'feedback', get_string('specificfeedback', 'question'), $hiddenofvisible);

        $mform->addElement('select', 'generalfeedback', get_string('generalfeedback', 'question'), $hiddenofvisible);

        $mform->addElement('select', 'correctresponse', get_string('rightanswer', 'question'), $hiddenofvisible);

        $mform->addElement('select', 'history', get_string('responsehistory', 'question'), $hiddenofvisible);

        $mform->addElement('submit', 'submit', get_string('restartwiththeseoptions', 'question'), $hiddenofvisible);
    }
}

/**
 * Delete the current preview, if any, and redirect to start a new preview.
 * @param integer $previewid
 * @param integer $questionid
 * @param string $preferredbehaviour
 * @param float $maxmark
 * @param integer $markdp
 */
function restart_preview($previewid, $questionid, $preferredbehaviour, $maxmark, $displayoptions) {
    if ($previewid) {
        question_engine::delete_questions_usage_by_activity($previewid);
    }
    redirect(question_preview_url($questionid, $preferredbehaviour, $maxmark, $displayoptions));
}
