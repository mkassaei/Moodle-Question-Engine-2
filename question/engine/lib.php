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
 * @copyright © 2006 The Open University
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
        return !in_array($state,
                array(self::NOT_STARTED, self::INCOMPLETE, self::COMPLETE));
    }

    public static function is_graded($state) {
        return ($state >= self::GRADED_INCORRECT && $state >= self::GRADED_CORRECT) ||
                ($state >= self::MANUALLY_GRADED_INCORRECT && $state >= self::MANUALLY_GRADED_CORRECT);
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
                return FINISHED_COMMENTED;
            case self::GAVE_UP:
                return self::GAVE_UP_COMMENTED;
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
 * This class contains all the options that controls how a question is displayed.
 *
 * @copyright © 2006 The Open University
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
 * @copyright © 2006 The Open University
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
        $prefix = $this->get_field_prefix($qnumber);
        $prefixlen = strlen($prefix);
        $submitteddata = array();
        foreach ($postdata as $name => $value) {
            if (substr($name, 0, $prefixlen) == $prefix) {
                $submitteddata[substr($name, $prefixlen)] = $value;
            }
        }
        return $submitteddata;
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
        $newqa->start($this->preferredmodel); // TODO handle things like random seed.
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
    private $id = null;
    private $usageid;
    private $numberinusage = null;
    private $interactionmodel = null;
    private $question;
    private $maxmark;
    private $minfraction;
    private $responsesummary = '';
    private $steps = array();
    private $flagged = false;
    private $pendingstep = null;

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

    public function get_step($i) {
        if ($i < 0 || $i >= count($this->steps)) {
            throw new Exception('Index out of bounds in question_attempt::get_step.');
        }
        return $this->steps[$i];
    }

    public function get_num_steps() {
        return count($this->steps);
    }

    public function get_last_step() {
        if (count($this->steps) == 0) {
            return new question_null_step();
        }
        return end($this->steps);
    }

    public function get_step_iterator() {
        return new question_attempt_step_iterator($this);
    }

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
                return $step->has_qt_var($name);
            }
        }
        return $default;
    }

    public function get_state() {
        return $this->get_last_step()->get_state();
    }

    public function get_mark() {
        $mark = $this->get_last_step()->get_fraction();
        if (!is_null($mark)) {
            $mark *= $this->maxmark;
        }
        return $mark;
    }

    public function get_max_mark() {
        return $this->maxmark;
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
        $qimoutput = $this->interactionmodel->get_renderer();
        $qtoutput = $this->question->get_renderer();
        return $qoutput->question($this, $qimoutput, $qtoutput, $options, $number);
    }

    protected function add_step(question_attempt_step $step) {
        $this->steps[] = $step;
    }

    public function start($preferredmodel) {
        $this->interactionmodel =
                $this->question->get_interaction_model($this, $preferredmodel);
        $this->minfraction = $this->interactionmodel->get_min_fraction();
        $firststep = new question_attempt_step();
        $firststep->set_state(question_state::INCOMPLETE);
        $this->interactionmodel->init_first_step($firststep);
        $this->add_step($firststep);
    }

    public function process_action($submitteddata) {
        $pendingstep = new question_attempt_step($submitteddata);
        if ($this->interactionmodel->process_action($pendingstep) == self::KEEP) {
            $this->add_step($pendingstep);
        }
    }

    public function finish() {
        $this->process_action(array('!finish' => 1));
    }

    public function regrade(question_attempt $oldqa) {
        foreach ($oldqa->get_step_iterator() as $step) {
            $this->process_action($step->get_submitted_data());
        }
    }

    public function manual_grade($comment, $mark) {
        $submitteddata = array('!comment' => $comment);
        if (!is_null($mark)) {
            $submitteddata['!mark'] = $mark;
            $submitteddata['!maxmark'] = $this->maxmark;
        }
        $this->process_action($submitteddata);
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
}


/**
 * A class abstracting access to the question_attempt::states array.
 *
 * @copyright © 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_step_iterator implements Iterator, ArrayAccess {
    protected $qa;
    protected $i;
    public function __construct(question_attempt $qa) {
        $this->qa = $qa;
        $this->rewind();
    }

    public function current() {
        return $this->offsetGet($this->i);
    }
    public function key() {
        return $this->i;
    }
    public function next() {
        ++$this->i;
    }
    public function rewind() {
        $this->i = 0;
    }
    public function valid() {
        return $this->offsetExists($this->i);
    }

    public function offsetExists($i) {
        return $i >= 0 && $i < $this->qa->get_num_steps();
    }
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
 * @copyright © 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_step {
    private $id = null;
    private $state = question_state::UNPROCESSED;
    private $fraction = null;
    private $timestamp;
    private $userid;
    private $data;

    public function __construct($data = array(), $timestamp = null, $userid = null) {
        global $USER;
        $this->data = $data;
        if (is_null($timestamp)) {
            $this->timestamp = time();
        } else {
            $this->timestamp = $timestamp;
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

    public function get_timestamp() {
        return $this->timestamp;
    }

    public function has_qt_var($name) {
        return array_key_exists($name, $this->data);
    }

    public function get_qt_var($name) {
        if (!$this->has_qt_var($name)) {
            throw new Exception('Unknown variable ' . $name . ' in submitted question type data.');
        }
        return $this->data[$name];
    }

    public function set_qt_var($name, $value) {
        if ($name[0] != '_') {
            throw new Exception('Cannot set question type data ' . $name . ' on an attempt step. You can only set variables with names begining with _.');
        }
        $this->data[$name] = $value;
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

    public function get_im_var($name) {
        if (!$this->has_im_var($name)) {
            throw new Exception('Unknown variable ' . $name . ' in submitted iteraction model data.');
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
}


/**
 * A null {@link question_attempt_step} returned from
 * {@link question_attempt::get_last_step()} etc. when a an attempt has just been
 * started and there is no acutal step.
 *
 * @copyright © 2006 The Open University
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
    protected $qa;
    protected $question;

    public function __construct(question_attempt $qa) {
        $this->qa = $qa;
        $this->question = $qa->get_question();
    }

    public function get_renderer() {
        list($ignored, $type) = explode('_', get_class($this), 3);
        return renderer_factory::get_renderer('qim_' . $type);
    }

    public function get_min_fraction() {
        return 0;
    }

    public function init_first_step(question_attempt_step $step) {
    }

    public abstract function process_action(question_attempt_step $pendingstep);

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


