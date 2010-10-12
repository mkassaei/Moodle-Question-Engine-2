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
 * Setting default, getting and setting user preferences in question preview options.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_preview_options extends question_display_options {
    /** @var string the behaviour to use for this preview. */
    public $behaviour;

    /** @var number the maximum mark to use for this preview. */
    public $maxmark;

    /** @var string prefix to append to field names to get user_preference names. */
    const OPTIONPREFIX = 'question_preview_options_';

    /**
     * Constructor.
     */
    public function __construct($question) {
        global $CFG;
        $this->behaviour = 'deferredfeedback';
        $this->maxmark = $question->defaultmark;
        $this->correctness = self::VISIBLE;
        $this->marks = self::MARK_AND_MAX;
        $this->markdp = $CFG->quiz_decimalpoints;
        $this->feedback = self::VISIBLE;
        $this->generalfeedback = self::VISIBLE;
        $this->rightanswer = self::VISIBLE;
        $this->history = self::HIDDEN;
        $this->flags = self::HIDDEN;
        $this->manualcomment = self::HIDDEN;
    }

    /**
     * @return array names of the options we store in the user preferences table.
     */
    protected function get_user_pref_fields() {
        return array('behaviour', 'correctness', 'marks', 'markdp', 'feedback',
                'generalfeedback', 'rightanswer', 'history');
    }

    /**
     * @return array names and param types of the options we read from the request.
     */
    protected function get_field_types() {
        return array(
            'behaviour' => PARAM_ALPHA,
            'maxmark' => PARAM_NUMBER,
            'correctness' => PARAM_BOOL,
            'marks' => PARAM_INT,
            'markdp' => PARAM_INT,
            'feedback' => PARAM_BOOL,
            'generalfeedback' => PARAM_BOOL,
            'rightanswer' => PARAM_BOOL,
            'history' => PARAM_BOOL,
        );
    }

    /**
     * Load the value of the options from the user_preferences table.
     */
    public function load_user_defaults() {
        foreach ($this->get_user_pref_fields() as $field) {
            $this->$field = get_user_preferences(
                    self::OPTIONPREFIX . $field, $this->$field);
        }
    }

    /**
     * Save a change to the user's preview options to the database.
     * @param object $newoptions
     */
    public function save_user_preview_options($newoptions) {
        foreach ($this->get_user_pref_fields() as $field) {
            if (isset($newoptions->$field)) {
                set_user_preference(self::OPTIONPREFIX . $field, $newoptions->$field);
            }
        }
    }

    /**
     * Set the value of any fields included in the request.
     */
    public function set_from_request() {
        foreach ($this->get_field_types() as $field => $type) {
            $this->$field = optional_param($field, $this->$field, $type);
        }
    }

    /**
     * @return string URL fragment. Parameters needed in the URL when continuing
     * this preview.
     */
    public function get_query_string() {
        $querystring = array();
        foreach ($this->get_field_types() as $field => $notused) {
            if ($field == 'behaviour' || $field == 'maxmark') {
                continue;
            }
            $querystring[] = $field . '=' . $this->$field;
        }
        return implode('&', $querystring);
    }
}


/**
 * The the URL to use for actions relating to this preview.
 * @param integer $questionid the question being previewed.
 * @param integer $qubaid the id of the question usage for this preview.
 * @param question_preview_options $options the options in use.
 */
function question_preview_action_url($questionid, $qubaid,
        question_preview_options $options) {
    global $CFG;
    $url = $CFG->wwwroot . '/question/preview.php?id=' . $questionid . '&previewid=' . $qubaid;
    return $url . '&' . $options->get_query_string();
}

/**
 * Delete the current preview, if any, and redirect to start a new preview.
 * @param integer $previewid
 * @param integer $questionid
 * @param object $displayoptions
 */
function restart_preview($previewid, $questionid, $displayoptions) {
    if ($previewid) {
        question_engine::delete_questions_usage_by_activity($previewid);
    }
    redirect(question_preview_url($questionid, $displayoptions->behaviour, $displayoptions->maxmark, $displayoptions));
}
