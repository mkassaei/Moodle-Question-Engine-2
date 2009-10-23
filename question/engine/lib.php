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
 * This file the Moodle question engine.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/compatibility.php');
require_once(dirname(__FILE__) . '/renderer.php');
require_once(dirname(__FILE__) . '/testquestiontype.php');


/**
 * This static class provides access to the other question engine classes.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_engine {
    private static $loadedmodels = array();

    /**
     *
     * @param $owningplugin
     * @return question_usage_by_activity
     */
    public static function make_questions_usage_by_activity($owningplugin) {
        return new question_usage_by_activity($owningplugin);
    }

    /**
     * Get the question type class for a particular question type.
     * @param string $typename the question type name. For example 'multichoice' or 'shortanswer'.
     * @return default_questiontype the corresponding question type class.
     */
    public static function get_qtype($typename) {
        global $QTYPES;
        return $QTYPES[$typename];
    }

    public static function load_interaction_model_class($model) {
        global $CFG;
        if (isset(self::$loadedmodels[$model])) {
            return;
        }
        $file = $CFG->dirroot . '/question/interaction/' . $model . '/model.php';
        if (!is_readable($file)) {
            throw new Exception('Unknown question interaction model ' . $model);
        }
        include_once($file);
        self::$loadedmodels[$model] = 1;
    }
}


/**
 * An enumration representing the states a question can be in after a step.
 *
 * With some useful methods to help manipulate states.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_state {
    const NOT_STARTED = -1;
    const UNPROCESSED = 0;
    const INCOMPLETE = 1;
    const COMPLETE = 2;
    const NEEDS_GRADING = 16;
    const FINISHED = 17;
    const GAVE_UP = 18;
    const GRADED_INCORRECT = 24;
    const GRADED_PARTCORRECT = 25;
    const GRADED_CORRECT = 26;
    const FINISHED_COMMENTED = 49;
    const GAVE_UP_COMMENTED = 50;
    const MANUALLY_GRADED_INCORRECT = 56;
    const MANUALLY_GRADED_PARTCORRECT = 57;
    const MANUALLY_GRADED_CORRECT = 58;

    public static function is_active($state) {
        return $state == self::INCOMPLETE || $state == self::COMPLETE;
    }

    public static function is_finished($state) {
        return $state >= self::NEEDS_GRADING;
    }

    public static function is_graded($state) {
        return ($state >= self::GRADED_INCORRECT && $state <= self::GRADED_CORRECT) ||
                ($state >= self::MANUALLY_GRADED_INCORRECT && $state <= self::MANUALLY_GRADED_CORRECT);
    }


    public static function is_commented($state) {
        return $state >= self::FINISHED_COMMENTED;
    }

    public static function graded_state_for_fraction($fraction) {
        if ($fraction < 0.0000001) {
            return self::GRADED_INCORRECT;
        } else if ($fraction > 0.9999999) {
            return self::GRADED_CORRECT;
        } else {
            return self::GRADED_PARTCORRECT;
        }
    }

    public static function manually_graded_state_for_other_state($state, $fraction) {
        $oldstate = $state & 0xFFFFFFDF;
        switch ($oldstate) {
            case self::FINISHED:
                return self::FINISHED_COMMENTED;
            case self::GAVE_UP:
                if (is_null($fraction)) {
                    return self::GAVE_UP_COMMENTED;
                }
                // Else fall through.
            case self::NEEDS_GRADING:
            case self::GRADED_INCORRECT:
            case self::GRADED_PARTCORRECT:
            case self::GRADED_CORRECT:
                return self::graded_state_for_fraction($fraction) + 32;
            default:
                throw new Exception('Illegal state transition.');
        }
    }

    public static function get_feedback_class($state) {
        switch ($state) {
            case self::GRADED_CORRECT:
            case self::MANUALLY_GRADED_CORRECT:
                return 'correct';
            case self::GRADED_PARTCORRECT:
            case self::MANUALLY_GRADED_PARTCORRECT:
                return 'partiallycorrect';
            case self::GRADED_INCORRECT:
            case self::MANUALLY_GRADED_INCORRECT:
            case self::GAVE_UP;
            case self::GAVE_UP_COMMENTED;
                return 'incorrect';
            default:
                return '';
        }
    }
}


/**
 * This class contains the constants and methods required for manipulating scores
 * for certainly based marking.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_cbm {
    const LOW = 1;
    const MED = 2;
    const HIGH = 3;
    const LOW_OFFSET = 0;
    const LOW_FACTOR = 0.333333333333333;
    const MED_OFFSET = -0.666666666666667;
    const MED_FACTOR = 1.333333333333333;
    const HIGH_OFFSET = -2;
    const HIGH_FACTOR = 3;

    public static $certainties = array(self::LOW, self::MED, self::HIGH);

    protected static $factor = array(
        self::LOW => self::LOW_FACTOR,
        self::MED => self::MED_FACTOR,
        self::HIGH => self::HIGH_FACTOR,
    );

    protected static $offset = array(
        self::LOW => self::LOW_OFFSET,
        self::MED => self::MED_OFFSET,
        self::HIGH => self::HIGH_OFFSET,
    );

    public static function adjust_fraction($fraction, $certainty) {
        return self::$offset[$certainty] + self::$factor[$certainty] * $fraction;
    }
}


/**
 * This class contains all the options that controls how a question is displayed.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_display_options {
    public $markdp = 2;
    public $flags = QUESTION_FLAGSSHOWN;
    public $readonly = false;
    public $feedback = false;
    public $correct_responses = false;
    public $generalfeedback = false;
    public $responses = true;
    public $marks = true;
    public $history = false;
    public $manualcommentlink = false; // Set to base URL for true.
}


/**
 * The definition of a question of a particular type.
 *
 * This class matches the question table in the database. It will normally be
 * subclassed by the particular question type.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_definition {
    public $id;
    public $category;
    public $parent = 0;
    public $qtype;
    public $name;
    public $questiontext;
    public $questiontextformat;
    public $generalfeedback = 'You should have selected true.';
    public $defaultmark = 1;
    public $length = 1;
    public $penalty = 0;
    public $stamp;
    public $version;
    public $hidden = 0;
    public $timecreated;
    public $timemodified;
    public $createdb;
    public $modifiedby;

    public function init_first_step(question_attempt_step $step) {
    }
}


/**
 * This class keeps track of a group of questions that are being attempted,
 * and which state each one is currently in.
 *
 * A quiz attempt or a lesson attempt could use an instance of this class to
 * keep track of all the questions in the attempt and process student submissions.
 * It is basically a collection of {@question_attempt} objects.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_usage_by_activity {
    protected $id = null;
    protected $preferredmodel = null;
    protected $owningplugin;
    protected $questionattempts = array();

    public function __construct($owningplugin) {
        $this->owningplugin = $owningplugin;
    }

    public function set_preferred_interaction_model($model) {
        $this->preferredmodel = $model;
    }

    public function get_id() {
        if (is_null($this->id)) {
            $this->id = random_string(10);
        }
        return $this->id;
    }

    public function add_question(question_definition $question) {
        $qa = new question_attempt($question, $this->get_id());
        if (count($this->questionattempts) == 0) {
            $this->questionattempts[1] = $qa;
        } else {
            $this->questionattempts[] = $qa;
        }
        $qa->set_number_in_usage(end(array_keys($this->questionattempts)));
        return $qa->get_number_in_usage();
    }

    public function question_count() {
        return count($this->questionattempts);
    }

    /**
     * @param integer $qnumber
     * @return question_attempt
     */
    public function get_question_attempt($qnumber) {
        if (!array_key_exists($qnumber, $this->questionattempts)) {
            throw new exception("There is no question_attempt number $qnumber in this attempt.");
        }
        return $this->questionattempts[$qnumber];
    }

    public function get_question_state($qnumber) {
        return $this->get_question_attempt($qnumber)->get_state();
    }

    public function get_question_mark($qnumber) {
        return $this->get_question_attempt($qnumber)->get_mark();
    }

    public function render_question($qnumber, $options, $number = null) {
        return $this->get_question_attempt($qnumber)->render($options, $number);
    }

    public function get_field_prefix($qnumber) {
        return $this->get_question_attempt($qnumber)->get_field_prefix();
    }

    public function start_all_questions() {
        foreach ($this->questionattempts as $qa) {
            $qa->start($this->preferredmodel);
        }
    }

    public function extract_responses($qnumber, $postdata) {
        return $this->get_question_attempt($qnumber)->get_submitted_data($postdata);
    }

    public function process_action($qnumber, $submitteddata) {
        $this->get_question_attempt($qnumber)->process_action($submitteddata);
    }

    public function finish_all_questions() {
        foreach ($this->questionattempts as $qa) {
            $qa->finish();
        }
    }

    public function manual_grade($qnumber, $comment, $mark) {
        $this->get_question_attempt($qnumber)->manual_grade($mark, $comment);
    }

    public function regrade_question($qnumber) {
        $oldqa = $this->get_question_attempt($qnumber);
        $newqa = new question_attempt($oldqa->get_question(), $oldqa->get_usage_id());
        $oldfirststep = $oldqa->get_step(0);
        $newqa->regrade($oldqa);
        $this->questionattempts[$qnumber] = $newqa;
    }

    public function regrade_all_questions() {
        foreach ($this->questionattempts as $qnumber => $notused) {
            $this->regrade_question($qnumber);
        }
    }
}

/**
 * Tracks an attempt at one particular question.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt {
    protected $id = null;
    protected $usageid;
    protected $numberinusage = null;
    protected $interactionmodel = null;
    protected $question;
    protected $maxmark;
    protected $minfraction = null;
    protected $responsesummary = '';
    protected $steps = array();
    protected $flagged = false;
    protected $pendingstep = null;

    const KEEP = true;
    const DISCARD = false;

    public function __construct(question_definition $question, $usageid) {
        $this->question = $question;
        $this->usageid = $usageid;
        if (!empty($question->maxmark)) {
            $this->maxmark = $question->maxmark;
        } else {
            $this->maxmark = $question->defaultmark;
        }
    }

    /**
     * @return question_definition
     */
    public function get_question() {
        return $this->question;
    }

    public function set_number_in_usage($qnumber) {
        $this->numberinusage = $qnumber;
    }

    public function get_number_in_usage() {
        return $this->numberinusage;
    }

    public function get_usage_id() {
        return $this->usageid;
    }

    public function set_flagged($flagged) {
        $this->flagged = $flagged;
    }

    public function is_flagged() {
        return $this->flagged;
    }

    public function get_qt_field_name($varname) {
        return $this->get_field_prefix() . $varname;
    }

    public function get_im_field_name($varname) {
        return $this->get_field_prefix() . '!' . $varname;
    }

    public function get_field_prefix() {
        return 'q' . $this->usageid . ',' . $this->numberinusage . '_';
    }

    /**
     * @param integer $i
     * @return question_attempt_step
     */
    public function get_step($i) {
        if ($i < 0 || $i >= count($this->steps)) {
            throw new Exception('Index out of bounds in question_attempt::get_step.');
        }
        return $this->steps[$i];
    }

    public function get_num_steps() {
        return count($this->steps);
    }

    /**
     * @return question_attempt_step
     */
    public function get_last_step() {
        if (count($this->steps) == 0) {
            return new question_null_step();
        }
        return end($this->steps);
    }

    /**
     * @return question_attempt_step_iterator
     */
    public function get_step_iterator() {
        return new question_attempt_step_iterator($this);
    }

    /**
     * @return question_attempt_reverse_step_iterator
     */
    public function get_reverse_step_iterator() {
        return new question_attempt_reverse_step_iterator($this);
    }

    /**
     * Get the latest value of a particular qtype variable. That is, get the value
     * from the latest step that has it set. Return null if it is not set in any step.
     * @param string $name the name of the variable to get.
     * @param mixed default the value to return in the variable has never been set.
     *      (Optional, defaults to null.)
     * @return mixed string value, or $default if it has never been set.
     */
    public function get_last_qt_var($name, $default = null) {
        foreach ($this->get_reverse_step_iterator() as $step) {
            if ($step->has_qt_var($name)) {
                return $step->get_qt_var($name);
            }
        }
        return $default;
    }

    /**
     * Get the latest value of a particular qim variable. That is, get the value
     * from the latest step that has it set. Return null if it is not set in any step.
     * @param string $name the name of the variable to get.
     * @param mixed default the value to return in the variable has never been set.
     *      (Optional, defaults to null.)
     * @return mixed string value, or $default if it has never been set.
     */
    public function get_last_im_var($name, $default = null) {
        foreach ($this->get_reverse_step_iterator() as $step) {
            if ($step->has_im_var($name)) {
                return $step->get_im_var($name);
            }
        }
        return $default;
    }

    public function get_state() {
        return $this->get_last_step()->get_state();
    }

    public function get_fraction() {
        return $this->get_last_step()->get_fraction();
    }

    public function get_mark() {
        $mark = $this->get_fraction();
        if (!is_null($mark)) {
            $mark *= $this->maxmark;
        }
        return $mark;
    }

    public function get_max_mark() {
        return $this->maxmark;
    }

    public function get_min_fraction() {
        if (is_null($this->minfraction)) {
            throw new Exception('This question_attempt has not been started yet, the min fraction is not yet konwn.');
        }
        return $this->minfraction;
    }

    public function format_mark($dp) {
        $mark = $this->get_mark();
        if (is_null($mark)) {
            return '--';
        }
        return round($mark, $dp);
    }

    public function format_max_mark($dp) {
        return round($this->maxmark, $dp);
    }

    public function format_mark_out_of_max($dp) {
        return $this->format_mark($dp) . ' / ' . $this->format_max_mark($dp);
    }

    public function render($options, $number) {
        $qoutput = renderer_factory::get_renderer('core', 'question');
        $qtoutput = $this->question->get_renderer();
        return $this->interactionmodel->render($options, $number, $qoutput, $qtoutput);
    }

    protected function add_step(question_attempt_step $step) {
        $this->steps[] = $step;
    }

    public function start($preferredmodel, $submitteddata = array(), $timestamp = null, $userid = null) {
        if (is_string($preferredmodel)) {
            $this->interactionmodel =
                    $this->question->get_interaction_model($this, $preferredmodel);
        } else {
            $class = get_class($preferredmodel);
            $this->interactionmodel = new $class($this);
        }
        $this->minfraction = $this->interactionmodel->get_min_fraction();
        $firststep = new question_attempt_step($submitteddata, $timestamp, $userid);
        $firststep->set_state(question_state::INCOMPLETE);
        $this->interactionmodel->init_first_step($firststep);
        $this->add_step($firststep);
    }

    protected function get_submitted_var($name, $type, $postdata) {
        if (is_null($postdata)) {
            return optional_param($name, null, $type);
        } else if (array_key_exists($name, $postdata)) {
            return clean_param($postdata[$name], $type);
        } else {
            return null;
        }
    }

    public function get_submitted_data($postdata) {
        $submitteddata = array();
        foreach ($this->interactionmodel->get_expected_data() as $name => $type) {
            $value = $this->get_submitted_var($this->get_im_field_name($name), $type, $postdata);
            if (!is_null($value)) {
                $submitteddata['!' . $name] = $value;
            }
        }
        foreach ($this->question->get_expected_data() as $name => $type) {
            $value = $this->get_submitted_var($this->get_qt_field_name($name), $type, $postdata);
            if (!is_null($value)) {
                $submitteddata[$name] = $value;
            }
        }
        return $submitteddata;
    }

    public function process_action($submitteddata, $timestamp = null, $userid = null) {
        $pendingstep = new question_attempt_step($submitteddata, $timestamp, $userid);
        if ($this->interactionmodel->process_action($pendingstep) == self::KEEP) {
            $this->add_step($pendingstep);
        }
    }

    public function finish($timestamp = null, $userid = null) {
        $this->process_action(array('!finish' => 1), $timestamp, $userid);
    }

    public function regrade(question_attempt $oldqa) {
        $first = true;
        foreach ($oldqa->get_step_iterator() as $step) {
            if ($first) {
                $first = false;
                $this->start($oldqa->interactionmodel, $step->get_qt_data(),
                        $step->get_timecreated(), $step->get_user_id());
            } else {
                $this->process_action($step->get_submitted_data(),
                        $step->get_timecreated(), $step->get_user_id());
            }
        }
    }

    public function manual_grade($comment, $mark, $timestamp = null, $userid = null) {
        $submitteddata = array('!comment' => $comment);
        if (!is_null($mark)) {
            $submitteddata['!mark'] = $mark;
            $submitteddata['!maxmark'] = $this->maxmark;
        }
        $this->process_action($submitteddata, $timestamp, $userid);
    }

    public function has_manual_comment() {
        foreach ($this->steps as $step) {
            if ($step->has_im_var('comment')) {
                return true;
            }
        }
        return false;
    }

    public function get_manual_comment() {
        foreach ($this->get_reverse_step_iterator() as $step) {
            if ($step->has_im_var('comment')) {
                return $step->get_im_var('comment');
            }
        }
        return null;
    }

    /**
     * Create a question_attempt_step from records loaded from the database.
     * @param array $records Raw records loaded from the database.
     * @param integer $questionattemptid The id of the question_attempt to extract.
     * @return question_attempt The newly constructed question_attempt_step.
     */
    public static function load_from_records(&$records, $questionattemptid) {
        $record = current($records);
        while ($record->questionattemptid != $questionattemptid) {
            $record = next($records);
            if (!$record) {
                throw new Exception("Question attempt $questionattemptid not found in the database.");
            }
        }

        // TODO something to do with $record->questionid
        $question = test_question_maker::make_a_description_question();

        $qa = new question_attempt($question, $record->questionusageid);
        $qa->set_number_in_usage($record->numberinusage);
        // TODO something to do with $record->interactionmodel
        // $qa->interactionmodel = ;
        $qa->maxmark = $record->maxmark;
        $qa->minfraction = $record->minfraction;
        $qa->set_flagged($record->flagged);
        $qa->questionsummary = $record->questionsummary;
        $qa->rightanswer = $record->rightanswer;
        $qa->timemodified = $record->timemodified;

        $i = 0;
        while (current($records)) {
            $qa->steps[$i] = question_attempt_step::load_from_records($records, $i);
            $i++;
        }

        return $qa;
    }
}


/**
 * A class abstracting access to the question_attempt::states array.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_step_iterator implements Iterator, ArrayAccess {
    protected $qa;
    protected $i;
    public function __construct(question_attempt $qa) {
        $this->qa = $qa;
        $this->rewind();
    }

    /** @return question_attempt_step */
    public function current() {
        return $this->offsetGet($this->i);
    }
    /** @return integer */
    public function key() {
        return $this->i;
    }
    public function next() {
        ++$this->i;
    }
    public function rewind() {
        $this->i = 0;
    }
    /** @return boolean */
    public function valid() {
        return $this->offsetExists($this->i);
    }

    /** @return boolean */
    public function offsetExists($i) {
        return $i >= 0 && $i < $this->qa->get_num_steps();
    }
    /** @return question_attempt_step */
    public function offsetGet($i) {
        return $this->qa->get_step($i);
    }
    public function offsetSet($offset, $value) {
        throw new Exception('You are only allowed read-only access to question_attempt::states through a question_attempt_step_iterator. Cannot set.');
    }
    public function offsetUnset($offset) {
        throw new Exception('You are only allowed read-only access to question_attempt::states through a question_attempt_step_iterator. Cannot unset.');
    }
}


class question_attempt_reverse_step_iterator extends question_attempt_step_iterator {
    public function next() {
        --$this->i;
    }

    public function rewind() {
        $this->i = $this->qa->get_num_steps() - 1;
    }
}


/**
 * Stores one step in a {@link question_attempt}.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_step {
    private $id = null;
    private $state = question_state::UNPROCESSED;
    private $fraction = null;
    private $timecreated;
    private $userid;
    private $data;

    public function __construct($data = array(), $timecreated = null, $userid = null) {
        global $USER;
        $this->data = $data;
        if (is_null($timecreated)) {
            $this->timecreated = time();
        } else {
            $this->timecreated = $timecreated;
        }
        if (is_null($userid)) {
            $this->userid = $USER->id;
        } else {
            $this->userid = $userid;
        }
    }

    public function get_state() {
        return $this->state;
    }

    public function set_state($state) {
        $this->state = $state;
    }

    public function get_fraction() {
        return $this->fraction;
    }

    public function set_fraction($fraction) {
        $this->fraction = $fraction;
    }

    public function get_user_id() {
        return $this->userid;
    }

    public function get_timecreated() {
        return $this->timecreated;
    }

    public function has_qt_var($name) {
        return array_key_exists($name, $this->data);
    }

    /**
     * @param string $name the name of a question type variable to look for in the submitted data.
     * @return string the requested variable, or null if the variable is not set.
     */
    public function get_qt_var($name) {
        if (!$this->has_qt_var($name)) {
            return null;
        }
        return $this->data[$name];
    }

    public function set_qt_var($name, $value) {
        if ($name[0] != '_') {
            throw new Exception('Cannot set question type data ' . $name . ' on an attempt step. You can only set variables with names begining with _.');
        }
        $this->data[$name] = $value;
    }

    public function get_all_data() {
        return $this->data;
    }

    public function get_qt_data() {
        $result = array();
        foreach ($this->data as $name => $value) {
            if ($name[0] != '!') {
                $result[$name] = $value;
            }
        }
        return $result;
    }

    public function has_im_var($name) {
        return array_key_exists('!' . $name, $this->data);
    }

    /**
     * @param string $name the name of an interaction model variable to look for in the submitted data.
     * @return string the requested variable, or null if the variable is not set.
     */
    public function get_im_var($name) {
        if (!$this->has_im_var($name)) {
            return null;
        }
        return $this->data['!' . $name];
    }

    public function set_im_var($name, $value) {
        if ($name[0] != '_') {
            throw new Exception('Cannot set question type data ' . $name . ' on an attempt step. You can only set variables with names begining with _.');
        }
        return $this->data['!' . $name] = $value;
    }

    public function get_im_data() {
        $result = array();
        foreach ($this->data as $name => $value) {
            if ($name[0] == '!') {
                $result[substr($name, 1)] = $value;
            }
        }
        return $result;
    }

    public function get_submitted_data() {
        $result = array();
        foreach ($this->data as $name => $value) {
            if ($name[0] == '_' || ($name[0] == '!' && $name[1] == '_')) {
                continue;
            }
            $result[$name] = $value;
        }
        return $result;
    }

    /**
     * Create a question_attempt_step from records loaded from the database.
     * @param array $records Raw records loaded from the database.
     * @param integer $stepid The id of the records to extract.
     * @return question_attempt_step The newly constructed question_attempt_step.
     */
    public static function load_from_records(&$records, $sequencenumber) {
        $currentrec = current($records);
        while ($currentrec->sequencenumber != $sequencenumber) {
            $currentrec = next($records);
            if (!$currentrec) {
                throw new Exception("Question attempt step $stepid not found in the database.");
            }
        }

        $record = $currentrec;
        $data = array();
        while ($currentrec && $currentrec->sequencenumber == $sequencenumber) {
            if ($currentrec->name) {
                $data[$currentrec->name] = $currentrec->value;
            }
            $currentrec = next($records);
        }

        $step = new question_attempt_step($data, $record->timecreated, $record->userid);
        $step->set_state($record->state);
        $step->set_fraction($record->fraction);
        return $step;
    }
}


/**
 * A null {@link question_attempt_step} returned from
 * {@link question_attempt::get_last_step()} etc. when a an attempt has just been
 * started and there is no acutal step.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_null_step {
    public function get_state() {
        return question_state::NOT_STARTED;
    }

    public function set_state($state) {
        throw new Exception('This question has not been started.');
    }

    public function get_fraction() {
        return NULL;
    }
}


/**
 * The base class for question interaction models.
 *
 * A question interaction model controls the flow of actions a student can
 * take as they work through a question, and later, as a teacher manually grades it.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_interaction_model {
    /**
     * @var question_attempt
     */
    protected $qa;
    /**
     * @var question_definition
     */
    protected $question;

    public function __construct(question_attempt $qa) {
        $this->qa = $qa;
        $this->question = $qa->get_question();
    }

    public function get_renderer() {
        list($ignored, $type) = explode('_', get_class($this), 3);
        return renderer_factory::get_renderer('qim_' . $type);
    }

    public function adjust_display_options($options) {
        if (question_state::is_finished($this->qa->get_state())) {
            $options->readonly = true;
        }
    }

    public function render($options, $number, $qoutput, $qtoutput) {
        $qimoutput = $this->get_renderer();
        $options = clone($options);
        $this->adjust_display_options($options);
        return $qoutput->question($this->qa, $qimoutput, $qtoutput, $options, $number);
    }

    public function get_min_fraction() {
        return 0;
    }

    public function init_first_step(question_attempt_step $step) {
        $this->question->init_first_step($step);
    }

    /**
     * Return an array of the interaction model variables that could be submitted
     * as part of a question of this type, with their types, so they can be
     * properly cleaned.
     * @return array variable name => PARAM_... constant.
     */
    public function get_expected_data() {
        return array();
    }

    public abstract function process_action(question_attempt_step $pendingstep);

    protected function is_same_response($pendingstep) {
        return $this->question->is_same_response(
                $this->qa->get_last_step()->get_qt_data(), $pendingstep->get_qt_data());
    }

    protected function is_complete_response($pendingstep) {
        return $this->question->is_complete_response($pendingstep->get_qt_data());
    }

    public function process_save(question_attempt_step $pendingstep) {
        if (!question_state::is_active($this->qa->get_state())) {
            throw new Exception('Question is already closed, cannot process_actions.');
        }
        if ($this->is_same_response($pendingstep)) {
            return question_attempt::DISCARD;
        }

        if ($this->is_complete_response($pendingstep)) {
            $pendingstep->set_state(question_state::COMPLETE);
        } else {
            $pendingstep->set_state(question_state::INCOMPLETE);
        }
        return question_attempt::KEEP;
    }

    public function process_comment(question_attempt_step $pendingstep) {
        $laststep = $this->qa->get_last_step();

        if ($pendingstep->has_im_var('mark')) {
            $pendingstep->set_fraction($pendingstep->get_im_var('mark') /
                    $pendingstep->get_im_var('maxmark'));
        }

        $pendingstep->set_state(question_state::manually_graded_state_for_other_state(
                $laststep->get_state(), $pendingstep->get_fraction()));
        return question_attempt::KEEP;
    }
}


