<?php // $Id$

///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//          http://moodle.org                                            //
//                                                                       //
// Copyright (C) 1999 onwards Martin Dougiamas  http://dougiamas.com     //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 2 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

MoodleQuickForm::registerElementType('duration', "$CFG->libdir/form/duration.php", 'MoodleQuickForm_duration');

/**
 * Settings form for the quiz module.
 * 
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package quiz
 */
class mod_quiz_mod_form extends moodleform_mod {
    var $_feedbacks;

    protected static $reviewfields = array(); // Initialised in the constructor.

    public function __construct($instance, $section, $cm) {
        self::$reviewfields = array(
            'attempt' => get_string('theattempt', 'quiz'),
            'correctness' => get_string('whethercorrect', 'question'),
            'marks' => get_string('marks', 'question'),
            'specificfeedback' => get_string('specificfeedback', 'question'),
            'generalfeedback' => get_string('generalfeedback', 'question'),
            'rightanswer' => get_string('rightanswer', 'question'),
            'overallfeedback' => get_string('overallfeedback', 'quiz'),
        );
        parent::__construct($instance, $section, $cm);
    }

    function definition() {

        global $COURSE, $CFG;
        $mform =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

    /// Name.
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');

    /// Introduction.
        $mform->addElement('htmleditor', 'intro', get_string('introduction', 'quiz'));
        $mform->setType('intro', PARAM_RAW);
        $mform->setHelpButton('intro', array('richtext2', get_string('helprichtext')));

        $mform->addElement('format', 'introformat', get_string('format'));

    /// Open and close dates.
        $mform->addElement('date_time_selector', 'timeopen', get_string('quizopen', 'quiz'),
                array('optional' => true, 'step' => 1));
        $mform->setHelpButton('timeopen', array('timeopen', get_string('quizopen', 'quiz'), 'quiz'));

        $mform->addElement('date_time_selector', 'timeclose', get_string('quizclose', 'quiz'),
                array('optional' => true, 'step' => 1));
        $mform->setHelpButton('timeclose', array('timeopen', get_string('quizclose', 'quiz'), 'quiz'));

    /// Time limit.
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'quiz'), array('optional' => true));
        $mform->setHelpButton('timelimit', array('timelimit', get_string('quiztimer','quiz'), 'quiz'));
        $mform->setAdvanced('timelimit', $CFG->quiz_fix_timelimit);
        $mform->setDefault('timelimit', $CFG->quiz_timelimit);

    /// Number of attempts.
        $attemptoptions = array('0' => get_string('unlimited'));
        for ($i = 1; $i <= QUIZ_MAX_ATTEMPT_OPTION; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('select', 'attempts', get_string('attemptsallowed', 'quiz'), $attemptoptions);
        $mform->setHelpButton('attempts', array('attempts', get_string('attemptsallowed','quiz'), 'quiz'));
        $mform->setAdvanced('attempts', $CFG->quiz_fix_attempts);
        $mform->setDefault('attempts', $CFG->quiz_attempts);

    /// Grading method.
        $mform->addElement('select', 'grademethod', get_string('grademethod', 'quiz'), quiz_get_grading_options());
        $mform->setHelpButton('grademethod', array('grademethod', get_string('grademethod','quiz'), 'quiz'));
        $mform->setAdvanced('grademethod', $CFG->quiz_fix_grademethod);
        $mform->setDefault('grademethod', $CFG->quiz_grademethod);
        $mform->disabledIf('grademethod', 'attempts', 'eq', 1);

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'layouthdr', get_string('layout', 'quiz'));

    /// Shuffle questions.
        $shuffleoptions = array(0 => get_string('asshownoneditscreen', 'quiz'), 1 => get_string('shuffledrandomly', 'quiz'));
        $mform->addElement('select', 'shufflequestions', get_string('questionorder', 'quiz'), $shuffleoptions, array('id' => 'id_shufflequestions'));
        $mform->setHelpButton('shufflequestions', array('shufflequestions', get_string('shufflequestions','quiz'), 'quiz'));
        $mform->setAdvanced('shufflequestions', $CFG->quiz_fix_shufflequestions);
        $mform->setDefault('shufflequestions', $CFG->quiz_shufflequestions);

    /// Questions per page.
        $pageoptions = array();
        $pageoptions[0] = get_string('neverallononepage', 'quiz');
        $pageoptions[1] = get_string('everyquestion', 'quiz');
        for ($i = 2; $i <= QUIZ_MAX_QPP_OPTION; ++$i) {
            $pageoptions[$i] = get_string('everynquestions', 'quiz', $i);
        }

        $pagegroup = array();
        $pagegroup[] = &$mform->createElement('select', 'questionsperpage', get_string('newpage', 'quiz'), $pageoptions, array('id' => 'id_questionsperpage'));
        $mform->setDefault('questionsperpage', $CFG->quiz_questionsperpage);

        if (!empty($this->_cm)) {
            $pagegroup[] = &$mform->createElement('checkbox', 'repaginatenow', '', get_string('repaginatenow', 'quiz'), array('id' => 'id_repaginatenow'));
            $mform->disabledIf('repaginatenow', 'shufflequestions', 'eq', 1);
            require_js(array('yui_yahoo', 'yui_dom', 'yui_event'));
            require_js($CFG->wwwroot . '/mod/quiz/edit.js');
        }

        $mform->addGroup($pagegroup, 'questionsperpagegrp', get_string('newpage', 'quiz'), null, false);
        $mform->setHelpButton('questionsperpagegrp', array('questionsperpage', get_string('newpageevery', 'quiz'), 'quiz'));
        $mform->setAdvanced('questionsperpagegrp', $CFG->quiz_fix_questionsperpage);

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'interactionhdr', get_string('questionbehaviour', 'quiz'));

    /// Shuffle within questions.
        $mform->addElement('selectyesno', 'shuffleanswers', get_string('shufflewithin', 'quiz'));
        $mform->setHelpButton('shuffleanswers', array('shufflewithin', get_string('shufflewithin','quiz'), 'quiz'));
        $mform->setAdvanced('shuffleanswers', $CFG->quiz_fix_shuffleanswers);
        $mform->setDefault('shuffleanswers', $CFG->quiz_shuffleanswers);

    /// How questions behave (question behaviour).
        $behaviours = question_engine::get_archetypal_behaviours();
        $mform->addElement('select', 'preferredbehaviour', get_string('howquestionsbehave', 'question'), $behaviours);
        $mform->setHelpButton('preferredbehaviour', array('howquestionsbehave', get_string('howquestionsbehave','question'), 'question'));
        $mform->setAdvanced('preferredbehaviour', $CFG->quiz_fix_preferredbehaviour);
        $mform->setDefault('preferredbehaviour', $CFG->quiz_preferredbehaviour);

    /// Each attempt builds on last.
        $mform->addElement('selectyesno', 'attemptonlast', get_string('eachattemptbuildsonthelast', 'quiz'));
        $mform->setHelpButton('attemptonlast', array('repeatattempts', get_string('eachattemptbuildsonthelast', 'quiz'), 'quiz'));
        $mform->setAdvanced('attemptonlast', $CFG->quiz_fix_attemptonlast);
        $mform->setDefault('attemptonlast', $CFG->quiz_attemptonlast);
        $mform->disabledIf('attemptonlast', 'attempts', 'eq', 1);

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'reviewoptionshdr', get_string('reviewoptionsheading', 'quiz'));
        $mform->setHelpButton('reviewoptionshdr', array('reviewoptions', get_string('reviewoptionsheading','quiz'), 'quiz'));
        $mform->setAdvanced('reviewoptionshdr', $CFG->quiz_fix_review);

    /// Review options.
        $this->add_review_options_group($mform, 'during', mod_quiz_display_options::DURING);
        $this->add_review_options_group($mform, 'immediately', mod_quiz_display_options::IMMEDIATELY_AFTER);
        $this->add_review_options_group($mform, 'open', mod_quiz_display_options::LATER_WHILE_OPEN);
        $this->add_review_options_group($mform, 'closed', mod_quiz_display_options::AFTER_CLOSE);

        foreach ($behaviours as $behaviour => $notused) {
            $unusedoptions = question_engine::get_behaviour_unused_display_options($behaviour);
            foreach ($unusedoptions as $unusedoption) {
                $mform->disabledIf($unusedoption . 'during', 'preferredbehaviour',
                        'eq', $behaviour);
            }
        }
        $mform->disabledIf('attemptduring', 'preferredbehaviour',
                'neq', 'wontmatch');
        $mform->disabledIf('overallfeedbackduring', 'preferredbehaviour',
                'neq', 'wontmatch');

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'display', get_string('display', 'form'));

    /// Show user picture.
        $mform->addElement('selectyesno', 'showuserpicture', get_string('showuserpicture', 'quiz'));
        $mform->setHelpButton('showuserpicture', array('showuserpicture', get_string('showuserpicture', 'quiz'), 'quiz'));
        $mform->setAdvanced('showuserpicture', $CFG->quiz_fix_showuserpicture);
        $mform->setDefault('showuserpicture', $CFG->quiz_showuserpicture);

    /// Overall decimal points.
        $options = array();
        for ($i = 0; $i <= QUIZ_MAX_DECIMAL_OPTION; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'decimalpoints', get_string('decimalplaces', 'quiz'), $options);
        $mform->setHelpButton('decimalpoints', array('decimalpoints', get_string('decimalplaces','quiz'), 'quiz'));
        $mform->setAdvanced('decimalpoints', $CFG->quiz_fix_decimalpoints);
        $mform->setDefault('decimalpoints', $CFG->quiz_decimalpoints);

    /// Question decimal points.
        $options = array(-1 => get_string('sameasoverall', 'quiz'));
        for ($i = 0; $i <= QUIZ_MAX_Q_DECIMAL_OPTION; $i++) {
            $options[$i] = $i;
        }
        $mform->addElement('select', 'questiondecimalpoints', get_string('decimalplacesquestion', 'quiz'), $options);
        $mform->setHelpButton('questiondecimalpoints', array('decimalplacesquestion', get_string('decimalplacesquestion','quiz'), 'quiz'));
        $mform->setAdvanced('questiondecimalpoints', $CFG->quiz_fix_questiondecimalpoints);
        $mform->setDefault('questiondecimalpoints', $CFG->quiz_questiondecimalpoints);

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'security', get_string('extraattemptrestrictions', 'quiz'));

    /// Enforced time delay between quiz attempts.
        $mform->addElement('passwordunmask', 'quizpassword', get_string('requirepassword', 'quiz'));
        $mform->setType('quizpassword', PARAM_TEXT);
        $mform->setHelpButton('quizpassword', array('requirepassword', get_string('requirepassword', 'quiz'), 'quiz'));
        $mform->setAdvanced('quizpassword', $CFG->quiz_fix_password);
        $mform->setDefault('quizpassword', $CFG->quiz_password);

    /// IP address.
        $mform->addElement('text', 'subnet', get_string('requiresubnet', 'quiz'));
        $mform->setType('subnet', PARAM_TEXT);
        $mform->setHelpButton('subnet', array('requiresubnet', get_string('requiresubnet', 'quiz'), 'quiz'));
        $mform->setAdvanced('subnet', $CFG->quiz_fix_subnet);
        $mform->setDefault('subnet', $CFG->quiz_subnet);

    /// Enforced time delay between quiz attempts.
        $mform->addElement('duration', 'delay1', get_string('delay1st2nd', 'quiz'), array('optional' => true));
        $mform->setHelpButton('delay1', array('timedelay1', get_string('delay1st2nd', 'quiz'), 'quiz'));
        $mform->setAdvanced('delay1', $CFG->quiz_fix_delay1);
        $mform->setDefault('delay1', $CFG->quiz_delay1);
        $mform->disabledIf('delay1', 'attempts', 'eq', 1);

        $mform->addElement('duration', 'delay2', get_string('delaylater', 'quiz'), array('optional' => true));
        $mform->setHelpButton('delay2', array('timedelay2', get_string('delaylater', 'quiz'), 'quiz'));
        $mform->setAdvanced('delay2', $CFG->quiz_fix_delay2);
        $mform->setDefault('delay2', $CFG->quiz_delay2);
        $mform->disabledIf('delay2', 'attempts', 'eq', 1);
        $mform->disabledIf('delay2', 'attempts', 'eq', 2);

    /// 'Secure' window.
        $mform->addElement('selectyesno', 'popup', get_string('showinsecurepopup', 'quiz'));
        $mform->setHelpButton('popup', array('popup', get_string('showinsecurepopup', 'quiz'), 'quiz'));
        $mform->setAdvanced('popup', $CFG->quiz_fix_popup);
        $mform->setDefault('popup', $CFG->quiz_popup);

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'overallfeedbackhdr', get_string('overallfeedback', 'quiz'));
        $mform->setHelpButton('overallfeedbackhdr', array('overallfeedback', get_string('overallfeedback', 'quiz'), 'quiz'));

        $mform->addElement('hidden', 'grade', $CFG->quiz_maximumgrade);
        if (empty($this->_cm)) {
            $needwarning = $CFG->quiz_maximumgrade == 0;
        } else {
            $quizgrade = get_field('quiz', 'grade', 'id', $this->_instance);
            $needwarning = $quizgrade == 0;
        }
        if ($needwarning) {
            $mform->addElement('static', 'nogradewarning', '', get_string('nogradewarning', 'quiz'));
        }

        $mform->addElement('static', 'gradeboundarystatic1', get_string('gradeboundary', 'quiz'), '100%');

        $repeatarray = array();
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'feedbacktext', get_string('feedback', 'quiz'), array('size' => 50));
        $mform->setType('feedbacktext', PARAM_RAW);
        $repeatarray[] = &MoodleQuickForm::createElement('text', 'feedbackboundaries', get_string('gradeboundary', 'quiz'), array('size' => 10));
        $mform->setType('feedbackboundaries', PARAM_NOTAGS);

        if (!empty($this->_instance)) {
            $this->_feedbacks = get_records('quiz_feedback', 'quizid', $this->_instance, 'mingrade DESC');
        } else {
            $this->_feedbacks = array();
        }
        $numfeedbacks = max(count($this->_feedbacks) * 1.5, 5);

        $nextel=$this->repeat_elements($repeatarray, $numfeedbacks - 1,
                array(), 'boundary_repeats', 'boundary_add_fields', 3,
                get_string('addmoreoverallfeedbacks', 'quiz'), true);

        // Put some extra elements in before the button
        $insertEl = &MoodleQuickForm::createElement('text', "feedbacktext[$nextel]", get_string('feedback', 'quiz'), array('size' => 50));
        $mform->insertElementBefore($insertEl, 'boundary_add_fields');

        $insertEl = &MoodleQuickForm::createElement('static', 'gradeboundarystatic2', get_string('gradeboundary', 'quiz'), '0%');
        $mform->insertElementBefore($insertEl, 'boundary_add_fields');

        // Add the disabledif rules. We cannot do this using the $repeatoptions parameter to
        // repeat_elements becuase we don't want to dissable the first feedbacktext.
        for ($i = 0; $i < $nextel; $i++) {
            $mform->disabledIf('feedbackboundaries[' . $i . ']', 'grade', 'eq', 0);
            $mform->disabledIf('feedbacktext[' . ($i + 1) . ']', 'grade', 'eq', 0);
        }

//-------------------------------------------------------------------------------
        $features = new stdClass;
        $features->groups = true;
        $features->groupings = true;
        $features->groupmembersonly = true;
        $this->standard_coursemodule_elements($features);

//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    protected function add_review_options_group($mform, $whenname, $when) {
        global $CFG;

        $group = array();
        foreach (self::$reviewfields as $field => $label) {
            $group[] = $mform->createElement('checkbox', $field . $whenname, '', $label);
        }
        $mform->addGroup($group, $whenname . 'optionsgrp', get_string('review' . $whenname, 'quiz'), null, false);

        foreach (self::$reviewfields as $field => $notused) {
            $cfgfield = 'quiz_review' . $field;
            if ($CFG->$cfgfield & $when) {
                $mform->setDefault($field . $whenname, 1);
            } else {
                $mform->setDefault($field . $whenname, 0);
            }
        }

        $mform->disabledIf('correctness' . $whenname, 'attempt' . $whenname);
        $mform->disabledIf('specificfeedback' . $whenname, 'attempt' . $whenname);
        $mform->disabledIf('generalfeedback' . $whenname, 'attempt' . $whenname);
        $mform->disabledIf('rightanswer' . $whenname, 'attempt' . $whenname);
    }

    protected function preprocessing_review_settings(&$toform, $whenname, $when) {
        foreach (self::$reviewfields as $field => $notused) {
            $fieldname = 'review' . $field;
            if (array_key_exists($fieldname, $toform)) {
                $toform[$field . $whenname] = $toform[$fieldname] & $when;
            }
        }
    }

    function data_preprocessing(&$toform) {
        if (isset($toform['grade'])) {
            $toform['grade'] = $toform['grade'] + 0; // Convert to a real number, so we don't get 0.0000.
        }

        if (count($this->_feedbacks)) {
            $key = 0;
            foreach ($this->_feedbacks as $feedback){
                $toform['feedbacktext['.$key.']'] = $feedback->feedbacktext;
                if ($feedback->mingrade > 0) {
                    $toform['feedbackboundaries['.$key.']'] = (100.0 * $feedback->mingrade / $toform['grade']) . '%';
                }
                $key++;
            }
        }

        if (isset($toform['timelimit'])) {
            $toform['timelimitenable'] = $toform['timelimit'] > 0;
        }

        $this->preprocessing_review_settings($toform, 'during', mod_quiz_display_options::DURING);
        $this->preprocessing_review_settings($toform, 'immediately', mod_quiz_display_options::IMMEDIATELY_AFTER);
        $this->preprocessing_review_settings($toform, 'open', mod_quiz_display_options::LATER_WHILE_OPEN);
        $this->preprocessing_review_settings($toform, 'closed', mod_quiz_display_options::AFTER_CLOSE);
        $toform['attemptduring'] = true;
        $toform['overallfeedbackduring'] = false;

        // Password field - different in form to stop browsers that remember
        // passwords from getting confused.
        if (isset($toform['password'])) {
            $toform['quizpassword'] = $toform['password'];
            unset($toform['password']);
        }
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check open and close times are consistent.
        if ($data['timeopen'] != 0 && $data['timeclose'] != 0 && $data['timeclose'] < $data['timeopen']) {
            $errors['timeclose'] = get_string('closebeforeopen', 'quiz');
        }

        // Check the boundary value is a number or a percentage, and in range.
        $i = 0;
        while (!empty($data['feedbackboundaries'][$i] )) {
            $boundary = trim($data['feedbackboundaries'][$i]);
            if (strlen($boundary) > 0 && $boundary[strlen($boundary) - 1] == '%') {
                $boundary = trim(substr($boundary, 0, -1));
                if (is_numeric($boundary)) {
                    $boundary = $boundary * $data['grade'] / 100.0;
                } else {
                    $errors["feedbackboundaries[$i]"] = get_string('feedbackerrorboundaryformat', 'quiz', $i + 1);
                }
            }
            if (is_numeric($boundary) && $boundary <= 0 || $boundary >= $data['grade'] ) {
                $errors["feedbackboundaries[$i]"] = get_string('feedbackerrorboundaryoutofrange', 'quiz', $i + 1);
            }
            if (is_numeric($boundary) && $i > 0 && $boundary >= $data['feedbackboundaries'][$i - 1]) {
                $errors["feedbackboundaries[$i]"] = get_string('feedbackerrororder', 'quiz', $i + 1);
            }
            $data['feedbackboundaries'][$i] = $boundary;
            $i += 1;
        }
        $numboundaries = $i;

        // Check there is nothing in the remaining unused fields.
        if (!empty($data['feedbackboundaries'])) {
            for ($i = $numboundaries; $i < count($data['feedbackboundaries']); $i += 1) {
                if (!empty($data['feedbackboundaries'][$i] ) && trim($data['feedbackboundaries'][$i] ) != '') {
                    $errors["feedbackboundaries[$i]"] = get_string('feedbackerrorjunkinboundary', 'quiz', $i + 1);
                }
            }
        }
        for ($i = $numboundaries + 1; $i < count($data['feedbacktext']); $i += 1) {
            if (!empty($data['feedbacktext'][$i] ) && trim($data['feedbacktext'][$i] ) != '') {
                $errors["feedbacktext[$i]"] = get_string('feedbackerrorjunkinfeedback', 'quiz', $i + 1);
            }
        }

        return $errors;
    }

}
