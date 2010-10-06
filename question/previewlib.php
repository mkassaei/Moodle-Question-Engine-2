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

        $mform->addElement('select', 'rightanswer', get_string('rightanswer', 'question'), $hiddenofvisible);

        $mform->addElement('select', 'history', get_string('responsehistory', 'question'), $hiddenofvisible);

        $mform->addElement('submit', 'submit', get_string('restartwiththeseoptions', 'question'), $hiddenofvisible);
    }
}


/**
 * Displays question preview options as default and set the options
 * Setting default, getiing and setting user preferences in question preview options.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_display_preview_options extends question_display_options {

    // Use the prefix to easily identify the names  in user_preferences table
    private $optionsprefix = 'question_preview_options_';

    //Set to true when the user have question disp
    private $firsttimeuser = false;
    /**
     * 
     * @param $newoptions
     * @return unknown_type
     */
    function __construct($newoptions = false) {
        if ($newoptions) {
            $this->set_user_preview_options($newoptions);
        }
    }

    /**
     * Returns an assosiative array with default values
     * @param void
     * @return array , an assosiative array with default values
     */
    private function get_default_display_options() {
        global $CFG;
        return array(
            'correctness' => parent::VISIBLE,
            'marks' => parent::MARK_AND_MAX,
            'markdp' => $CFG->quiz_decimalpoints,
            'feedback' => parent::VISIBLE,
            'generalfeedback' => parent::VISIBLE,
            'rightanswer' => parent::VISIBLE,
            'history' => parent::HIDDEN
        );
    }

    /**
     * Returns an assosiative array with user preferences in question preview options
     * @param void
     * @return array, an assosiative array with question preview options user preferences
     */
    private function get_user_preview_options() {
        $userpreferences = array();
        $prefixlength = strlen($this->optionsprefix);
        foreach (get_user_preferences() as $key=>$value) {
            if (substr($key, 0, $prefixlength) == $this->optionsprefix) {
                $userpreferences[substr($key, $prefixlength)]= $value;
            }
        }
        return $userpreferences;
    }

    /**
     * Sets question preview options as user preferences
     * @param array $newoptions
     * @return void
     */
    private function set_user_preview_options($newoptions) {
        // Get option keys and add the missing ones
        $optionkeys = array_keys($this->get_default_display_options());
        $optionkeys[] = 'behaviour';
        $optionkeys[] = 'maxmark';

        // Set user preferences
        $userpreferences = array();
        foreach ($newoptions as $key=>$value) {
            if (in_array($key, $optionkeys)) {
                $userpreferences[$this->optionsprefix . $key] = $value;
            }
        }
        set_user_preferences($userpreferences);
    }

    /**
     * Returns an assosiative array of question preview options from 
     * user preferences if set, otherwise default
     * @param void
     * @return array, true when user log in for the first time, false otherwise
     */
    public function get_preview_options() {
        // Get use preview options
        $userpreviewoptions = $this->get_user_preview_options();
        if (count($userpreviewoptions) == 0) {
            $this->firsttimeuser = true;
            return $this->get_default_display_options();
        }
        return $userpreviewoptions;
    }

    /**
     * Returns true if the user is a first time user and false when the user has 
     * already question preview options in user preferences
     * @param void
     * @return boolean, true when user log in for the first time, false otherwise
     */
    public function is_first_time_user() {
        return $this->firsttimeuser;
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
