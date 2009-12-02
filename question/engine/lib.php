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
 * This defines the core classes of the Moodle question engine.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/datalib.php');
require_once(dirname(__FILE__) . '/renderer.php');
require_once(dirname(__FILE__) . '/../type/questiontype.php');
require_once(dirname(__FILE__) . '/../type/questionbase.php');
require_once(dirname(__FILE__) . '/../type/rendererbase.php');
require_once(dirname(__FILE__) . '/../interaction/modelbase.php');
require_once(dirname(__FILE__) . '/../interaction/rendererbase.php');

require_once(dirname(__FILE__) . '/compatibility.php');
require_once(dirname(__FILE__) . '/testquestiontype.php');


/**
 * This static class provides access to the other question engine classes.
 *
 * It provides functions for managing the various types of plugins (question
 * types and interaction models), and for creating, loading, saving and deleting
 * {@link question_usage_by_activity}s, which is the main class that is used by
 * other code that wants to use questions.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_engine {
    /** @var array question type name => question_type subclass. */
    private static $questiontypes = array();

    /** @var array question type name => 1. Records which question definitions have been loaded. */
    private static $loadedqdefs = array(
        'multichoice' => 1,
    );
    /** @var array interaction model name => 1. Records which interaction models have been loaded. */
    private static $loadedmodels = array();

    /**
     * Create a new {@link question_usage_by_activity}. The usage is
     * created in memory. If you want it to persist, you will need to call
     * {@link save_questions_usage_by_activity()}.
     *
     * @param string $owningplugin the plugin creating this attempt. For example mod_quiz.
     * @param object $context the context this usage belongs to.
     * @return question_usage_by_activity the newly created object.
     */
    public static function make_questions_usage_by_activity($owningplugin, $context) {
        return new question_usage_by_activity($owningplugin, $context);
    }

    /**
     * Load a {@link question_usage_by_activity} from the database, based on its id.
     * @param integer $qubaid the id of the usage to load.
     * @return question_usage_by_activity loaded from the database.
     */
    public static function load_questions_usage_by_activity($qubaid) {
        $dm = new question_engine_data_mapper();
        return $dm->load_questions_usage_by_activity($qubaid);
    }

    /**
     * Save a {@link question_usage_by_activity} to the database. This works either
     * if the usage was newly created by {@link make_questions_usage_by_activity()}
     * or loaded from the database using {@link load_questions_usage_by_activity()}
     * @param question_usage_by_activity the usage to save.
     */
    public static function save_questions_usage_by_activity(question_usage_by_activity $quba) {
        $observer = $quba->get_observer();
        if ($observer instanceof question_engine_unit_of_work) {
            $observer->save();
        } else {
            $dm = new question_engine_data_mapper();
            $dm->insert_questions_usage_by_activity($quba);
        }
    }

    /**
     * Delete a {@link question_usage_by_activity} from the database, based on its id.
     * @param integer $qubaid the id of the usage to delete.
     */
    public static function delete_questions_usage_by_activity($qubaid) {
        $dm = new question_engine_data_mapper();
        $dm->delete_questions_usage_by_activity($qubaid);
    }

    /**
     * Load a question definition from the database. The object returned
     * will actually be of an appropriate {@link question_definition} subclass.
     * @param integer $questionid the id of the question to load.
     * @return question_definition loaded from the database.
     */
    public static function load_question($questionid) {
        $questiondata = get_record('question', 'id', $questionid);
        if (empty($questiondata)) {
            throw new Exception('Unknown question id ' . $questionid);
        }
        get_question_options($questiondata);
        return self::get_qtype($questiondata->qtype)->make_question($questiondata);
    }

    /**
     * Get the question type class for a particular question type.
     * @param string $qtypename the question type name. For example 'multichoice' or 'shortanswer'.
     * @return question_type the corresponding question type class.
     */
    public static function get_qtype($qtypename) {
        global $CFG;
        if (isset(self::$questiontypes[$qtypename])) {
            return self::$questiontypes[$qtypename];
        }
        $file = $CFG->dirroot . '/question/type/' . $qtypename . '/questiontype.php';
        if (!is_readable($file)) {
            throw new Exception('Unknown question type ' . $qtypename);
        }
        include_once($file);
        $class = 'qtype_' . $qtypename;
        self::$questiontypes[$qtypename] = new $class();
        return self::$questiontypes[$qtypename];
    }

    /**
     * Load the question definition class(es) belonging to a question type. That is,
     * include_once('/question/type/' . $qtypename . '/question.php'), with a bit
     * of checking.
     * @param string $qtypename the question type name. For example 'multichoice' or 'shortanswer'.
     */
    public static function load_question_definition_classes($qtypename) {
        global $CFG;
        if (isset(self::$loadedqdefs[$qtypename])) {
            return;
        }
        $file = $CFG->dirroot . '/question/type/' . $qtypename . '/question.php';
        if (!is_readable($file)) {
            throw new Exception('Unknown question type (no definition) ' . $qtypename);
        }
        include_once($file);
        self::$loadedqdefs[$qtypename] = 1;
    }

    /**
     * Create an archetypal interaction model for a particular question attempt.
     * Used by {@link question_definition::make_interaction_model()}.
     *
     * @param string $preferredmodel the type of model required.
     * @param question_attempt $qa the question attempt the model will process.
     * @return question_interaction_model an instance of appropriate interaction model class.
     */
    public static function make_archetypal_interaction_model($preferredmodel, question_attempt $qa) {
        question_engine::load_interaction_model_class($preferredmodel);
        $class = 'qim_' . $preferredmodel;
        if (!constant($class . '::IS_ARCHETYPAL')) {
            throw new Exception('The requested interaction model is not actually an archetypal one.');
        }
        return new $class($qa);
    }

    /**
     * Load the interaction model class(es) belonging to a particular model. That is,
     * include_once('/question/interaction/' . $model . '/model.php'), with a bit
     * of checking.
     * @param string $qtypename the question type name. For example 'multichoice' or 'shortanswer'.
     */
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

    /**
     * Return an array where the keys are the internal names of the archetypal
     * interaction models, and the values are a human-readable name. An
     * archetypal interaction model is one that is suitable to pass the name of to
     * {@link question_usage_by_activity::set_preferred_interaction_model()}.
     *
     * @return array model name => lang string for this model name.
     */
    public static function get_archetypal_interaction_models() {
        $archetypes = array();
        $models = get_list_of_plugins('question/interaction');
        foreach ($models as $path) {
            $model = basename($path);
            self::load_interaction_model_class($model);
            $plugin = 'qim_' . $model;
            if (constant($plugin . '::IS_ARCHETYPAL')) {
                $archetypes[$model] = self::get_interaction_model_name($model);
            }
        }
        asort($archetypes, SORT_LOCALE_STRING);
        return $archetypes;
    }

    /**
     * Get the translated name of an interaction model, for display in the UI.
     * @param string $model the internal name of the model.
     * @return string name from the current language pack.
     */
    public static function get_interaction_model_name($model) {
        return get_string($model, 'qim_' . $model);
    }

    /**
     * Returns the valid choices for the number of decimal places for showing
     * question marks. For use in the user interface.
     * @return array suitable for passing to {@link choose_from_menu()} or similar.
     */
    public static function get_dp_options() {
        return question_display_options::get_dp_options();
    }
}


/**
 * An enumration representing the states a question can be in after a
 * {@link question_attempt_step}.
 *
 * There are also some useful methods for testing and manipulating states.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_state {
    /**#@+
     * @var integer a state the question can be in.
     */
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
    /**#@-*/

    /**
     * Is this state one of the ones that mean the question attempt is in progress?
     * That is, started, but no finished.
     * @param integer $state one of the state constants.
     * @return boolean
     */
    public static function is_active($state) {
        return $state == self::INCOMPLETE || $state == self::COMPLETE;
    }

    /**
     * Is this state one of the ones that mean the question attempt is finished?
     * That is, not furthre interaction possible, apart from manual grading.
     * @param $state
     * @return boolean
     */
    public static function is_finished($state) {
        return $state >= self::NEEDS_GRADING;
    }

    /**
     * Is this state one of the ones that mean the question attempt has been graded?
     * @param integer $state one of the state constants.
     * @return boolean
     */
    public static function is_graded($state) {
        return ($state >= self::GRADED_INCORRECT && $state <= self::GRADED_CORRECT) ||
                ($state >= self::MANUALLY_GRADED_INCORRECT && $state <= self::MANUALLY_GRADED_CORRECT);
    }

    /**
     * Is this state one of the ones that mean the question attempt has had a manual comment added?
     * @param integer $state one of the state constants.
     * @return boolean
     */
    public static function is_commented($state) {
        return $state >= self::FINISHED_COMMENTED;
    }

    /**
     * Return the appropriate graded state based on a fraction. That is 0 or less
     * is GRADED_INCORRECT, 1 is GRADED_CORRECT, otherwise it is GRADED_PARTCORRECT.
     * Appropriate allowance is made for rounding float values.
     *
     * @param number $fraction the grade, on the fraction scale.
     * @return integer one of the state constants.
     */
    public static function graded_state_for_fraction($fraction) {
        if ($fraction < 0.0000001) {
            return self::GRADED_INCORRECT;
        } else if ($fraction > 0.9999999) {
            return self::GRADED_CORRECT;
        } else {
            return self::GRADED_PARTCORRECT;
        }
    }

    /**
     * Compute an appropriate state to move to after a manual comment has been
     * added to another state.
     * @param integer $state the starting state.
     * @param number $fraction the manual grade (if any) on the fraction scale.
     * @return integer the new state.
     */
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

    /**
     * Return an appropriate CSS class name ''/'correct'/'partiallycorrect'/'incorrect',
     * for a state.
     * @param $state one of the state constants.
     * @return string
     */
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

    /**
     * Return an appropriate string from the language pack for a state. This is
     * used, for example, by {@link qim_renderer::get_state_string()}. However,
     * some interaction models sometimes change this default string for
     * soemthing more specific.
     *
     * @param integer $state one of the state constants.
     * @return string a string from the lang pack that can be used in the UI.
     */
    public static function default_string($state) {
        switch ($state) {
            case self::INCOMPLETE;
                return get_string('notyetanswered', 'question');
            case self::COMPLETE;
                return get_string('answersaved', 'question');
            case self::NEEDS_GRADING;
                return get_string('requiresgrading', 'question');
            case self::FINISHED;
            case self::FINISHED_COMMENTED;
                return get_string('complete', 'question');
            case self::GAVE_UP;
            case self::GAVE_UP_COMMENTED;
                return get_string('notanswered', 'question');
            case self::GRADED_INCORRECT:
            case self::MANUALLY_GRADED_INCORRECT:
                return get_string('incorrect', 'question');
            case self::GRADED_PARTCORRECT:
            case self::MANUALLY_GRADED_PARTCORRECT:
                return get_string('partiallycorrect', 'question');
            case self::GRADED_CORRECT:
            case self::MANUALLY_GRADED_CORRECT:
                return get_string('correct', 'question');
            default:
                throw new Exception('Unknown question state.');
        }
    }
}


/**
 * This class contains all the options that controls how a question is displayed.
 *
 * Normally, what will happen is that the calling code will set up some display
 * options to indicate what sort of question display it wants, and then before the
 * question is rendered, the interaction model will be given a chance to modify the
 * display options, so that, for example, A question that is finished will only
 * be shown read-only, and a question that has not been submitted will not have
 * any sort of feedback displayed.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_display_options {
    /**#@+ @var integer named constants for the values that most of the options take. */
    const HIDDEN = 0;
    const VISIBLE = 1;
    const EDITABLE = 2;
    /**#@-*/

    /**#@+ @var integer named constants for the {@link $marks} option. */
    const MAX_ONLY = 1;
    const MARK_AND_MAX = 2;
    /**#@-*/

    /**
     * @var integer maximum value for the {@link $markpd} option. This is
     * effectively set by the database structure, which uses NUMBER(12,7) columns
     * for question marks/fractions.
     */
    const MAX_DP = 7;

    /**
     * @var boolean whether the question should be displayed as a read-only review,
     * or in an active state where you can change the answer.
     */
    public $readonly = false;

    /**
     * @var integer {@link question_display_options::HIDDEN} or {@link question_display_options::VISIBLE}
     */
    public $responses = self::VISIBLE;
    /**TODO
     * @var unknown_type
     */
    public $correctness = self::VISIBLE;
    public $feedback = self::VISIBLE;
    public $generalfeedback = self::VISIBLE;
    public $correctresponse = self::VISIBLE;
    public $marks = self::MARK_AND_MAX;
    public $markdp = 2;
    public $manualcomment = self::VISIBLE;
    public $history = self::HIDDEN;
    public $flags = self::VISIBLE;

    /**
     * Initialise an instance of this class from the kind of bitmask values stored
     * in the quiz.review fields in the databas.
     *
     * This function probably does not belong here.
     *
     * @param integer $bitmask a review options bitmask from the quiz module.
     */
    public function set_review_options($bitmask) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        $this->responses = ($bitmask & QUIZ_REVIEW_RESPONSES) != 0;
        $this->feedback = ($bitmask & QUIZ_REVIEW_FEEDBACK) != 0;
        $this->generalfeedback = ($bitmask & QUIZ_REVIEW_GENERALFEEDBACK) != 0;
        $this->marks = self::MARK_AND_MAX * (($bitmask & QUIZ_REVIEW_SCORES) != 0);
        $this->correctresponse = ($bitmask & QUIZ_REVIEW_ANSWERS) != 0;
    }

    /**
     * Set all the feedback-related fields {@link $feedback}, {@link generalfeedback},
     * {@link correctresponse} and {@link manualcomment} to
     * {@link question_display_options::HIDDEN}.
     */
    public function hide_all_feedback() {
        $this->feedback = self::HIDDEN;
        $this->generalfeedback = self::HIDDEN;
        $this->correctresponse = self::HIDDEN;
        $this->manualcomment = self::HIDDEN;
    }

    /**
     * @return boolean Whether the link to manually grade this question should be shown.
     */
    public function can_edit_comment() {
        return is_string($this->manualcomment);
    }

    /**
     * Returns the valid choices for the number of decimal places for showing
     * question marks. For use in the user interface.
     *
     * Calling code should probably use {@link question_engine::get_dp_options()}
     * rather than calling this method directly.
     *
     * @return array suitable for passing to {@link choose_from_menu()} or similar.
     */
    public static function get_dp_options() {
        $options = array();
        for ($i = 0; $i <= self::MAX_DP; $i += 1) {
            $options[$i] = $i;
        }
        return $options;
    }
}


/**
 * This class keeps track of a group of questions that are being attempted,
 * and which state, and so on, each one is currently in.
 *
 * A quiz attempt or a lesson attempt could use an instance of this class to
 * keep track of all the questions in the attempt and process student submissions.
 * It is basically a collection of {@question_attempt} objects.
 *
 * The questions being attempted as part of this usage are identified by an integer
 * that is passed into many of the methods as $qnumber. ($question->id is not
 * used so that the same question can be used more than once in an attempt.)
 *
 * Normally, calling code should be able to do everything it needs to be calling
 * methods of this class. You should not normally need to get individual
 * {@question_attempt} objects and play around with their inner workind, in code
 * that it outside the quetsion engine.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_usage_by_activity {
    /**
     * @var integer|string the id for this usage. If this usage was loaded from
     * the database, then this is the database id. Otherwise a unique random
     * string is used.
     */
    protected $id = null;

    /**
     * @var string name of an archetypal interaction model, that should be used
     * by questions in this usage if possible.
     */
    protected $preferredmodel = null;
    protected $context;
    protected $owningplugin;
    protected $questionattempts = array();
    protected $observer;

    /**
     * Create a new instance. Normally, calling code should use
     * {@link question_engine::make_questions_usage_by_activity()} or
     * {@link question_engine::load_questions_usage_by_activity()} rather than
     * calling this constructor directly.
     *
     * @param string $owningplugin the plugin creating this attempt. For example mod_quiz.
     * @param object $context the context this usage belongs to.
     */
    public function __construct($owningplugin, $context) {
        $this->owningplugin = $owningplugin;
        $this->context = $context;
        $this->observer = new question_usage_null_observer();
    }

    /**
     * @param string $model the name of an archetypal interaction model, that should
     * be used by questions in this usage if possible.
     */
    public function set_preferred_interaction_model($model) {
        $this->preferredmodel = $model;
        $this->observer->notify_modified();
    }

    /** @return string the name of the preferred interaction model. */
    public function get_preferred_interaction_model() {
        return $this->preferredmodel;
    }

    /** @return stdClass the context this usage belongs to. */
    public function get_owning_context() {
        return $this->context;
    }

    /** @return string the name of the plugin that owns this attempt. */
    public function get_owning_plugin() {
        return $this->owningplugin;
    }

    /** @return integer|string If this usage came from the database, then the id
     * from the question_usages table is returned. Otherwise a random string is
     * returned. */
    public function get_id() {
        if (is_null($this->id)) {
            $this->id = random_string(10);
        }
        return $this->id;
    }

    /** @return question_usage_observer that is tracking changes made to this usage. */
    public function get_observer() {
        return $this->observer;
    }

    /**
     * For internal use only. Used by {@link question_engine_data_mapper} to set
     * the id when a usage is saved to the database.
     * @param integer $id the newly determined id for this usage.
     */
    public function set_id_from_database($id) {
        $this->id = $id;
        foreach ($this->questionattempts as $qa) {
            $qa->set_usage_id($id);
        }
    }

    /**
     * Add another question to this usage.
     *
     * The added question is not started until you call {@link start_question()}
     * on it.
     *
     * @param question_definition $question the question to add.
     * @param number $maxmark the maximum this question will be marked out of in
     *      this attempt (optional). If not given, $question->defaultmark is used.
     * @return integer the number used to identify this question within this usage.
     */
    public function add_question(question_definition $question, $maxmark = null) {
        $qa = new question_attempt($question, $this->get_id(), $this->observer, $maxmark);
        if (count($this->questionattempts) == 0) {
            $this->questionattempts[1] = $qa;
        } else {
            $this->questionattempts[] = $qa;
        }
        $qa->set_number_in_usage(end(array_keys($this->questionattempts)));
        $this->observer->notify_attempt_added($qa);
        return $qa->get_number_in_usage();
    }

    /**
     * Get the question_definition for a question in this attempt.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return question_definition the requested question object.
     */
    public function get_question($qnumber) {
        return $this->get_question_attempt($qnumber)->get_question();
    }

    /** @return array all the identifying numbers of all the questions in this usage. */
    public function get_question_numbers() {
        return array_keys($this->questionattempts);
    }

    /** @return integer the identifying number of the first question that was added to this usage. */
    public function get_first_question_number() {
        reset($this->questionattempts);
        return key($this->questionattempts);
    }

    /** @return integer the number of questions that are currently in this usage. */
    public function question_count() {
        return count($this->questionattempts);
    }

    /**
     * Note the part of the {@link question_usage_by_activity} comment that explains
     * that {@link question_attempt} objects should be considered part of the inner
     * workings of the question engine, and should not, if possible, be accessed directly.
     *
     * @return question_attempt_iterator for iterating over all the questions being
     * attempted. as part of this usage.
     */
    public function get_attempt_iterator() {
        return new question_attempt_iterator($this);
    }

    /**
     * Note the part of the {@link question_usage_by_activity} comment that explains
     * that {@link question_attempt} objects should be considered part of the inner
     * workings of the question engine, and should not, if possible, be accessed directly.
     *
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return question_attempt the corresponding {@link question_attempt} object.
     */
    public function get_question_attempt($qnumber) {
        if (!array_key_exists($qnumber, $this->questionattempts)) {
            throw new exception("There is no question_attempt number $qnumber in this attempt.");
        }
        return $this->questionattempts[$qnumber];
    }

    /**
     * Get the current state of the attempt at a question.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return integer one of the {@link question_state} constants.
     */
    public function get_question_state($qnumber) {
        return $this->get_question_attempt($qnumber)->get_state();
    }

    /**
     * Get the current mark awarded for the attempt at a question.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return number|null The current mark for this question, or null if one has
     * not been assigned yet.
     */
    public function get_question_mark($qnumber) {
        return $this->get_question_attempt($qnumber)->get_mark();
    }

    public function get_question_max_mark($qnumber) {
        return $this->get_question_attempt($qnumber)->get_max_mark();
    }

    public function render_question($qnumber, $options, $number = null) {
        return $this->get_question_attempt($qnumber)->render($options, $number);
    }

    public function render_question_head_html($qnumber) {
        return $this->get_question_attempt($qnumber)->render_head_html();
    }

    public function get_field_prefix($qnumber) {
        return $this->get_question_attempt($qnumber)->get_field_prefix();
    }

    public function start_question($qnumber) {
        $qa = $this->get_question_attempt($qnumber);
        $qa->start($this->preferredmodel);
        $this->observer->notify_attempt_modified($qa);
    }

    public function start_all_questions() {
        foreach ($this->questionattempts as $qa) {
            $qa->start($this->preferredmodel);
            $this->observer->notify_attempt_modified($qa);
        }
    }

    public function process_all_actions($postdata = null) {
        $qnumbers = optional_param('qnumbers', null, PARAM_SEQUENCE);
        if (is_null($qnumbers)) {
            $qnumbers = $this->get_question_numbers();
        } else if (!$qnumbers) {
            $qnumbers = array();
        } else {
            $qnumbers = explode(',', $qnumbers);
        }
        foreach ($qnumbers as $qnumber) {
            $submitteddata = $this->extract_responses($qnumber, $postdata);
            $this->process_action($qnumber, $submitteddata);
        }
    }

    public function get_correct_response($qnumber) {
        return $this->get_question_attempt($qnumber)->get_correct_response();
    }

    public function extract_responses($qnumber, $postdata = null) {
        return $this->get_question_attempt($qnumber)->get_submitted_data($postdata);
    }

    public function process_action($qnumber, $submitteddata) {
        $qa = $this->get_question_attempt($qnumber);
        $qa->process_action($submitteddata);
        $this->observer->notify_attempt_modified($qa);
    }

    public function finish_question($qnumber) {
        $qa = $this->get_question_attempt($qnumber);
        $qa->finish();
        $this->observer->notify_attempt_modified($qa);
    }

    public function finish_all_questions() {
        foreach ($this->questionattempts as $qa) {
            $qa->finish();
            $this->observer->notify_attempt_modified($qa);
        }
    }

    public function manual_grade($qnumber, $comment, $mark) {
        $qa = $this->get_question_attempt($qnumber);
        $qa->manual_grade($mark, $comment);
        $this->observer->notify_attempt_modified($qa);
    }

    public function regrade_question($qnumber, $newmaxmark = null) {
        $oldqa = $this->get_question_attempt($qnumber);
        if (is_null($newmaxmark)) {
            $newmaxmark = $oldqa->get_max_mark();
        }
        $newqa = new question_attempt($oldqa->get_question(), $oldqa->get_usage_id(), null, $newmaxmark);
        $oldfirststep = $oldqa->get_step(0);
        $newqa->regrade($oldqa);
        $this->questionattempts[$qnumber] = $newqa;
        // TODO notify observer.
    }

    public function regrade_all_questions() {
        foreach ($this->questionattempts as $qnumber => $notused) {
            $this->regrade_question($qnumber);
        }
    }

    /**
     * Create a question_usage_by_activity from records loaded from the database.
     * @param array $records Raw records loaded from the database.
     * @param integer $questionattemptid The id of the question_attempt to extract.
     * @return question_attempt The newly constructed question_attempt_step.
     */
    public static function load_from_records(&$records, $qubaid) {
        $record = current($records);
        while ($record->qubaid != $qubaid) {
            $record = next($records);
            if (!$record) {
                throw new Exception("Question usage $qubaid not found in the database.");
            }
        }

        $quba = new question_usage_by_activity($record->owningplugin,
            get_context_instance_by_id($record->contextid));
        $quba->set_id_from_database($record->qubaid);
        $quba->set_preferred_interaction_model($record->preferredmodel);

        $quba->observer = new question_engine_unit_of_work($quba);

        while ($record && $record->qubaid == $qubaid && !is_null($record->numberinusage)) {
            $quba->questionattempts[$record->numberinusage] =
                    question_attempt::load_from_records($records,
                    $record->questionattemptid, $quba->observer);
            $record = current($records);
        }

        return $quba;
    }
}


/**
 * A class abstracting access to the question_attempt::states array.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_iterator implements Iterator, ArrayAccess {
    protected $quba;
    protected $qnumbers;

    public function __construct(question_usage_by_activity $quba) {
        $this->quba = $quba;
        $this->qnumbers = $quba->get_question_numbers();
        $this->rewind();
    }

    /** @return question_attempt_step */
    public function current() {
        return $this->offsetGet(current($this->qnumbers));
    }
    /** @return integer */
    public function key() {
        return current($this->qnumbers);
    }
    public function next() {
        next($this->qnumbers);
    }
    public function rewind() {
        reset($this->qnumbers);
    }
    /** @return boolean */
    public function valid() {
        return current($this->qnumbers) !== false;
    }

    /** @return boolean */
    public function offsetExists($qnumber) {
        return in_array($qnumber, $this->qnumbers);
    }
    /** @return question_attempt_step */
    public function offsetGet($qnumber) {
        return $this->quba->get_question_attempt($qnumber);
    }
    public function offsetSet($qnumber, $value) {
        throw new Exception('You are only allowed read-only access to question_attempt::states through a question_attempt_step_iterator. Cannot set.');
    }
    public function offsetUnset($qnumber) {
        throw new Exception('You are only allowed read-only access to question_attempt::states through a question_attempt_step_iterator. Cannot unset.');
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
    /** @var question_usage_observer */
    protected $observer;

    const KEEP = true;
    const DISCARD = false;

    public function __construct(question_definition $question, $usageid,
            question_usage_observer $observer = null, $maxmark = null) {
        $this->question = $question;
        $this->usageid = $usageid;
        if (is_null($observer)) {
            $observer = new question_usage_null_observer();
        }
        $this->observer = $observer;
        if (!is_null($maxmark)) {
            $this->maxmark = $maxmark;
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

    public function get_database_id() {
        return $this->id;
    }

    public function get_usage_id() {
        return $this->usageid;
    }

    public function set_usage_id($usageid) {
        $this->usageid = $usageid;
    }

    public function get_interaction_model_name() {
        return $this->interactionmodel->get_name();
    }

    public function set_flagged($flagged) {
        $this->flagged = $flagged;
        $this->observer->notify_attempt_modified($this);
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
        return format_float($this->get_mark(), $dp);
    }

    public function format_max_mark($dp) {
        return format_float($this->maxmark, $dp);
    }

    public function render($options, $number) {
        $qoutput = renderer_factory::get_renderer('core', 'question');
        $qtoutput = $this->question->get_renderer();
        return $this->interactionmodel->render($options, $number, $qoutput, $qtoutput);
    }

    public function render_head_html() {
        return $this->question->qtype->get_html_head_contributions($this->question, 'TODO');
    }

    protected function add_step(question_attempt_step $step) {
        $this->steps[] = $step;
        end($this->steps);
        $this->observer->notify_step_added($step, $this, key($this->steps));
    }

    public function start($preferredmodel, $submitteddata = array(), $timestamp = null, $userid = null) {
        if (is_string($preferredmodel)) {
            $this->interactionmodel =
                    $this->question->make_interaction_model($this, $preferredmodel);
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

    protected function get_submitted_var($name, $type, $postdata = null) {
        if (is_null($postdata)) {
            return optional_param($name, null, $type);
        } else if (array_key_exists($name, $postdata)) {
            return clean_param($postdata[$name], $type);
        } else {
            return null;
        }
    }

    public function get_submitted_data($postdata = null) {
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

    public function get_correct_response() {
        $response = $this->question->get_correct_response();
        $imvars = $this->interactionmodel->get_correct_response();
        foreach ($imvars as $name => $value) {
            $response['!' . $name] = $value;
        }
        return $response;
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
    public static function load_from_records(&$records, $questionattemptid,
            question_usage_observer $observer) {
        $record = current($records);
        while ($record->questionattemptid != $questionattemptid) {
            $record = next($records);
            if (!$record) {
                throw new Exception("Question attempt $questionattemptid not found in the database.");
            }
        }

        $question = question_engine::load_question($record->questionid);

        $qa = new question_attempt($question, $record->questionusageid, null, $record->maxmark + 0);
        $qa->id = $record->questionattemptid;
        $qa->set_number_in_usage($record->numberinusage);
        $qa->minfraction = $record->minfraction + 0;
        $qa->set_flagged($record->flagged);
        $qa->questionsummary = $record->questionsummary;
        $qa->rightanswer = $record->rightanswer;
        $qa->timemodified = $record->timemodified;

        $qa->interactionmodel = $question->make_interaction_model($qa, $record->interactionmodel);

        $i = 0;
        while (current($records)) {
            $qa->steps[$i] = question_attempt_step::load_from_records($records, $i);
            $i++;
        }

        $qa->observer = $observer;

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

        $step = new question_attempt_step_read_only($data, $record->timecreated, $record->userid);
        $step->state = $record->state;
        if (!is_null($record->fraction)) {
            $step->fraction = $record->fraction + 0;
        }
        return $step;
    }
}


/**
 * A subclass of {@link question_attempt_step} that cannot be modified.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_step_read_only extends question_attempt_step {
    public function set_state($state) {
        throw new Exception('Cannot modify a question_attempt_step_read_only.');
    }
    public function set_fraction($fraction) {
        throw new Exception('Cannot modify a question_attempt_step_read_only.');
    }
    public function set_qt_var($name, $value) {
        throw new Exception('Cannot modify a question_attempt_step_read_only.');
    }
    public function set_im_var($name, $value) {
        throw new Exception('Cannot modify a question_attempt_step_read_only.');
    }
}


/**
 * A null {@link question_attempt_step} returned from
 * {@link question_attempt::get_last_step()} etc. when a an attempt has just been
 * started and there is no acutal step.
 *
 * @copyright 2009 The Open University
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
 * Interface for things that want to be notified of signficant changes to a
 * {@link question_usage_by_activity}.
 *
 * A question interaction model controls the flow of actions a student can
 * take as they work through a question, and later, as a teacher manually grades it.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface question_usage_observer {
    public function notify_modified();
    public function notify_attempt_modified(question_attempt $qa);
    public function notify_attempt_added(question_attempt $qa);
    public function notify_step_added(question_attempt_step $step, question_attempt $qa, $seq);
}


/**
 * Null implmentation of the {@link question_usage_watcher} interface.
 * Does nothing.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_usage_null_observer implements question_usage_observer {
    public function notify_modified() {
    }
    public function notify_attempt_modified(question_attempt $qa) {
    }
    public function notify_attempt_added(question_attempt $qa) {
    }
    public function notify_step_added(question_attempt_step $step, question_attempt $qa, $seq) {
    }
}