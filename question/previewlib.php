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
    function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'optionsheader', get_string('changeoptions', 'question'));

        $mform->addElement('select', 'model', get_string('howquestionsbehave', 'question'),
                question_engine::get_archetypal_interaction_models());
        $mform->setHelpButton('model', array('howquestionsbehave', get_string('howquestionsbehave', 'question'), 'question'));

        $mform->addElement('text', 'maxmark', get_string('markedoutof', 'question'), array('size' => '5'));
        $mform->setType('maxmark', PARAM_NUMBER);

        $mform->addElement('select', 'markdp', get_string('decimalplacesingrades', 'question'),
                question_engine::get_dp_options());

        // TODO Other fields from http://moodle.org/mod/forum/discuss.php?d=134156#p595000

        $mform->addElement('submit', 'submit', get_string('restartwiththeseoptions', 'question'));
    }
}

/**
 * Generate the URL for starting a new preview of a given question with the given options.
 * @param integer $questionid
 * @param string $preferredmodel
 * @param fload $maxmark
 * @param integer $markdp
 * @return string the URL.
 */
function restart_url($questionid, $preferredmodel, $maxmark, $markdp) {
    global $CFG;
    return $CFG->wwwroot . '/question/preview.php?id=' . $questionid . '&model=' .
                $preferredmodel . '&maxmark=' . $maxmark . '&markdp=' . $markdp;
}

/**
 * Delete the current preview, if any, and redirect to start a new preview.
 * @param integer $previewid
 * @param integer $questionid
 * @param string $preferredmodel
 * @param float $maxmark
 * @param integer $markdp
 */
function restart_preview($previewid, $questionid, $preferredmodel, $maxmark, $markdp) {
    if ($previewid) {
        question_engine::delete_questions_usage_by_activity($previewid);
    }
    redirect(restart_url($questionid, $preferredmodel, $maxmark, $markdp));
}
