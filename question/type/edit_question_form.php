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
 * A base class for question editing forms.
 *
 * @package moodlecore
 * @subpackage questiontypes
 * @copyright &copy; 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


/**
 * Form definition base class. This defines the common fields that
 * all question types need. Question types should define their own
 * class that inherits from this one, and implements the definition_inner()
 * method.
 *
 * @copyright &copy; 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
class question_edit_form extends moodleform {
    const DEFAULT_NUM_HINTS = 2;

    /**
     * Question object with options and answers already loaded by get_question_options
     * Be careful how you use this it is needed sometimes to set up the structure of the
     * form in definition_inner but data is always loaded into the form with set_data.
     * @var object
     */
    protected $question;

    protected $contexts;
    protected $category;
    protected $categorycontext;
    protected $coursefilesid;

    public function __construct($submiturl, $question, $category, $contexts, $formeditable = true) {
        $this->question = $question;
        $this->contexts = $contexts;
        $this->category = $category;
        $this->categorycontext = get_context_instance_by_id($category->contextid);

        //course id or site id depending on question cat context
        $this->coursefilesid =  get_filesdir_from_context(get_context_instance_by_id($category->contextid));

        parent::__construct($submiturl, null, 'post', '', null, $formeditable);

    }

    /**
     * Build the form definition.
     *
     * This adds all the form fields that the default question type supports.
     * If your question type does not support all these fields, then you can
     * override this method and remove the ones you don't want with $mform->removeElement().
     */
    public function definition() {
        global $COURSE, $CFG;

        $qtype = $this->qtype();
        $langfile = "qtype_$qtype";

        $mform = $this->_form;

        // Standard fields at the start of the form.
        $mform->addElement('header', 'generalheader', get_string("general", 'form'));

        if (!isset($this->question->id)) {
            //adding question
            $mform->addElement('questioncategory', 'category', get_string('category', 'question'),
                    array('contexts' => $this->contexts->having_cap('moodle/question:add')));
        } elseif (!($this->question->formoptions->canmove || $this->question->formoptions->cansaveasnew)) {
            //editing question with no permission to move from category.
            $mform->addElement('questioncategory', 'category', get_string('category', 'question'),
                    array('contexts' => array($this->categorycontext)));
        } elseif ($this->question->formoptions->movecontext) {
            //moving question to another context.
            $mform->addElement('questioncategory', 'categorymoveto', get_string('category', 'question'),
                    array('contexts' => $this->contexts->having_cap('moodle/question:add')));

        } else {
            //editing question with permission to move from category or save as new q
            $currentgrp = array();
            $currentgrp[0] =& $mform->createElement('questioncategory', 'category', get_string('categorycurrent', 'question'),
                    array('contexts' => array($this->categorycontext)));
            if ($this->question->formoptions->canedit || $this->question->formoptions->cansaveasnew) {
                //not move only form
                $currentgrp[1] =& $mform->createElement('checkbox', 'usecurrentcat', '', get_string('categorycurrentuse', 'question'));
                $mform->setDefault('usecurrentcat', 1);
            }
            $currentgrp[0]->freeze();
            $currentgrp[0]->setPersistantFreeze(false);
            $mform->addGroup($currentgrp, 'currentgrp', get_string('categorycurrent', 'question'), null, false);

            $mform->addElement('questioncategory', 'categorymoveto', get_string('categorymoveto', 'question'),
                    array('contexts' => array($this->categorycontext)));
            if ($this->question->formoptions->canedit || $this->question->formoptions->cansaveasnew) {
                //not move only form
                $mform->disabledIf('categorymoveto', 'usecurrentcat', 'checked');
            }
        }

        $mform->addElement('text', 'name', get_string('questionname', 'question'), array('size' => 50));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('htmleditor', 'questiontext', get_string('questiontext', 'question'),
                array('rows' => 15, 'course' => $this->coursefilesid));
        $mform->setType('questiontext', PARAM_RAW);
        $mform->setHelpButton('questiontext', array(array('questiontext', get_string('questiontext', 'question'), 'question'), 'richtext'), false, 'editorhelpbutton');
        $mform->addElement('format', 'questiontextformat', get_string('format'));

        $mform->addElement('text', 'defaultmark', get_string('defaultmark', 'question'),
                array('size' => 3));
        $mform->setType('defaultmark', PARAM_INT);
        $mform->setDefault('defaultmark', 1);
        $mform->addRule('defaultmark', null, 'required', null, 'client');

        $mform->addElement('htmleditor', 'generalfeedback', get_string('generalfeedback', 'question'),
                array('rows' => 10, 'course' => $this->coursefilesid));
        $mform->setType('generalfeedback', PARAM_RAW);
        $mform->setHelpButton('generalfeedback', array('generalfeedback', get_string('generalfeedback', 'question'), 'question'));

        // Any questiontype specific fields.
        $this->definition_inner($mform);

        if (!empty($this->question->id)) {
            $mform->addElement('header', 'createdmodifiedheader', get_string('createdmodifiedheader', 'question'));
            $a = new object();
            if (!empty($this->question->createdby)) {
                $a->time = userdate($this->question->timecreated);
                $a->user = fullname(get_record('user', 'id', $this->question->createdby));
            } else {
                $a->time = get_string('unknown', 'question');
                $a->user = get_string('unknown', 'question');
            }
            $mform->addElement('static', 'created', get_string('created', 'question'), get_string('byandon', 'question', $a));
            if (!empty($this->question->modifiedby)) {
                $a = new object();
                $a->time = userdate($this->question->timemodified);
                $a->user = fullname(get_record('user', 'id', $this->question->modifiedby));
                $mform->addElement('static', 'modified', get_string('modified', 'question'), get_string('byandon', 'question', $a));
            }
        }

        // Standard fields at the end of the form.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'qtype');
        $mform->setType('qtype', PARAM_ALPHA);

        $mform->addElement('hidden', 'inpopup');
        $mform->setType('inpopup', PARAM_INT);

        $mform->addElement('hidden', 'versioning');
        $mform->setType('versioning', PARAM_BOOL);

        $mform->addElement('hidden', 'movecontext');
        $mform->setType('movecontext', PARAM_BOOL);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->setDefault('cmid', 0);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', 0);

        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);
        $mform->setDefault('returnurl', 0);

        $buttonarray = array();
        if (!empty($this->question->id)) {
            //editing / moving question
            if ($this->question->formoptions->movecontext) {
                $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('moveq', 'question'));
            } elseif ($this->question->formoptions->canedit || $this->question->formoptions->canmove ||$this->question->formoptions->movecontext) {
                $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
            }
            if ($this->question->formoptions->cansaveasnew) {
                $buttonarray[] = &$mform->createElement('submit', 'makecopy', get_string('makecopy', 'question'));
            }
            $buttonarray[] = &$mform->createElement('cancel');
        } else {
            // adding new question
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
            $buttonarray[] = &$mform->createElement('cancel');
        }
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');

        if ($this->question->formoptions->movecontext) {
            $mform->hardFreezeAllVisibleExcept(array('categorymoveto', 'buttonar'));
        } elseif ((!empty($this->question->id)) && (!($this->question->formoptions->canedit || $this->question->formoptions->cansaveasnew))) {
            $mform->hardFreezeAllVisibleExcept(array('categorymoveto', 'buttonar', 'currentgrp'));
        }
    }

    /**
     * Add any question-type specific form fields.
     *
     * @param object $mform the form being built.
     */
    protected function definition_inner($mform) {
        // By default, do nothing.
    }

    /**
     * Get the list of form elements to repeat, one for each answer.
     * @param object $mform the form being built.
     * @param $label the label to use for each option.
     * @param $gradeoptions the possible grades for each answer.
     * @param $repeatedoptions reference to array of repeated options to fill
     * @param $answersoption reference to return the name of $question->options field holding an array of answers
     * @return array of form fields.
     */
    protected function get_per_answer_fields(&$mform, $label, $gradeoptions, &$repeatedoptions, &$answersoption) {
        $repeated = array();
        $repeated[] =& $mform->createElement('header', 'answerhdr', $label);
        $repeated[] =& $mform->createElement('text', 'answer', get_string('answer', 'question'), array('size' => 50));
        $repeated[] =& $mform->createElement('select', 'fraction', get_string('grade'), $gradeoptions);
        $repeated[] =& $mform->createElement('htmleditor', 'feedback', get_string('feedback', 'question'),
                                array('course' => $this->coursefilesid));
        $repeatedoptions['answer']['type'] = PARAM_RAW;
        $repeatedoptions['fraction']['default'] = 0;
        $answersoption = 'answers';
        return $repeated;
    }

    /**
     * Add a set of form fields, obtained from get_per_answer_fields, to the form,
     * one for each existing answer, with some blanks for some new ones.
     * @param object $mform the form being built.
     * @param $label the label to use for each option.
     * @param $gradeoptions the possible grades for each answer.
     * @param $minoptions the minimum number of answer blanks to display. Default QUESTION_NUMANS_START.
     * @param $addoptions the number of answer blanks to add. Default QUESTION_NUMANS_ADD.
     */
    protected function add_per_answer_fields(&$mform, $label, $gradeoptions, $minoptions = QUESTION_NUMANS_START, $addoptions = QUESTION_NUMANS_ADD) {
        $answersoption = '';
        $repeatedoptions = array();
        $repeated = $this->get_per_answer_fields($mform, $label, $gradeoptions, $repeatedoptions, $answersoption);

        if (isset($this->question->options)) {
            $countanswers = count($this->question->options->$answersoption);
        } else {
            $countanswers = 0;
        }
        if ($this->question->formoptions->repeatelements) {
            $repeatsatstart = max($minoptions, $countanswers + $addoptions);
        } else {
            $repeatsatstart = $countanswers;
        }

        $this->repeat_elements($repeated, $repeatsatstart, $repeatedoptions, 'noanswers', 'addanswers', $addoptions, get_string('addmorechoiceblanks', 'qtype_multichoice'));
    }

    protected function add_combined_feedback_fields($withshownumpartscorrect = false) {
        $mform = $this->_form;

        $mform->addElement('header', 'combinedfeedbackhdr', get_string('combinedfeedback', 'question'));

        foreach (array('correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback') as $feedbackname) {
            $mform->addElement('htmleditor', $feedbackname, get_string($feedbackname, 'question'),
                                array('course' => $this->coursefilesid));
            $mform->setType($feedbackname, PARAM_RAW);

            if ($withshownumpartscorrect && $feedbackname == 'partiallycorrectfeedback') {
                $mform->addElement('checkbox', 'shownumcorrect', get_string('options', 'question'), get_string('shownumpartscorrect', 'question'));
            }
        }
    }

    protected function get_hint_fields($withclearwrong = false, $withshownumpartscorrect = false) {
        $mform = $this->_form;

        $repeated = array();
        $repeated[] = $mform->createElement('header', 'answerhdr', get_string('hintn', 'question'));
        $repeated[] = $mform->createElement('htmleditor', 'hint', get_string('hinttext', 'question'), array('size' => 50));
        $repeatedoptions['hint']['type'] = PARAM_RAW;

        if ($withclearwrong) {
            $repeated[] = $mform->createElement('checkbox', 'hintclearwrong', get_string('options', 'question'), get_string('clearwrongparts', 'question'));
        }
        if ($withshownumpartscorrect) {
            $repeated[] = $mform->createElement('checkbox', 'hintshownumcorrect', '', get_string('shownumpartscorrect', 'question'));
        }

        return array($repeated, $repeatedoptions);
    }

    protected function add_interactive_settings($withclearwrong = false, $withshownumpartscorrect = false) {
        $mform = $this->_form;

        $mform->addElement('header', 'multitriesheader', get_string('settingsformultipletries', 'question'));

        $penalties = array(
            1.0000000,
            0.5000000,
            0.3333333,
            0.2500000,
            0.2000000,
            0.1000000,
            0.0000000
        );
        if (!empty($this->question->penalty) && !in_array($this->question->penalty, $penalties)) {
            $penalties[] = $this->question->penalty;
            sort($penalties);
        }
        $penaltyoptions = array();
        foreach ($penalties as $penalty) {
            $penaltyoptions["$penalty"] = (100 * $penalty) . '%';
        }
        $mform->addElement('select', 'penalty', get_string('penaltyforeachincorrecttry', 'question'),
                $penaltyoptions);
        $mform->addRule('penalty', null, 'required', null, 'client');
        $mform->setHelpButton('penalty', array('penalty', get_string('penaltyforeachincorrecttry', 'question'), 'question'));
        $mform->setDefault('penalty', 0.3333333);

        if (isset($this->question->hints)) {
            $counthints = count($this->question->hints);
        } else {
            $counthints = 0;
        }

        if ($this->question->formoptions->repeatelements) {
            $repeatsatstart = max(self::DEFAULT_NUM_HINTS, $counthints);
        } else {
            $repeatsatstart = $counthints;
        }

        list($repeated, $repeatedoptions) = $this->get_hint_fields(
                $withclearwrong, $withshownumpartscorrect);
        $this->repeat_elements($repeated, $repeatsatstart, $repeatedoptions,
                'numhints', 'addhint', 1, get_string('addanotherhint', 'question'));
    }

    public function set_data($question) {
        global $QTYPES;
        $QTYPES[$question->qtype]->set_default_options($question);

        // Set any options.
        $extra_question_fields = $QTYPES[$question->qtype]->extra_question_fields();
        if (is_array($extra_question_fields) && !empty($question->options)) {
            array_shift($extra_question_fields);
            foreach ($extra_question_fields as $field) {
                if (isset($question->options->$field)) {
                    $question->$field = $question->options->$field;
                }
            }
        }

        if (!empty($question->hints)) {
            $i = 0;
            foreach ($question->hints as $hint) {
                $question->hint[$i] = $hint->hint;
                $question->hintclearwrong[$i] = !empty($hint->clearwrong);
                $question->hintshownumcorrect[$i] = !empty($hint->shownumcorrect);
                $i += 1;
            }
        }

        parent::set_data($question);
    }

    public function validation($fromform, $files) {
        $errors = parent::validation($fromform, $files);
        if (empty($fromform->makecopy) && isset($this->question->id) 
                && ($this->question->formoptions->canedit || $this->question->formoptions->cansaveasnew) 
                && empty($fromform->usecurrentcat) && !$this->question->formoptions->canmove) {
            $errors['currentgrp'] = get_string('nopermissionmove', 'question');
        }
        return $errors;
    }
    

    /**
     * Override this in the subclass to question type name.
     * @return the question type name, should be the same as the name() method in the question type class.
     */
    public function qtype() {
        return '';
    }
}
