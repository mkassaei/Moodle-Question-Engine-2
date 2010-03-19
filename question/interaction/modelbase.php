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
 * Defines the quetsion interaction model base class
 *
 * @package moodlecore
 * @subpackage questioninteractions
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * The base class for question interaction models.
 *
 * A question interaction model is used by the question engine, specifically by
 * a {@link question_attempt} to manage the flow of actions a student can take
 * as they work through a question, and later, as a teacher manually grades it.
 * In turn, the interaction model will delegate certain processing to the
 * relevant {@link question_definition}.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_interaction_model {
    /**
     * Certain interaction models are definitive of a  way that questions can
     * behave when attempted. For example deferredfeedback model, interactive
     * model, etc. These are the options that should be listed in the
     * user-interface. These models should define the class constant
     * IS_ARCHETYPAL as true. Other models are more implementation details, for
     * example the informationitem model, or a special subclass like
     * interactive_adapted_for_my_qtype. These models should IS_ARCHETYPAL as
     * false.
     * @var boolean
     */
    const IS_ARCHETYPAL = false;

    /** @var question_attempt the question attempt we are managing. */
    protected $qa;
    /** @var question_definition shortcut to $qa->get_question(). */
    protected $question;

    /**
     * Normally you should not call this constuctor directly. The appropriate
     * interaction model object is created automatically as part of
     * {@link question_attempt::start()}.
     * @param question_attempt $qa the question attempt we will be managing.
     * @param string $preferredmodel the type of interaction that was actually
     *      requested. This information is not needed in most cases, the type of
     *      subclass is enough, but occasionally it is needed.
     */
    public function __construct(question_attempt $qa, $preferredmodel) {
        $this->qa = $qa;
        $this->question = $qa->get_question();
        $requiredclass = $this->required_question_definition_type();
        if (!$this->question instanceof $requiredclass) {
            throw new Exception('This interaction model (' . $this->get_name() .
                    ') cannot work with this question (' . get_class($this->question) . ')');
        }
    }

    /**
     * Most interaction models can only work with {@link question_definition}s
     * of a particular subtype, or that implement a particular interface.
     * This method lets the interaction model document that. The type of
     * question passed to the constructor is then checked against this type.
     * @return string class/interface name.
     */
    public abstract function required_question_definition_type();

    /**
     * @return string the name of this interaction model. For example the name of
     * qim_mymodle is 'mymodel'.
     */
    public function get_name() {
        return substr(get_class($this), 4);
    }

    /**
     * Cause the question to be renderered. This gets the appropriate interaction
     * model renderer using {@link get_renderer()}, and adjusts the display
     * options using {@link adjust_display_options()} and then calls
     * {@link core_question_renderer::question()} to do the work.
     * @param question_display_options $options controls what should and should not be displayed.
     * @param unknown_type $number the question number to display.
     * @param core_question_renderer $qoutput the question renderer that will coordinate everything.
     * @param qtype_renderer $qtoutput the question type renderer that will be helping.
     * @return HTML fragment.
     */
    public function render(question_display_options $options, $number,
            core_question_renderer $qoutput, qtype_renderer $qtoutput) {
        $qimoutput = $this->get_renderer();
        $options = clone($options);
        $this->adjust_display_options($options);
        return $qoutput->question($this->qa, $qimoutput, $qtoutput, $options, $number);
    }

    /**
     * @return qim_renderer get the appropriate renderer to use for this model.
     */
    public function get_renderer() {
        return renderer_factory::get_renderer(get_class($this));
    }

    /**
     * Make any changes to the display options before a question is rendered, so
     * that it can be displayed in a way that is appropriate for the statue it is
     * currently in. For example, by default, if the question is finished, we
     * ensure that it is only ever displayed read-only.
     * @param question_display_options $options the options to adjust. Just change
     * the properties of this object - objects are passed by referece.
     */
    public function adjust_display_options(question_display_options $options) {
        if ($this->qa->get_state()->is_finished()) {
            $options->readonly = true;
        } else {
            $options->hide_all_feedback();
        }
    }

    /**
     * Get the most applicable hint for the question in its current state.
     * @return question_hint|null the most applicable hint, or null, if none.
     */
    public function get_applicable_hint() {
        return null;
    }

    /**
     * What is the minimum fraction that can be scored for this question.
     * Normally this will be based on $this->question->init_first_step($step),
     * but may be modified in some way by the model.
     *
     * @return number the minimum fraction when this question is attempted under
     * this model.
     */
    public function get_min_fraction() {
        return 0;
    }

    /**
     * Adjust a random guess score for a question using this model. You have to
     * do this without knowing details of the specific question, or which usage
     * it is in.
     * @param number $fraction the random guess score from the question type.
     * @return number the adjusted fraction.
     */
    public static function adjust_random_guess_score($fraction) {
        return $fraction;
    }

    /**
     * Return an array of the interaction model variables that could be submitted
     * as part of a question of this type, with their types, so they can be
     * properly cleaned.
     * @return array variable name => PARAM_... constant.
     */
    public function get_expected_data() {
        if (!$this->qa->get_state()->is_finished()) {
            return array();
        }

        $vars = array('comment' => PARAM_RAW);
        if ($this->qa->get_max_mark()) {
            $vars['mark'] = PARAM_NUMBER;
            $vars['maxmark'] = PARAM_NUMBER;
        }
        return $vars;
    }

    /**
     * Return an array of question type variables for the question in its current
     * state. Normally, if {@link adjust_display_options()} would set
     * {@link question_display_options::$readonly} to true, then this method
     * should return an empty array, otherwise it should return
     * $this->question->get_expected_data(). Thus, there should be little need to
     * override this method.
     * @return array|string variable name => PARAM_... constant, or, as a special case
     *      that should only be used in unavoidable, the constant question_attempt::USE_RAW_DATA
     *      meaning take all the raw submitted data belonging to this question.
     */
    public function get_expected_qt_data() {
        $fakeoptions = new question_display_options();
        $fakeoptions->readonly = false;
        $this->adjust_display_options($fakeoptions);
        if ($fakeoptions->readonly) {
            return array();
        } else {
            return $this->question->get_expected_data();
        }
    }

    /**
     * Return an array of any im variables, and the value required to get full
     * marks.
     * @return array variable name => value.
     */
    public function get_correct_response() {
        return array();
    }

    /**
     * Generate a brief, plain-text, summary of this question. This is used by
     * various reports. This should show the particular variant of the question
     * as presented to students. For example, the calculated quetsion type would
     * fill in the particular numbers that were presented to the student.
     * This method will return null if such a summary is not possible, or
     * inappropriate.
     *
     * Normally, this method delegates to {question_definition::get_question_summary()}.
     *
     * @return string|null a plain text summary of this question.
     */
    public function get_question_summary() {
        return $this->question->get_question_summary();
    }

    /**
     * Generate a brief, plain-text, summary of the correct answer to this question.
     * This is used by various reports, and can also be useful when testing.
     * This method will return null if such a summary is not possible, or
     * inappropriate.
     *
     * @return string|null a plain text summary of the right answer to this question.
     */
    public function get_right_answer_summary() {
        return null;
    }

    /**
     * Used by {@link start_based_on()} to get the data needed to start a new
     * attempt from the point this attempt has go to.
     * @return array name => value pairs.
     */
    public function get_resume_data() {
        $olddata = $this->qa->get_step(0)->get_all_data();
        $olddata = $this->qa->get_last_qt_data() + $olddata;
        $olddata = $this->get_our_resume_data() + $olddata;
        return $olddata;
    }

    /**
     * Used by {@link start_based_on()} to get the data needed to start a new
     * attempt from the point this attempt has go to.
     * @return unknown_type
     */
    protected function get_our_resume_data() {
        return array();
    }

    /**
     * Initialise the first step in a question attempt.
     *
     * This method must call $this->question->init_first_step($step), and may
     * perform additional processing if the model requries it.
     *
     * @param question_attempt_step $step the step being initialised.
     */
    public function init_first_step(question_attempt_step $step) {
        $this->question->init_first_step($step);
    }

    /**
     * The main entry point for processing an action.
     *
     * All the various operations that can be performed on a
     * {@link question_attempt} get channeled through this function, except for
     * {@link question_attempt::start()} which goes to {@link init_first_step()}.
     * {@link question_attempt::finish()} becomes an action with im vars
     * finish => 1, and manual comment/grade becomes an action with im vars
     * comment => comment text, and mark => ..., max_mark => ... if the question
     * is graded.
     *
     * This method should first determine whether the action is significant. For
     * example, if no actual action is being performed, but instead the current
     * responses are being saved, and there has been no change since the last
     * set of responses that were saved, this the action is not significatn. In
     * this case, this method should return {@link question_attempt::DISCARD}.
     * Otherwise it should return {@link question_attempt::KEEP}.
     *
     * If the action is significant, this method should also perform any
     * necessary updates to $pendingstep. For example, it should call
     * {@link question_attempt_step::set_state()} to set the state that results
     * from this action, and if this is a grading action, it should call
     * {@link question_attempt_step::set_fraction()}.
     *
     * This method can also call {@link question_attempt_step::set_im_var()} to
     * store additional infomation. There are two main uses for this. This can
     * be used to store the result of any randomisation done. It is important to
     * store the result of randomisation once, and then in future use the same
     * outcome if the actions are ever replayed. This is how regrading works.
     * The other use is to cache the result of expensive computations performed
     * on the raw response data, so that subsequent display and review of the
     * question does not have to repeat the same expensive computations.
     *
     * Often this method is implemented as a dispatching method that examines
     * the pending step to determine the kind of action being performed, and
     * then calls a more specific method like {@link process_save()} or
     * {@link process_comment()}. Look at some of the standard interaction
     * models for examples.
     *
     * @param question_attempt_pending_step $pendingstep a partially initialised step
     *      containing all the information about the action that is being peformed.
     *      This information can be accessed using {@link question_attempt_step::get_im_var()}.
     * @return boolean either {@link question_attempt::KEEP} or {@link question_attempt::DISCARD}
     */
    public abstract function process_action(question_attempt_pending_step $pendingstep);

    /**
     * Implementation of processing a manual comment/grade action that should
     * be suitable for most subclasses.
     * @param question_attempt_pending_step $pendingstep a partially initialised step
     *      containing all the information about the action that is being peformed.
     * @return boolean either {@link question_attempt::KEEP}
     */
    public function process_comment(question_attempt_pending_step $pendingstep) {
        $laststep = $this->qa->get_last_step();

        if ($pendingstep->has_im_var('mark')) {
            $pendingstep->set_fraction($pendingstep->get_im_var('mark') /
                    $pendingstep->get_im_var('maxmark'));
        }

        $pendingstep->set_state($laststep->get_state()->
                corresponding_commented_state($pendingstep->get_fraction()));
        return question_attempt::KEEP;
    }
}


/**
 * A subclass of {@link question_interaction_model} that implements a save
 * action that is suitable for most questions that implement the
 * {@link question_manually_gradable} interface.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_interaction_model_with_save extends question_interaction_model {
    public function required_question_definition_type() {
        return 'question_manually_gradable';
    }

    /**
     * Work out whether the response in $pendingstep are significantly different
     * from the last set of responses we have stored.
     * @param question_attempt_step $pendingstep contains the new responses.
     * @return boolean whether the new response is the same as we already have.
     */
    protected function is_same_response(question_attempt_step $pendingstep) {
        return $this->question->is_same_response(
                $this->qa->get_last_step()->get_qt_data(), $pendingstep->get_qt_data());
    }

    /**
     * Work out whether the response in $pendingstep represent a complete answer
     * to the question. Normally this will call
     * {@link question_manually_gradable::is_complete_response}, but some
     * interaction models, for example the CBM ones, have their own parts to the
     * response.
     * @param question_attempt_step $pendingstep contains the new responses.
     * @return boolean whether the new response is complete.
     */
    protected function is_complete_response(question_attempt_step $pendingstep) {
        return $this->question->is_complete_response($pendingstep->get_qt_data());
    }

    /**
     * Implementation of processing a save action that should be suitable for
     * most subclasses.
     * @param question_attempt_pending_step $pendingstep a partially initialised step
     *      containing all the information about the action that is being peformed.
     * @return boolean either {@link question_attempt::KEEP} or {@link question_attempt::DISCARD}
     */
    public function process_save(question_attempt_pending_step $pendingstep) {
        if ($this->qa->get_state()->is_finished()) {
            return question_attempt::DISCARD;
        } else if (!$this->qa->get_state()->is_active()) {
            throw new Exception('Question is not active, cannot process_actions.');
        }

        if ($this->is_same_response($pendingstep)) {
            return question_attempt::DISCARD;
        }

        if ($this->is_complete_response($pendingstep)) {
            $pendingstep->set_state(question_state::$complete);
        } else {
            $pendingstep->set_state(question_state::$todo);
        }
        return question_attempt::KEEP;
    }
}


/**
 * This helper class contains the constants and methods required for
 * manipulating scores for certainly based marking.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_cbm {
    /**#@+ @var integer named constants for the certainty levels. */
    const LOW = 1;
    const MED = 2;
    const HIGH = 3;
    /**#@-*/

    /** @var array list of all the certainty levels. */
    public static $certainties = array(self::LOW, self::MED, self::HIGH);

    /**#@+ @var array coefficients used to adjust the fraction based on certainty.. */
    protected static $factor = array(
        self::LOW => 0.333333333333333,
        self::MED => 1.333333333333333,
        self::HIGH => 3,
    );
    protected static $offset = array(
        self::LOW => 0,
        self::MED => -0.666666666666667,
        self::HIGH => -2,
    );
    /**#@-*/

    /**
     * @return integer the default certaintly level that should be assuemd if
     * the student does not choose one.
     */
    public static function default_certainty() {
        return self::LOW;
    }

    /**
     * Given a fraction, and a certainly, compute the adjusted fraction.
     * @param number $fraction the raw fraction for this question.
     * @param integer $certainty one of the certainly level constants.
     * @return number the adjusted fraction taking the certainly into account.
     */
    public static function adjust_fraction($fraction, $certainty) {
        return self::$offset[$certainty] + self::$factor[$certainty] * $fraction;
    }

    /**
     * @param integer $certainty one of the LOW/MED/HIGH constants.
     * @return string a textual desciption of this certainly.
     */
    public static function get_string($certainty) {
        return get_string('certainty' . $certainty, 'qim_deferredcbm');
    }

    public static function summary_with_certainty($summary, $certainty) {
        if (is_null($certainty)) {
            return $summary;
        }
        return $summary . ' [' . self::get_string($certainty) . ']';
    }
}
