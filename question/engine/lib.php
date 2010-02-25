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


require_once(dirname(__FILE__) . '/compatibility.php');

require_once(dirname(__FILE__) . '/datalib.php');
require_once(dirname(__FILE__) . '/renderer.php');
require_once(dirname(__FILE__) . '/bank.php');
require_once(dirname(__FILE__) . '/../type/questiontype.php');
require_once(dirname(__FILE__) . '/../type/questionbase.php');
require_once(dirname(__FILE__) . '/../type/rendererbase.php');
require_once(dirname(__FILE__) . '/../interaction/modelbase.php');
require_once(dirname(__FILE__) . '/../interaction/rendererbase.php');


/**
 * This static class provides access to the other question engine classes.
 *
 * It provides functions for managing question interaction models), and for
 * creating, loading, saving and deleting {@link question_usage_by_activity}s,
 * which is the main class that is used by other code that wants to use questions.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_engine {
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
     * Reload the state of one question in a {@link question_usage_by_activity}
     * from the database. Possibly only going as far as the step with sequence number $seq.
     * @param question_usage_by_activity $quba the id of the usage to load.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @param integer $seq (optional) If given, only load the steps up to and including
     *      the one with this sequence number.
     */
    public static function reload_question_state_in_quba(question_usage_by_activity $quba, $qnumber, $seq = null) {
        $dm = new question_engine_data_mapper();
        $dm->reload_question_state_in_quba($quba, $qnumber, $seq);
    }

    /**
     * Save a {@link question_usage_by_activity} to the database. This works either
     * if the usage was newly created by {@link make_questions_usage_by_activity()}
     * or loaded from the database using {@link load_questions_usage_by_activity()}
     * @param question_usage_by_activity the usage to save.
     */
    public static function save_questions_usage_by_activity(question_usage_by_activity $quba) {
        $dm = new question_engine_data_mapper();
        $observer = $quba->get_observer();
        if ($observer instanceof question_engine_unit_of_work) {
            $observer->save($dm);
        } else {
            $dm->insert_questions_usage_by_activity($quba);
        }
    }

    /**
     * Delete a {@link question_usage_by_activity} from the database, based on its id.
     * @param integer $qubaid the id of the usage to delete.
     */
    public static function delete_questions_usage_by_activity($qubaid) {
        global $CFG;
        self::delete_questions_usage_by_activities($CFG->prefix . 'question_usages.id = ' . $qubaid);
    }

    /**
     * Delete a {@link question_usage_by_activity} from the database, based on its id.
     * @param integer $qubaid the id of the usage to delete.
     */
    public static function delete_questions_usage_by_activities($where) {
        $dm = new question_engine_data_mapper();
        $dm->delete_questions_usage_by_activities($where);
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
        return new $class($qa, $preferredmodel);
    }

    /**
     * Create an interaction model for a particular type. If that type cannot be
     * found, return an instance of qim_missing.
     *
     * Normally you should use {@link make_archetypal_interaction_model()}, or
     * call the constructor of a particular model class directly. This method
     * is only intended for use by {@link question_attempt::load_from_records()}.
     *
     * @param string $model the type of model to create.
     * @param question_attempt $qa the question attempt the model will process.
     * @param string $preferredmodel the preferred interaction model for the containing usage.
     * @return question_interaction_model an instance of appropriate interaction model class.
     */
    public static function make_interaction_model($model, question_attempt $qa, $preferredmodel) {
        try {
            question_engine::load_interaction_model_class($model);
        } catch (Exception $e) {
            question_engine::load_interaction_model_class('missing');
            return new qim_missing($qa, $preferredmodel);
        }
        $class = 'qim_' . $model;
        return new $class($qa, $preferredmodel);
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

    public static function initialise_js() {
        return question_flags::initialise_js();
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
     * Specific question_state instances.
     */
    public static $notstarted;
    public static $unprocessed;
    public static $todo;
    public static $invalid;
    public static $complete;
    public static $needsgrading;
    public static $finished;
    public static $gaveup;
    public static $gradedwrong;
    public static $gradedpartial;
    public static $gradedright;
    public static $manfinished;
    public static $mangaveup;
    public static $mangrwrong;
    public static $mangrpartial;
    public static $mangrright;
    /**#@+-*/

    protected function __construct() {
    }

    public static function init() {
        $us = new ReflectionClass('question_state');
        foreach ($us->getStaticProperties() as $name => $notused) {
            $class = 'question_state_' . $name;
            $states[$name] = new $class();
            self::$$name = $states[$name];
        }
    }

    /**
     * @return string convert this state to a string.
     */
    public function __toString() {
        return substr(get_class($this), 15);
    }

    /**
     * @param string $name a state name.
     * @return question_state the state with that name.
     */
    public static function get($name) {
        return self::$$name;
    }

    /**
     * Is this state one of the ones that mean the question attempt is in progress?
     * That is, started, but no finished.
     * @return boolean
     */
    public function is_active() {
        return false;
    }

    /**
     * Is this state one of the ones that mean the question attempt is finished?
     * That is, not furthre interaction possible, apart from manual grading.
     * @return boolean
     */
    public function is_finished() {
        return true;
    }

    /**
     * Is this state one of the ones that mean the question attempt has been graded?
     * @return boolean
     */
    public function is_graded() {
        return false;
    }

    /**
     * Is this state one of the ones that mean the question attempt has been graded?
     * @return boolean
     */
    public function is_correct() {
        return false;
    }

    /**
     * Is this state one of the ones that mean the question attempt has been graded?
     * @return boolean
     */
    public function is_partially_correct() {
        return false;
    }

    /**
     * Is this state one of the ones that mean the question attempt has been graded?
     * @return boolean
     */
    public function is_incorrect() {
        return false;
    }

    /**
     * Is this state one of the ones that mean the question attempt has been graded?
     * @return boolean
     */
    public function is_gave_up() {
        return false;
    }

    /**
     * Is this state one of the ones that mean the question attempt has had a manual comment added?
     * @return boolean
     */
    public function is_commented() {
        return false;
    }

    /**
     * Return the appropriate graded state based on a fraction. That is 0 or less
     * is $graded_incorrect, 1 is $graded_correct, otherwise it is $graded_partcorrect.
     * Appropriate allowance is made for rounding float values.
     *
     * @param number $fraction the grade, on the fraction scale.
     * @return integer one of the state constants.
     */
    public static function graded_state_for_fraction($fraction) {
        if ($fraction < 0.0000001) {
            return self::$gradedwrong;
        } else if ($fraction > 0.9999999) {
            return self::$gradedright;
        } else {
            return self::$gradedpartial;
        }
    }

    /**
     * Return the appropriate manually graded state based on a fraction. That is 0 or less
     * is $manually_graded_incorrect, 1 is $manually_graded_correct, otherwise it is
     * $manually_graded_partcorrect. Appropriate allowance is made for rounding float values.
     *
     * @param number $fraction the grade, on the fraction scale.
     * @return integer one of the state constants.
     */
    public static function manually_graded_state_for_fraction($fraction) {
        if ($fraction < 0.0000001) {
            return self::$mangrwrong;
        } else if ($fraction > 0.9999999) {
            return self::$mangrright;
        } else {
            return self::$mangrpartial;
        }
    }

    /**
     * Compute an appropriate state to move to after a manual comment has been
     * added to this state.
     * @param number $fraction the manual grade (if any) on the fraction scale.
     * @return integer the new state.
     */
    public function corresponding_commented_state($fraction) {
        throw new Exception('Unexpected question state.');
    }

    /**
     * Return an appropriate CSS class name ''/'correct'/'partiallycorrect'/'incorrect',
     * for a state.
     * @return string
     */
    public function get_feedback_class() {
        return '';
    }

    /**
     * Return the name of an appropriate string to look up in the question
     * language pack for a state. This is used, for example, by
     * {@link qim_renderer::get_state_string()}. However, some interaction
     * models sometimes change this default string for soemthing more specific.
     *
     * @return string the name of a string that can be looked up in the 'question'
     *      lang pack, or used as a CSS class name, etc.
     */
    public abstract function get_state_class();

    /**
     * The result of doing get_string on the result of {@link get_state_class()}.
     *
     * @return string a string from the lang pack that can be used in the UI.
     */
    public function default_string() {
        return get_string($this->get_state_class(), 'question');
    }
}


/**#@+
 * Specific question_state subclasses.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_state_notstarted extends question_state {
    public function is_finished() {
        return false;
    }
    public function get_state_class() {
        throw new Exception('Unexpected question state.');
    }
}
class question_state_unprocessed extends question_state {
    public function is_finished() {
        return false;
    }
    public function get_state_class() {
        throw new Exception('Unexpected question state.');
    }
}
class question_state_todo extends question_state {
    public function is_active() {
        return true;
    }
    public function is_finished() {
        return false;
    }
    public function get_state_class() {
        return 'notyetanswered';
    }
}
class question_state_invalid extends question_state {
    public function is_active() {
        return true;
    }
    public function is_finished() {
        return false;
    }
    public function get_state_class() {
        return 'invalidanswer';
    }
}
class question_state_complete extends question_state {
    public function is_active() {
        return true;
    }
    public function is_finished() {
        return false;
    }
    public function get_state_class() {
        return 'answersaved';
    }
}
class question_state_needsgrading extends question_state {
    public function get_state_class() {
        return 'requiresgrading';
    }
    public function corresponding_commented_state($fraction) {
        return self::manually_graded_state_for_fraction($fraction);
    }
}
class question_state_finished extends question_state {
    public function get_state_class() {
        return 'complete';
    }
    public function corresponding_commented_state($fraction) {
        return self::$manfinished;
    }
}
class question_state_gaveup extends question_state {
    public function is_gave_up() {
        return true;
    }
    public function get_feedback_class() {
        return 'incorrect';
    }
    public function get_state_class() {
        return 'notanswered';
    }
    public function corresponding_commented_state($fraction) {
        if (is_null($fraction)) {
            return self::$mangaveup;
        } else {
            return self::manually_graded_state_for_fraction($fraction);
        }
    }
}
abstract class question_state_graded extends question_state {
    public function is_graded() {
        return true;
    }
    public function get_state_class() {
        return $this->get_feedback_class();
    }
    public function corresponding_commented_state($fraction) {
        return self::manually_graded_state_for_fraction($fraction);
    }
}
class question_state_gradedwrong extends question_state_graded {
    public function is_incorrect() {
        return true;
    }
    public function get_feedback_class() {
        return 'incorrect';
    }
}
class question_state_gradedpartial extends question_state_graded {
    public function is_graded() {
        return true;
    }
    public function is_partially_correct() {
        return true;
    }
    public function get_feedback_class() {
        return 'partiallycorrect';
    }
}
class question_state_gradedright extends question_state_graded {
    public function is_graded() {
        return true;
    }
    public function is_correct() {
        return true;
    }
    public function get_feedback_class() {
        return 'correct';
    }
}
class question_state_manfinished extends question_state_finished {
    public function is_commented() {
        return true;
    }
}
class question_state_mangaveup extends question_state_gaveup {
    public function is_commented() {
        return true;
    }
}
abstract class question_state_manuallygraded extends question_state_graded {
    public function is_commented() {
        return true;
    }
}
class question_state_mangrwrong extends question_state_manuallygraded {
    public function is_incorrect() {
        return false;
    }
    public function get_feedback_class() {
        return 'incorrect';
    }
}
class question_state_mangrpartial extends question_state_manuallygraded {
    public function is_partially_correct() {
        return true;
    }
    public function get_feedback_class() {
        return 'partiallycorrect';
    }
}
class question_state_mangrright extends question_state_manuallygraded {
    public function is_correct() {
        return true;
    }
    public function get_feedback_class() {
        return 'correct';
    }
}
/**#@-*/
question_state::init();

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
     * @var boolean whether the question type should output hidden form fields
     * to reset any incorrect parts of the resonse to blank.
     */
    public $clearwrong = false;

    /**
     * Not really used withing the question engine (at least at the moment.)
     * The only way to not show the response the student entered is to not display
     * the question in its current state at all. (This is how this field is
     * used in the quiz at the moment.)
     * @var integer {@link question_display_options::HIDDEN} or
     * {@link question_display_options::VISIBLE}
     */
    public $responses = self::VISIBLE;

    /**
     * Should the one-line summary of the current state of the question that
     * appears by the question number be shown?
     * @var integer {@link question_display_options::HIDDEN} or
     * {@link question_display_options::VISIBLE}
     */
    public $correctness = self::VISIBLE;

    /**
     * Should the specific feedback be visible.
     * @var integer {@link question_display_options::HIDDEN} or
     * {@link question_display_options::VISIBLE}
     */
    public $feedback = self::VISIBLE;

    /**
     * For questions with a number of sub-parts (like matching, or
     * multiple-choice, multiple-reponse) display the number of sub-parts that
     * were correct.
     * @var integer {@link question_display_options::HIDDEN} or
     * {@link question_display_options::VISIBLE}
     */
    public $numpartscorrect = self::VISIBLE;

    /**
     * Should the general feedback be visible?
     * @var integer {@link question_display_options::HIDDEN} or
     * {@link question_display_options::VISIBLE}
     */
    public $generalfeedback = self::VISIBLE;

    /**
     * Should the automatically generated display of what the correct answer is
     * be visible?
     * @var integer {@link question_display_options::HIDDEN} or
     * {@link question_display_options::VISIBLE}
     */
    public $correctresponse = self::VISIBLE;

    /**
     * The the mark and/or the maximum available mark for this question be visible?
     * @var integer {@link question_display_options::HIDDEN},
     * {@link question_display_options::MAX_ONLY} or {@link question_display_options::MARK_AND_MAX}
     */
    public $marks = self::MARK_AND_MAX;

    /** @var number of decimal places to use when formatting marks for output. */
    public $markdp = 2;

    /**
     * Should the manually added marker's comment be visible. Should the link for
     * adding/editing the comment be there.
     * @var integer|string {@link question_display_options::HIDDEN},
     * {@link question_display_options::VISIBLE}, or string base URL for the edit
     * comment script (meaning show any comment, and the link).
     */
    public $manualcomment = self::VISIBLE;

    /**
     * Should the history of previous question states table be visible?
     * @var integer {@link question_display_options::HIDDEN} or
     * {@link question_display_options::VISIBLE}
     */
    public $history = self::HIDDEN;

    /**
     * Should the flag this question UI element be visible, and if so, should the
     * flag state be changable?
     * @var integer {@link question_display_options::HIDDEN},
     * {@link question_display_options::VISIBLE} or {@link question_display_options::EDITABLE}
     */
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
        $this->numpartscorrect = self::HIDDEN;
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
 * Contains the logic for handling question flags.
 *
 * @copyright © 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_flags {
    /**
     * Get the checksum that validates that a toggle request is valid.
     * @param integer $qubaid the question usage id.
     * @param integer $questionid the question id.
     * @param integer $sessionid the question_attempt id.
     * @param object $user the user. If null, defaults to $USER.
     * @return string that needs to be sent to question/toggleflag.php for it to work.
     */
    protected static function get_toggle_checksum($qubaid, $questionid, $qaid, $user = null) {
        if (is_null($user)) {
            global $USER;
            $user = $USER;
        }
        return md5($qubaid . "_" . $user->secret . "_" . $questionid . "_" . $qaid);
    }

    /**
     * Get the postdata that needs to be sent to question/toggleflag.php to change the flag state.
     * You need to append &newstate=0/1 to this.
     * @return the post data to send.
     */
    public static function get_postdate(question_attempt $qa) {
        $qaid = $qa->get_database_id();
        $qubaid = $qa->get_usage_id();
        $qid = $qa->get_question()->id;
        $checksum = self::get_toggle_checksum($qubaid, $qid, $qaid);
        return "qaid=$qaid&qubaid=$qubaid&qid=$qid&checksum=$checksum&sesskey=" . sesskey();
    }

    /**
     * If the request seems valid, update the flag state of a question attempt.
     * Throws exceptions if this is not a valid update request.
     * @param integer $qubaid the question usage id.
     * @param integer $questionid the question id.
     * @param integer $sessionid the question_attempt id.
     * @param string $checksum checksum, as computed by {@link get_toggle_checksum()}
     *      corresponding to the last three arguments.
     * @param boolean $newstate the new state of the flag. true = flagged.
     */
    public static function update_flag($qubaid, $questionid, $qaid, $checksum, $newstate) {
        // Check the checksum - it is very hard to know who a question session belongs
        // to, so we require that checksum parameter is matches an md5 hash of the 
        // three ids and the users username. Since we are only updating a flag, that
        // probably makes it sufficiently difficult for malicious users to toggle
        // other users flags.
        if ($checksum != question_flags::get_toggle_checksum($qubaid, $questionid, $qaid)) {
            throw new Exception('checksum failure');
        }

        $dm = new question_engine_data_mapper();
        $dm->update_question_attempt_flag($qubaid, $questionid, $qaid, $newstate);
    }

    public static function initialise_js() {
        global $CFG;

        require_js(array('yui_yahoo','yui_event', 'yui_connection'));
        require_js($CFG->wwwroot . '/question/qengine.js');

        $config = array(
            'actionurl' => $CFG->wwwroot . '/question/toggleflag.php',
            'flagicon' => $CFG->pixpath . '/i/flagged.png',
            'unflagicon' => $CFG->pixpath . '/i/unflagged.png',
            'flagtooltip' => get_string('clicktoflag', 'question'),
            'unflagtooltip' => get_string('clicktounflag', 'question'),
            'flaggedalt' => get_string('flagged', 'question'),
            'unflaggedalt' => get_string('notflagged', 'question'),
        );
        return print_js_config($config, 'qengine_config', true);
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
 * Instances of this class correspond to rows in the question_usages table.
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

    /** @var object the context this usage belongs to. */
    protected $context;

    /** @var string plugin name of the plugin this usage belongs to. */
    protected $owningplugin;

    /** @var array {@link question_attempt}s that make up this usage. */
    protected $questionattempts = array();

    /** @var question_usage_observer that tracks changes to this usage. */
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
     * Check whether $number actually corresponds to a question attempt that is
     * part of this usage. Throws an exception if not.
     *
     * @param integer $qnumber a number allegedly identifying a question within this usage.
     */
    protected function check_qnumber($qnumber) {
        if (!array_key_exists($qnumber, $this->questionattempts)) {
            throw new exception("There is no question_attempt number $qnumber in this attempt.");
        }
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
        $this->check_qnumber($qnumber);
        return $this->questionattempts[$qnumber];
    }

    /**
     * Get the current state of the attempt at a question.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return question_state.
     */
    public function get_question_state($qnumber) {
        return $this->get_question_attempt($qnumber)->get_state();
    }

    /**
     * Get the time of the most recent action performed on a question.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return integer timestamp.
     */
    public function get_question_action_time($qnumber) {
        return $this->get_question_attempt($qnumber)->get_last_action_time();
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

    /**
     * Get the maximum mark possible for the attempt at a question.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return number the available marks for this question.
     */
    public function get_question_max_mark($qnumber) {
        return $this->get_question_attempt($qnumber)->get_max_mark();
    }

    /**
     * Get the current mark awarded for the attempt at a question.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return number|null The current mark for this question, or null if one has
     * not been assigned yet.
     */
    public function get_total_mark() {
        $mark = 0;
        foreach ($this->questionattempts as $qa) {
            $mark += $qa->get_mark();
        }
        return $mark;
    }

    /**
     * Get the {@link core_question_renderer}, in collaboration with appropriate
     * {@link qim_renderer} and {@link qtype_renderer} subclasses, to generate the
     * HTML to display this question.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @param question_display_options $options controls how the question is rendered.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return string HTML fragment representing the question.
     */
    public function render_question($qnumber, $options, $number = null) {
        return $this->get_question_attempt($qnumber)->render($options, $number);
    }

    /**
     * Generate any bits of HTML that needs to go in the <head> tag when this question
     * is displayed in the body.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return string HTML fragment.
     */
    public function render_question_head_html($qnumber) {
        return $this->get_question_attempt($qnumber)->render_head_html();
    }

    /**
     * You should probably not use this method in code outside the question engine.
     * The main reason for exposing it was for the benefit of unit tests.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return string return the prefix that is pre-pended to field names in the HTML
     * that is output.
     */
    public function get_field_prefix($qnumber) {
        return $this->get_question_attempt($qnumber)->get_field_prefix();
    }

    /**
     * Start the attempt at a question that has been added to this usage.
     * @param integer $qnumber the number used to identify this question within this usage.
     */
    public function start_question($qnumber) {
        $qa = $this->get_question_attempt($qnumber);
        $qa->start($this->preferredmodel);
        $this->observer->notify_attempt_modified($qa);
    }

    /**
     * Start the attempt at all questions that has been added to this usage.
     */
    public function start_all_questions() {
        foreach ($this->questionattempts as $qa) {
            $qa->start($this->preferredmodel);
            $this->observer->notify_attempt_modified($qa);
        }
    }

    /**
     * Start the attempt at a question, starting from the point where the previous
     * question_attempt $oldqa had reached. This is used by the quiz 'Each attempt
     * builds on last' mode.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @param question_attempt $oldqa a previous attempt at this quetsion that
     *      defines the starting point.
     */
    public function start_question_based_on($qnumber, question_attempt $oldqa) {
        $qa = $this->get_question_attempt($qnumber);
        $qa->start_based_on($oldqa);
        $this->observer->notify_attempt_modified($qa);
    }

    /**
     * Process all the question actions in the current request.
     *
     * If there is a parameter qnumbers included in the post data, then only
     * those question numbers will be processed, otherwise all questions in this
     * useage will be.
     *
     * This function also does {@link update_question_flags()}.
     *
     * @param integer $timestamp optional, use this timestamp as 'now'.
     * @param array $postdata optional, only intended for testing. Use this data
     * instead of the data from $_POST.
     */
    public function process_all_actions($timestamp = null, $postdata = null) {
        $qnumbers = question_attempt::get_submitted_var('qnumbers', PARAM_SEQUENCE, $postdata);
        if (is_null($qnumbers)) {
            $qnumbers = $this->get_question_numbers();
        } else if (!$qnumbers) {
            $qnumbers = array();
        } else {
            $qnumbers = explode(',', $qnumbers);
        }
        foreach ($qnumbers as $qnumber) {
            $submitteddata = $this->extract_responses($qnumber, $postdata);
            $this->process_action($qnumber, $submitteddata, $timestamp);
        }
        $this->update_question_flags($postdata);
    }

    /**
     * Get the submitted data from the current request that belongs to this
     * particular question.
     *
     * @param integer $qnumber the number used to identify this question within this usage.
     * @param $postdata optional, only intended for testing. Use this data
     * instead of the data from $_POST.
     * @return array submitted data specific to this question.
     */
    public function extract_responses($qnumber, $postdata = null) {
        return $this->get_question_attempt($qnumber)->get_submitted_data($postdata);
    }

    /**
     * Process a specific action on a specific question.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @param $submitteddata the submitted data that constitutes the action.
     */
    public function process_action($qnumber, $submitteddata, $timestamp = null) {
        $qa = $this->get_question_attempt($qnumber);
        $qa->process_action($submitteddata, $timestamp);
        $this->observer->notify_attempt_modified($qa);
    }

    /**
     * Update the flagged state for all question_attempts in this usage, if their
     * flagged state was changed in the request.
     *
     * @param $postdata optional, only intended for testing. Use this data
     * instead of the data from $_POST.
     */
    public function update_question_flags($postdata = null) {
        foreach ($this->questionattempts as $qa) {
            $flagged = question_attempt::get_submitted_var(
                    $qa->get_flag_field_name(), PARAM_BOOL, $postdata);
            if (!is_null($flagged) && $flagged != $qa->is_flagged()) {
                $qa->set_flagged($flagged);
            }
        }
        
    }

    /**
     * Get the correct response to a particular question. Passing the results of
     * this method to {@link process_action()} will probably result in full marks.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @return array that constitutes a correct response to this question.
     */
    public function get_correct_response($qnumber) {
        return $this->get_question_attempt($qnumber)->get_correct_response();
    }

    /**
     * Finish the active phase of an attempt at a question.
     *
     * This is an external act of finishing the attempt. Think, for example, of
     * the 'Submit all and finish' button in the quiz. Some interaction models,
     * (for example, immediatefeedback) give a way of finishing the active phase
     * of a question attempt as part of a {@link process_action()} call.
     *
     * After the active phase is over, the only changes possible are things like
     * manual grading, or changing the flag state.
     *
     * @param integer $qnumber the number used to identify this question within this usage.
     */
    public function finish_question($qnumber, $timestamp = null) {
        $qa = $this->get_question_attempt($qnumber);
        $qa->finish($timestamp);
        $this->observer->notify_attempt_modified($qa);
    }

    /**
     * Finish the active phase of an attempt at a question. See {@link finish_question()}
     * for a fuller description of what 'finish' means.
     */
    public function finish_all_questions($timestamp = null) {
        foreach ($this->questionattempts as $qa) {
            $qa->finish($timestamp);
            $this->observer->notify_attempt_modified($qa);
        }
    }

    /**
     * Perform a manual grading action on a question attempt.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @param string $comment the comment being added to the question attempt.
     * @param number $mark the mark that is being assigned. Can be null to just
     * add a comment.
     */
    public function manual_grade($qnumber, $comment, $mark) {
        $qa = $this->get_question_attempt($qnumber);
        $qa->manual_grade($comment, $mark);
        $this->observer->notify_attempt_modified($qa);
    }

    /**
     * Regrade a question in this usage. This replays the sequence of submitted
     * actions to recompute the outcomes.
     * @param integer $qnumber the number used to identify this question within this usage.
     * @param $newmaxmark (optional) if given, will change the max mark while regrading.
     */
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

    /**
     * Regrade all the questions in this usage (without changing their max mark).
     */
    public function regrade_all_questions() {
        foreach ($this->questionattempts as $qnumber => $notused) {
            $this->regrade_question($qnumber);
        }
    }

    /**
     * Create a question_usage_by_activity from records loaded from the database.
     *
     * For internal use only.
     *
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
                    $record->questionattemptid, $quba->observer,
                    $quba->get_preferred_interaction_model());
            $record = current($records);
        }

        return $quba;
    }

    /**
     * Replace a particular question_attempt with a different one.
     *
     * For internal use only. Used when reloading the state of a question from the
     * database.
     *
     * @param array $records Raw records loaded from the database.
     * @param integer $questionattemptid The id of the question_attempt to extract.
     * @return question_attempt The newly constructed question_attempt_step.
     */
    public function replace_loaded_question_attempt_info($qnumber, $qa) {
        $this->check_qnumber($qnumber);
        $this->questionattempts[$qnumber] = $qa;
    }
}


/**
 * A class abstracting access to the
 * {@link question_usage_by_activity::$questionattempts} array.
 *
 * This class snapshots the list of {@link question_attempts} to iterate over
 * when it is created. If a question is added to the usage mid-iteration, it
 * will now show up.
 *
 * To create an instance of this class, use
 * {@link question_usage_by_activity::get_attempt_iterator()}
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_iterator implements Iterator, ArrayAccess {
    /** @var question_usage_by_activity that we are iterating over. */
    protected $quba;
    /** @var array of question numbers. */
    protected $qnumbers;

    /**
     * To create an instance of this class, use {@link question_usage_by_activity::get_attempt_iterator()}.
     * @param $quba the usage to iterate over.
     */
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
 * Tracks an attempt at one particular question in a {@link question_usage_by_activity}.
 *
 * Most calling code should need to access objects of this class. They should be
 * able to do everything through the usage interface. This class is an internal
 * implementation detail of the question engine.
 *
 * Instances of this class correspond to rows in the question_attempts table, and
 * a collection of {@link question_attempt_steps}. Question inteaction models and
 * question types do work with question_attempt objects.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt {
    const USE_RAW_DATA = 'use raw data';

    /** @var integer if this attempts is stored in the question_attempts table, the id of that row. */
    protected $id = null;

    /** @var integer|string the id of the question_usage_by_activity we belong to. */
    protected $usageid;

    /** @var integer the number used to identify this question_attempt within the usage. */
    protected $numberinusage = null;

    /**
     * @var question_interaction_model the interaction model controlling this attempt.
     * null until {@link start()} is called.
     */
    protected $interactionmodel = null;

    /** @var question_definition the question this is an attempt at. */
    protected $question;

    /** @var number the maximum mark that can be scored at this question. */
    protected $maxmark;

    /**
     * @var number the minimum fraction that can be scored at this question, so
     * the minimum mark is $this->minfraction * $this->maxmark.
     */
    protected $minfraction = null;

    /**
     * @var string plain text summary of the variant of the question the
     * student saw. Intended for reporting purposes.
     */
    protected $questionsummary = null;

    /**
     * @var string plain text summary of the response the student gave.
     * Intended for reporting purposes.
     */
    protected $responsesummary = null;

    /**
     * @var string plain text summary of the correct response to this question
     * variant the student saw. The format should be similar to responsesummary.
     * Intended for reporting purposes.
     */
    protected $rightanswer = null;

    /** @var array of {@link question_attempt_step}s. The steps in this attempt. */
    protected $steps = array();

    /** @var boolean whether the user has flagged this attempt within the usage. */
    protected $flagged = false;

    /** @var question_usage_observer tracks changes to the useage this attempt is part of.*/
    protected $observer;

    /**#@+
     * Constants used by the intereaction models to indicate whether the current
     * pending step should be kept or discarded.
     */
    const KEEP = true;
    const DISCARD = false;
    /**#@-*/

    /**
     * Create a new {@link question_attempt}. Normally you should create question_attempts
     * indirectly, by calling {@link question_usage_by_activity::add_question()}.
     *
     * @param question_definition $question the question this is an attempt at.
     * @param integer|string $usageid The id of the
     *      {@link question_usage_by_activity} we belong to. Used by {@link get_field_prefix()}.
     * @param question_usage_observer $observer tracks changes to the useage this
     *      attempt is part of. (Optional, a {@link question_usage_null_observer} is
     *      used if one is not passed.
     * @param number $maxmark the maximum grade for this question_attempt. If not
     * passed, $question->defaultmark is used.
     */
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

    /** @return question_definition the question this is an attempt at. */
    public function get_question() {
        return $this->question;
    }

    /**
     * Set the number used to identify this question_attempt within the usage.
     * For internal use only.
     * @param integer $qnumber
     */
    public function set_number_in_usage($qnumber) {
        $this->numberinusage = $qnumber;
    }

    /** @return integer the number used to identify this question_attempt within the usage. */
    public function get_number_in_usage() {
        return $this->numberinusage;
    }

    /**
     * @return integer the id of row for this question_attempt, if it is stored in the
     * database. null if not.
     */
    public function get_database_id() {
        return $this->id;
    }

    /** @return integer|string the id of the {@link question_usage_by_activity} we belong to. */
    public function get_usage_id() {
        return $this->usageid;
    }

    /**
     * Set the id of the {@link question_usage_by_activity} we belong to.
     * For internal use only.
     * @param integer|string the new id.
     */
    public function set_usage_id($usageid) {
        $this->usageid = $usageid;
    }

    /** @return string the name of the interaction model that is controlling this attempt. */
    public function get_interaction_model_name() {
        return $this->interactionmodel->get_name();
    }

    /**
     * Set the flagged state of this question.
     * @param boolean $flagged the new state.
     */
    public function set_flagged($flagged) {
        $this->flagged = $flagged;
        $this->observer->notify_attempt_modified($this);
    }

    /** @return boolean whether this question is currently flagged. */
    public function is_flagged() {
        return $this->flagged;
    }

    /**
     * Get the name (in the sense a HTML name="" attribute, or a $_POST variable
     * name) to use for the field that indicates whether this question is flagged.
     *
     * @return string  The field name to use.
     */
    public function get_flag_field_name() {
        return $this->get_field_prefix() . '#flagged';
    }

    /**
     * Get the name (in the sense a HTML name="" attribute, or a $_POST variable
     * name) to use for a question_type variable belonging to this question_attempt.
     *
     * See the comment on {@link question_attempt_step} for an explanation of
     * question type and interaction model variables.
     *
     * @param $varname The short form of the variable name.
     * @return string  The field name to use.
     */
    public function get_qt_field_name($varname) {
        return $this->get_field_prefix() . $varname;
    }

    /**
     * Get the name (in the sense a HTML name="" attribute, or a $_POST variable
     * name) to use for a question_type variable belonging to this question_attempt.
     *
     * See the comment on {@link question_attempt_step} for an explanation of
     * question type and interaction model variables.
     *
     * @param $varname The short form of the variable name.
     * @return string  The field name to use.
     */
    public function get_im_field_name($varname) {
        return $this->get_field_prefix() . '!' . $varname;
    }

    /**
     * Get the prefix added to variable names to give field names for this
     * question attempt.
     *
     * You should not use this method directly. This is an implementation detail
     * anyway, but if you must access it, use {@link question_usage_by_activity::get_field_prefix()}.
     *
     * @param $varname The short form of the variable name.
     * @return string  The field name to use.
     */
    public function get_field_prefix() {
        return 'q' . $this->usageid . ',' . $this->numberinusage . '_';
    }

    /**
     * Get one of the steps in this attempt.
     * For internal/test code use only.
     * @param integer $i the step number.
     * @return question_attempt_step
     */
    public function get_step($i) {
        if ($i < 0 || $i >= count($this->steps)) {
            throw new Exception('Index out of bounds in question_attempt::get_step.');
        }
        return $this->steps[$i];
    }

    /**
     * Get the number of steps in this attempt.
     * For internal/test code use only.
     * @return integer the number of steps we currently have.
     */
    public function get_num_steps() {
        return count($this->steps);
    }

    /**
     * Return the latest step in this question_attempt.
     * For internal/test code use only.
     * @return question_attempt_step
     */
    public function get_last_step() {
        if (count($this->steps) == 0) {
            return new question_null_step();
        }
        return end($this->steps);
    }

    /**
     * @return question_attempt_step_iterator for iterating over the steps in
     * this attempt, in order.
     */
    public function get_step_iterator() {
        return new question_attempt_step_iterator($this);
    }

    /**
     * @return question_attempt_reverse_step_iterator for iterating over the steps in
     * this attempt, in reverse order.
     */
        public function get_reverse_step_iterator() {
        return new question_attempt_reverse_step_iterator($this);
    }

    /**
     * Get the qt data from the latest step that has any qt data. Return $default
     * array if it is no step has qt data.
     *
     * @param string $name the name of the variable to get.
     * @param mixed default the value to return no step has qt data.
     *      (Optional, defaults to an empty array.)
     * @return array|mixed the data, or $default if there is not any.
     */
    public function get_last_qt_data($default = array()) {
        foreach ($this->get_reverse_step_iterator() as $step) {
            $response = $step->get_qt_data();
            if (!empty($response)) {
                return $response;
            }
        }
        return $default;
    }

    /**
     * Get the latest value of a particular question type variable. That is, get
     * the value from the latest step that has it set. Return null if it is not
     * set in any step.
     *
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
     * Get the latest value of a particular interaction model variable. That is,
     * get the value from the latest step that has it set. Return null if it is
     * not set in any step.
     *
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

    /**
     * Get the current state of this question attempt. That is, the state of the
     * latest step.
     * @return question_state
     */
    public function get_state() {
        return $this->get_last_step()->get_state();
    }

    /**
     * @return string A brief textual description of the current state.
     */
    public function get_state_string() {
        return $this->interactionmodel->get_renderer()->get_state_string($this);
    }

    public function get_last_action_time() {
        return $this->get_last_step()->get_timecreated();
    }

    /**
     * Get the current fraction of this question attempt. That is, the fraction
     * of the latest step, or null if this question has not yet been graded.
     * @return number the current fraction.
     */
    public function get_fraction() {
        return $this->get_last_step()->get_fraction();
    }

    /**
     * @return number the current mark for this question.
     * {@link get_fraction()} * {@link get_max_mark()}.
     */
    public function get_mark() {
        $mark = $this->get_fraction();
        if (!is_null($mark)) {
            $mark *= $this->maxmark;
        }
        return $mark;
    }

    /** @return number the maximum mark possible for this question attempt. */
    public function get_max_mark() {
        return $this->maxmark;
    }

    /** @return number the maximum mark possible for this question attempt. */
    public function get_min_fraction() {
        if (is_null($this->minfraction)) {
            throw new Exception('This question_attempt has not been started yet, the min fraction is not yet konwn.');
        }
        return $this->minfraction;
    }

    /**
     * The current mark, formatted to the stated number of decimal places. Uses
     * {@link format_float()} to format floats according to the current locale.
     * @param integer $dp number of decimal places.
     * @return string formatted mark.
     */
    public function format_mark($dp) {
        return format_float($this->get_mark(), $dp);
    }

    /**
     * The maximum mark for this question attempt, formatted to the stated number
     * of decimal places. Uses {@link format_float()} to format floats according
     * to the current locale.
     * @param integer $dp number of decimal places.
     * @return string formatted maximum mark.
     */
    public function format_max_mark($dp) {
        return format_float($this->maxmark, $dp);
    }

    /**
     * Return the hint that applies to the question in its current state, or null.
     * @return question_hint|null
     */
    public function get_applicable_hint() {
        return $this->interactionmodel->get_applicable_hint();
    }

    /**
     * Get the {@link core_question_renderer}, in collaboration with appropriate
     * {@link qim_renderer} and {@link qtype_renderer} subclasses, to generate the
     * HTML to display this question attempt in its current state.
     * @param question_display_options $options controls how the question is rendered.
     * @param string|null $number The question number to display.
     * @return string HTML fragment representing the question.
     */
    public function render($options, $number) {
        $qoutput = renderer_factory::get_renderer('core', 'question');
        $qtoutput = $this->question->get_renderer();
        return $this->interactionmodel->render($options, $number, $qoutput, $qtoutput);
    }

    /**
     * Generate any bits of HTML that needs to go in the <head> tag when this question
     * attempt is displayed in the body.
     * @return string HTML fragment.
     */
    public function render_head_html() {
        return $this->question->get_renderer()->head_code($this) .
                $this->interactionmodel->get_renderer()->head_code($this);
    }

    /**
     * Add a step to this question attempt.
     * @param question_attempt_step $step the new step.
     */
    protected function add_step(question_attempt_step $step) {
        $this->steps[] = $step;
        end($this->steps);
        $this->observer->notify_step_added($step, $this, key($this->steps));
    }

    /**
     * Start (or re-start) this question attempt.
     *
     * You should not call this method directly. Call
     * {@link question_usage_by_activity::start_question()} instead.
     *
     * @param string|question_interaction_model $preferredmodel the name of the
     *      desired archetypal interaction model, or an actual model instance.
     * @param $submitteddata optional, used when re-starting to keep the same initial state.
     * @param $timestamp optional, the timstamp to record for this action. Defaults to now.
     * @param $userid optional, the user to attribute this action to. Defaults to the current user.
     */
    public function start($preferredmodel, $submitteddata = array(), $timestamp = null, $userid = null) {
        // Initialise the interaction model.
        if (is_string($preferredmodel)) {
            $this->interactionmodel =
                    $this->question->make_interaction_model($this, $preferredmodel);
        } else {
            $class = get_class($preferredmodel);
            $this->interactionmodel = new $class($this, $preferredmodel);
        }

        // Record the minimum fraction.
        $this->minfraction = $this->interactionmodel->get_min_fraction();

        // Initialise the first step.
        $firststep = new question_attempt_step($submitteddata, $timestamp, $userid);
        $firststep->set_state(question_state::$todo);
        $this->interactionmodel->init_first_step($firststep);
        $this->add_step($firststep);

        // Record questionline and correct answer.
        $this->questionsummary = $this->interactionmodel->get_question_summary();
        $this->rightanswer = $this->interactionmodel->get_right_answer_summary();
    }

    /**
     * Start this question attempt, starting from the point that the previous
     * attempt $oldqa had reached.
     *
     * You should not call this method directly. Call
     * {@link question_usage_by_activity::start_question_based_on()} instead.
     *
     * @param question_attempt $oldqa a previous attempt at this quetsion that
     *      defines the starting point.
     */
    public function start_based_on(question_attempt $oldqa) {
        $this->start($oldqa->interactionmodel, $oldqa->get_resume_data());
    }

    /**
     * Used by {@link start_based_on()} to get the data needed to start a new
     * attempt from the point this attempt has go to.
     * @return array name => value pairs.
     */
    protected function get_resume_data() {
        return $this->interactionmodel->get_resume_data();
    }

    /**
     * Get a particular parameter from the current request. A wrapper round
     * {@link optional_param()}.
     * @param string $name the paramter name.
     * @param integer $type one of the PARAM_... constants.
     * @param array $postdata (optional, only inteded for testing use) take the
     *      data from this array, instead of from $_POST.
     * @return mixed the requested value.
     */
    public static function get_submitted_var($name, $type, $postdata = null) {
        if (is_null($postdata)) {
            return optional_param($name, null, $type);
        } else if (array_key_exists($name, $postdata)) {
            return clean_param($postdata[$name], $type);
        } else {
            return null;
        }
    }

    /**
     * Get any data from the request that matches the list of expected params.
     * @param array $expected variable name => PARAM_... constant.
     * @param string $extraprefix '!' or ''.
     * @return array name => value.
     */
    protected function get_expected_data($expected, $postdata, $extraprefix) {
        $submitteddata = array();
        foreach ($expected as $name => $type) {
            $value = self::get_submitted_var(
                    $this->get_field_prefix() . $extraprefix . $name, $type, $postdata);
            if (!is_null($value)) {
                $submitteddata[$extraprefix . $name] = $value;
            }
        }
        return $submitteddata;
    }

    /**
     * Get all the submitted question type data for this question, whithout checking
     * that it is valid or cleaning it in any way.
     * @return array name => value.
     */
    protected function get_all_submitted_qt_vars($postdata) {
        if (is_null($postdata)) {
            $postdata = $_POST;
        }

        $pattern = '/^' . preg_quote($this->get_field_prefix()) . '[^!]/';
        $prefixlen = strlen($this->get_field_prefix());

        $submitteddata = array();
        foreach ($_POST as $name => $value) {
            if (preg_match($pattern, $name)) {
                $submitteddata[substr($name, $prefixlen)] = $value;
            }
        }

        return $submitteddata;
    }

    /**
     * Get all the sumbitted data belonging to this question attempt from the
     * current request.
     * @param array $postdata (optional, only inteded for testing use) take the
     *      data from this array, instead of from $_POST.
     * @return array name => value pairs that could be passed to {@link process_action()}.
     */
    public function get_submitted_data($postdata = null) {
        $submitteddata = $this->get_expected_data(
                $this->interactionmodel->get_expected_data(), $postdata, '!');

        $expected = $this->interactionmodel->get_expected_qt_data();
        if ($expected === self::USE_RAW_DATA) {
            $submitteddata += $this->get_all_submitted_qt_vars($postdata);
        } else {
            $submitteddata += $this->get_expected_data($expected, $postdata, '');
        }
        return $submitteddata;
    }

    /**
     * Get a set of response data for this question attempt that would get the
     * best possible mark.
     * @return array name => value pairs that could be passed to {@link process_action()}.
     */
    public function get_correct_response() {
        $response = $this->question->get_correct_response();
        $imvars = $this->interactionmodel->get_correct_response();
        foreach ($imvars as $name => $value) {
            $response['!' . $name] = $value;
        }
        return $response;
    }

    /**
     * @return string a simple textual summary of the question that was asked.
     */
    public function get_question_summary() {
        return $this->questionsummary;
    }

    /**
     * @return string a simple textual summary of response given.
     */
    public function get_response_summary() {
        return $this->responsesummary;
    }

    /**
     * @return string a simple textual summary of the correct resonse.
     */
    public function get_right_answer_summary() {
        return $this->rightanswer;
    }

    /**
     * Perform the action described by $submitteddata.
     * @param array $submitteddata the submitted data the determines the action.
     * @param integer $timestamp the time to record for the action. (If not given, use now.)
     * @param integer $userid the user to attribute the aciton to. (If not given, use the current user.)
     */
    public function process_action($submitteddata, $timestamp = null, $userid = null) {
        $pendingstep = new question_attempt_step($submitteddata, $timestamp, $userid);
        if ($this->interactionmodel->process_action($pendingstep) == self::KEEP) {
            $this->add_step($pendingstep);
        }
    }

    /**
     * Perform a finish action on this question attempt. This corresponds to an
     * external finish action, for example the user pressing Submit all and finish
     * in the quiz, rather than using one of the controls that is part of the
     * question.
     *
     * @param integer $timestamp the time to record for the action. (If not given, use now.)
     * @param integer $userid the user to attribute the aciton to. (If not given, use the current user.)
     */
    public function finish($timestamp = null, $userid = null) {
        $this->process_action(array('!finish' => 1), $timestamp, $userid);
    }

    /**
     * Perform a regrade. This replays all the actions from $oldqa into this
     * attempt.
     * @param question_attempt $oldqa the attempt to regrade.
     */
    public function regrade(question_attempt $oldqa) {
        $first = true;
        foreach ($oldqa->get_step_iterator() as $step) {
            if ($first) {
                $first = false;
                $this->start($oldqa->interactionmodel, $step->get_all_data(),
                        $step->get_timecreated(), $step->get_user_id());
            } else {
                $this->process_action($step->get_submitted_data(),
                        $step->get_timecreated(), $step->get_user_id());
            }
        }
    }

    /**
     * Perform a manual grading action on this attempt.
     * @param $comment the comment being added.
     * @param $mark the new mark. (Optional, if not given, then only a comment is added.)
     * @param integer $timestamp the time to record for the action. (If not given, use now.)
     * @param integer $userid the user to attribute the aciton to. (If not given, use the current user.)
     * @return unknown_type
     */
    public function manual_grade($comment, $mark, $timestamp = null, $userid = null) {
        $submitteddata = array('!comment' => $comment);
        if (!is_null($mark)) {
            $submitteddata['!mark'] = $mark;
            $submitteddata['!maxmark'] = $this->maxmark;
        }
        $this->process_action($submitteddata, $timestamp, $userid);
    }

    /** @return boolean Whether this question attempt has had a manual comment added. */
    public function has_manual_comment() {
        foreach ($this->steps as $step) {
            if ($step->has_im_var('comment')) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string the most recent manual comment that was added to this question.
     * null, if none.
     */
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
     *
     * For internal use only.
     *
     * @param array $records Raw records loaded from the database.
     * @param integer $questionattemptid The id of the question_attempt to extract.
     * @return question_attempt The newly constructed question_attempt_step.
     */
    public static function load_from_records(&$records, $questionattemptid,
            question_usage_observer $observer, $preferredmodel) {
        $record = current($records);
        while ($record->questionattemptid != $questionattemptid) {
            $record = next($records);
            if (!$record) {
                throw new Exception("Question attempt $questionattemptid not found in the database.");
            }
        }

        $question = question_bank::load_question($record->questionid);

        $qa = new question_attempt($question, $record->questionusageid, null, $record->maxmark + 0);
        $qa->id = $record->questionattemptid;
        $qa->set_number_in_usage($record->numberinusage);
        $qa->minfraction = $record->minfraction + 0;
        $qa->set_flagged($record->flagged);
        $qa->questionsummary = $record->questionsummary;
        $qa->rightanswer = $record->rightanswer;
        $qa->responsesummary = $record->responsesummary;
        $qa->timemodified = $record->timemodified;

        $qa->interactionmodel = question_engine::make_interaction_model(
                $record->interactionmodel, $qa, $preferredmodel);

        $i = 0;
        while ($record && $record->questionattemptid == $questionattemptid && !is_null($record->attemptstepid)) {
            $qa->steps[$i] = question_attempt_step::load_from_records($records, $record->attemptstepid);
            if ($i == 0) {
                $question->init_first_step($qa->steps[0]);
            }
            $i++;
            $record = current($records);
        }

        $qa->observer = $observer;

        return $qa;
    }
}


/**
 * A class abstracting access to the {@link question_attempt::$states} array.
 *
 * This is actively linked to question_attempt. If you add an new step
 * mid-iteration, then it will be included.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_step_iterator implements Iterator, ArrayAccess {
    /** @var question_attempt the question_attempt being iterated over. */
    protected $qa;
    /** @var integer records the current position in the iteration. */
    protected $i;

    /**
     * Do not call this constructor directly.
     * Use {@link question_attempt::get_step_iterator()}.
     * @param question_attempt $qa the attempt to iterate over.
     */
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


/**
 * A variant of {@link question_attempt_step_iterator} that iterates through the
 * steps in reverse order.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
 * The most important attributes of a step are the state, which is one of the
 * {@link question_state} constants, the fraction, which may be null, or a
 * number bewteen the attempt's minfraction and 1.0, and the array of submitted
 * data, about which more later.
 *
 * A step also tracks the time it was created, and the user responsible for
 * creating it.
 *
 * The submitted data is basically just an array of name => value pairs, with
 * certain conventions about the to divide the variables into four = two times two
 * categories.
 *
 * Variables may either belong to the interaction model, in which case the
 * name starts with a !, or they may belong to the question type in which case
 * they name does not start with a !.
 *
 * Second, variables may either be ones that came form the original request, in
 * which case the name does not start with an _, or they are cached values that
 * were created during processing, in which case the name does start with an _.
 *
 * That is, each name will start with one of '', '_'. '!' or '!_'. The remainder
 * of the name should match the regex [a-z][a-z0-9]*.
 *
 * These variables can be accessed with {@link get_im_var()} and {@link get_qt_var()},
 * - to be clear, ->get_im_var('x') gets the variable with name '!x' -
 * and values whose names start with '_' can be set using {@link set_im_var()}
 * and {@link set_qt_var()}. There are some other methods like {@link has_im_var()}
 * to check wether a varaible with a particular name is set, and {@link get_im_data()}
 * to get all the interaction model data as an associative array.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_attempt_step {
    /** @var integer if this attempts is stored in the question_attempts table, the id of that row. */
    private $id = null;

    /** @var question_state one of the {@link question_state} constants. The state after this step. */
    private $state;

    /** @var null|number the fraction (grade on a scale of minfraction .. 1.0) or null. */
    private $fraction = null;

    /** @var integer the timestamp when this step was created. */
    private $timecreated;

    /** @var integer the id of the user resonsible for creating this step. */
    private $userid;

    /** @var array name => value pairs. The submitted data. */
    private $data;

    /**
     * You should not need to call this constructor in your own code. Steps are
     * normally created by {@link question_attempt} methods like
     * {@link question_attempt::process_action()}.
     * @param array $data the submitted data that defines this step.
     * @param integer $timestamp the time to record for the action. (If not given, use now.)
     * @param integer $userid the user to attribute the aciton to. (If not given, use the current user.)
     */
    public function __construct($data = array(), $timecreated = null, $userid = null) {
        global $USER;
        $this->state = question_state::$unprocessed;
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

    /** @return question_state The state after this step. */
    public function get_state() {
        return $this->state;
    }

    /**
     * Set the state. Normally only called by interaction models.
     * @param question_state $state one of the {@link question_state} constants.
     */
    public function set_state($state) {
        $this->state = $state;
    }

    /**
     * @return null|number the fraction (grade on a scale of minfraction .. 1.0)
     * or null if this step has not been marked.
     */
    public function get_fraction() {
        return $this->fraction;
    }

    /**
     * Set the fraction. Normally only called by interaction models.
     * @param null|number $fraction the fraction to set.
     */
    public function set_fraction($fraction) {
        $this->fraction = $fraction;
    }

    /** @return integer the id of the user resonsible for creating this step. */
    public function get_user_id() {
        return $this->userid;
    }

    /** @return integer the timestamp when this step was created. */
    public function get_timecreated() {
        return $this->timecreated;
    }

    /**
     * @param string $name the name of a question type variable to look for in the submitted data.
     * @return boolean whether a variable with this name exists in the question type data.
     */
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

    /**
     * Set a cached question type variable.
     * @param string $name the name of the variable to set. Must match _[a-z][a-z0-9]*.
     * @param string $value the value to set.
     */
    public function set_qt_var($name, $value) {
        if ($name[0] != '_') {
            throw new Exception('Cannot set question type data ' . $name . ' on an attempt step. You can only set variables with names begining with _.');
        }
        $this->data[$name] = $value;
    }

    /**
     * Get all the question type variables.
     * @param array name => value pairs.
     */
    public function get_qt_data() {
        $result = array();
        foreach ($this->data as $name => $value) {
            if ($name[0] != '!') {
                $result[$name] = $value;
            }
        }
        return $result;
    }

    /**
     * @param string $name the name of an interaction model variable to look for in the submitted data.
     * @return boolean whether a variable with this name exists in the question type data.
     */
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

    /**
     * Set a cached interaction model variable.
     * @param string $name the name of the variable to set. Must match _[a-z][a-z0-9]*.
     * @param string $value the value to set.
     */
    public function set_im_var($name, $value) {
        if ($name[0] != '_') {
            throw new Exception('Cannot set question type data ' . $name . ' on an attempt step. You can only set variables with names begining with _.');
        }
        return $this->data['!' . $name] = $value;
    }

    /**
     * Get all the interaction model variables.
     * @param array name => value pairs.
     */
    public function get_im_data() {
        $result = array();
        foreach ($this->data as $name => $value) {
            if ($name[0] == '!') {
                $result[substr($name, 1)] = $value;
            }
        }
        return $result;
    }

    /**
     * Get all the submitted data, but not the cached data. Interaction model
     * variables have the ! at the start of their name. This is only really
     * intended for use by {@link question_attempt::regrade()}, it should not
     * be considered part of the public API.
     * @param array name => value pairs.
     */
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
     * Get all the data. Interaction model variables have the ! at the start of
     * their name. This is only intended for internal use, for example by
     * {@link question_engine_data_mapper::insert_question_attempt_step()},
     * however, it can ocasionally be useful in test code. It should not be
     * considered part of the public API of this class.
     * @param array name => value pairs.
     */
    public function get_all_data() {
        return $this->data;
    }

    /**
     * Create a question_attempt_step from records loaded from the database.
     * @param array $records Raw records loaded from the database.
     * @param integer $stepid The id of the records to extract.
     * @return question_attempt_step The newly constructed question_attempt_step.
     */
    public static function load_from_records(&$records, $attemptstepid) {
        $currentrec = current($records);
        while ($currentrec->attemptstepid != $attemptstepid) {
            $currentrec = next($records);
            if (!$currentrec) {
                throw new Exception("Question attempt step $attemptstepid not found in the database.");
            }
        }

        $record = $currentrec;
        $data = array();
        while ($currentrec && $currentrec->attemptstepid == $attemptstepid) {
            if ($currentrec->name) {
                $data[$currentrec->name] = $currentrec->value;
            }
            $currentrec = next($records);
        }

        $step = new question_attempt_step_read_only($data, $record->timecreated, $record->userid);
        $step->state = question_state::get($record->state);
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
 * created and there is no acutal step.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_null_step {
    public function get_state() {
        return question_state::$notstarted;
    }

    public function set_state($state) {
        throw new Exception('This question has not been started.');
    }

    public function get_fraction() {
        return null;
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
    /** Called when a field of the question_usage_by_activity is changed. */
    public function notify_modified();

    /**
     * Called when the fields of a question attempt in this usage are modified.
     * @param question_attempt $qa the newly added question attempt.
     */
    public function notify_attempt_modified(question_attempt $qa);

    /**
     * Called when a new question attempt is added to this usage.
     * @param question_attempt $qa the newly added question attempt.
     */
    public function notify_attempt_added(question_attempt $qa);

    /**
     * Called when a new step is added to a question attempt in this usage.
     * @param $step the new step.
     * @param $qa the usage it is being added to.
     * @param $seq the sequence number of the new step.
     */
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