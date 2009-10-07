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
require_once(dirname(__FILE__) . '/../interaction/deferredfeedback/model.php');


/**
 * This static class provides access to the other question engine classes.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_engine {
    /**
     *
     * @param $owningplugin
     * @return question_usage_by_activity
     */
    public static function make_questions_usage_by_activity($owningplugin) {
        return new question_usage_by_activity($owningplugin);
    }

    public static function load_questions_usage_by_activity($attemptid) {

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
}


abstract class question_state {
    const UNPROCESSED = 0;
    const NOT_STARTED = 1;
    const INCOMPLETE = 2;
    const COMPLETE = 3;
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

    public static function graded_state_for_grade($grade) {
        if ($grade < 0.0000001) {
            return self::GRADED_INCORRECT;
        } else if ($grade > 0.9999999) {
            return self::GRADED_CORRECT;
        } else {
            return self::GRADED_PARTCORRECT;
        }
    }

    public static function manually_graded_state_for_other_state($state, $grade) {
        $oldstate = $state & 0xFFFFFFDF;
        switch ($oldstate) {
            case self::FINISHED:
                return FINISHED_COMMENTED;
            case self::GAVE_UP:
                return self::GAVE_UP_COMMENTED;
            case self::GRADED_INCORRECT:
            case self::GRADED_PARTCORRECT:
            case self::GRADED_CORRECT:
                return self::graded_state_for_grade($grade) + 32;
            default:
                throw new Exception('Illegal state transition.');
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
    public $flags = QUESTION_FLAGSSHOWN;
    public $readonly = false;
    public $feedback = false;
    public $correct_responses = false;
    public $generalfeedback = false;
    public $responses = true;
    public $scores = true;
    public $history = false;
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

    public function add_question($question) {
        $qa = new question_attempt($question);
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

    public function get_question_grade($qnumber) {
        return $this->get_question_attempt($qnumber)->get_grade();
    }

    public function render_question($qnumber, $options) {
        return $this->get_question_attempt($qnumber)->render($options);
    }

    public function get_field_prefix($qnumber) {
        $this->get_question_attempt($qnumber); // Validate $qnumber.
        return 'q' . $this->get_id() . ',' . $qnumber . '_';
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

    public function manual_grade($qnumber, $grade, $comment) {
        $this->get_question_attempt($qnumber)->manual_grade($grade, $comment);
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
    private $numberinusage = null;
    private $interactionmodel = null;
    private $question;
    private $qtype;
    private $maxgrade;
    private $responsesummary = '';
    private $states = array();
    private $flagged = false;

    public function __construct($question) {
        $this->question = $question;
        $this->qtype = question_engine::get_qtype($question->qtype);
        if (!empty($question->maxgrade)) {
            $this->maxgrade = $question->maxgrade;
        } else {
            $this->maxgrade = $question->defaultgrade;
        }
    }

    public function set_number_in_usage($qnumber) {
        $this->numberinusage = $qnumber;
    }

    public function get_number_in_usage() {
        return $this->numberinusage;
    }

    public function set_fagged($flagged) {
        $this->flagged = $flagged;
    }

    public function is_flagged() {
        return $this->flagged;
    }

    public function get_last_step() {
        if (count($this->states) == 0) {
            return new question_null_state();
        }
        return end($this->states);
    }

    public function get_state() {
        return $this->get_last_step()->get_state();
    }

    public function get_grade() {
        return $this->get_last_step()->get_grade();
    }

    public function get_question() {
        return $this->question;
    }

    public function get_qtype() {
        return $this->qtype;
    }

    public function render($options) {
        $qoutput = renderer_factory::get_renderer('core', 'question');
        $qimoutput = $this->interactionmodel->get_renderer($this->question);
        $qtoutput = $this->qtype->get_renderer($this->question);
        return $qoutput->question($this, $qimoutput, $qtoutput, $options);
    }

    public function add_state($state) {
        $this->states[] = $state;
    }

    public function start($preferredmodel) {
        $this->interactionmodel =
                $this->qtype->get_interaction_model($preferredmodel);
        $this->add_state($this->interactionmodel->create_initial_state());
    }

    public function process_action($submitteddata) {
        $this->interactionmodel->process_action($this, $submitteddata);
    }

    public function finish() {
        $this->interactionmodel->finish($this);
    }

    public function manual_grade($grade, $comment) {
        $this->interactionmodel->manual_grade($this, $grade, $comment);
    }
}


class question_null_state {
    public function get_state() {
        return question_state::NOT_STARTED;
    }

    public function set_state($state) {
        throw new Exception('This question has not been started.');
    }

    public function get_grade() {
        return NULL;
    }
}


/**
 * Stores one state of a question that is being attempted.
 *
 * @copyright © 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_step {
    private $id = null;
    private $state = question_state::UNPROCESSED;
    private $grade = null;
    private $timestamp;
    private $userid;
    private $responses = array();

    public function __construct($timestamp = null, $userid = null) {
        global $USER;
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

    public function get_response() {
        return $this->responses;
    }

    public function set_response($responses) {
        $this->responses = $responses;
    }

    public function get_grade() {
        return $this->grade;
    }

    public function set_grade($grade) {
        $this->grade = $grade;
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
abstract class question_interaction_model_base {
    public function create_initial_state() {
        $state = new question_attempt_step();
        $state->set_state(question_state::INCOMPLETE);
        return $state;
    }

    public function get_renderer($question) {
        list($ignored, $type) = explode('_', get_class($this), 3);
        return renderer_factory::get_renderer('qim_' . $type);
    }

    public abstract function process_action(question_attempt $qa, array $submitteddata);

    public function process_comment($qa, $submitteddata) {
        $currentstate = $qa->get_last_step();

        $newstate = new question_attempt_step();
        $newstate->set_response($submitteddata);
        if (array_key_exists('!grade', $submitteddata)) {
            $newstate->set_grade($submitteddata['!grade']);
        }
        $newstate->set_state(question_state::manually_graded_state_for_other_state(
                $currentstate->get_state(), $newstate->get_grade()));
        $qa->add_state($newstate);
    }

    public function finish(question_attempt $qa) {
        $this->process_action($qa, array('!finish' => 1));
    }

    public function manual_grade(question_attempt $qa, $grade, $comment) {
        $submitteddata = array('!comment' => $comment);
        if (!is_null($grade)) {
            $submitteddata['!grade'] = $grade;
        }
        $this->process_action($qa, $submitteddata);
    }
}


