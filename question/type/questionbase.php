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
 * This file defines the class {@link question_definition} and its subclasses.
 *
 * @package moodlecore
 * @subpackage questiontypes
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * The definition of a question of a particular type.
 *
 * This class is a close match to the question table in the database.
 * Definitions of question of a particular type normally subclass one of the
 * more specific classes {@link question_with_responses},
 * {@link question_graded_automatically} or {@link question_information_item}.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_definition {
    /** @var integer id of the question in the datase, or null if this question
     * is not in the database. */
    public $id;

    /** @var integer question category id. */
    public $category;

    /** @var integer parent question id. */
    public $parent = 0;

    /** @var question_type the question type this question is. */
    public $qtype;

    /** @var string question name. */
    public $name;

    /** @var string question text. */
    public $questiontext;

    /** @var integer question test format. */
    public $questiontextformat;

    /** @var string question general feedback. */
    public $generalfeedback;

    /** @var number what this quetsion is marked out of, by default. */
    public $defaultmark = 1;

    /** @var integer How many question numbers this question consumes. */
    public $length = 1;

    /** @var number penalty factor of this question. */
    public $penalty = 0;

    /** @var string unique identifier of this question. */
    public $stamp;

    /** @var string unique identifier of this version of this question. */
    public $version;

    /** @var boolean whethre this question has been deleted/hidden in the question bank. */
    public $hidden = 0;

    /** @var integer timestamp when this question was created. */
    public $timecreated;

    /** @var integer timestamp when this question was modified. */
    public $timemodified;

    /** @var integer userid of the use who created this question. */
    public $createdb;

    /** @var integer userid of the use who modified this question. */
    public $modifiedby;

    /** @var array of question_hints. */
    public $hints = array();

    /**
     * Constructor. Normally to get a question, you call
     * {@link question_bank::load_question()}, but questions can be created
     * directly, for example in unit test code.
     * @return unknown_type
     */
    public function __construct() {
    }

    /**
     * @return the name of the question type (for example multichoice) that this
     * question is.
     */
    public function get_type_name() {
        return $this->qtype->name();
    }

    /**
     * Creat the appropriate interaction model for an attempt at this quetsion,
     * given the desired (archetypal) interaction model.
     *
     * This default implementation will suit most normal graded questions.
     *
     * If your question is of a patricular type, then it may need to do something
     * different. For example, if your question can only be graded manually, then
     * it should probably return a manualgraded interaction model, irrespective of
     * what is asked for.
     *
     * If your question wants to do somthing especially complicated is some situations,
     * then you may wish to return a particular interaction model related to the
     * one asked for. For example, you migth want to return a
     * qim_interactive_adapted_for_myqtype.
     *
     * @param question_attempt $qa the attempt we are creating an interaction
     *      model for.
     * @param string $preferredmodel the requested type of interaction.
     * @return question_interaction_model the new interaction model object.
     */
    public function make_interaction_model(question_attempt $qa, $preferredmodel) {
        return question_engine::make_archetypal_interaction_model($preferredmodel, $qa);
    }

    /**
     * Initialise the first step of an attempt at this quetsion.
     *
     * For example, the multiple choice question type uses this method to
     * randomly shuffle the choices, if that option has been set in the question.
     * It then stores that order by calling $step->set_qt_var(...).
     *
     * @param question_attempt_step $step the step to be initialised.
     */
    public function init_first_step(question_attempt_step $step) {
    }

    /**
     * Some questions can return a negative mark if the student gets it wrong.
     *
     * This method returns the lowest mark the question can return, on the
     * fraction scale. that is, where the maximum possible mark is 1.0.
     *
     * @return number minimum fraction this question will ever return.
     */
    public function get_min_fraction() {
        return 0;
    }

    /**
     * @return qtype_renderer the renderer to use for outputting this question.
     */
    public function get_renderer() {
        return renderer_factory::get_renderer('qtype_' . $this->qtype->name());
    }

    /**
     * What data may be included in the form submission when a student submits
     * this question in its current state?
     *
     * This information is used in calls to optional_param. The parameter name
     * has {@link question_attempt::get_field_prefix()} automatically prepended.
     *
     * @return array|string variable name => PARAM_... constant, or, as a special case
     *      that should only be used in unavoidable, the constant question_attempt::USE_RAW_DATA
     *      meaning take all the raw submitted data belonging to this question.
     */
    public abstract function get_expected_data();

    /**
     * What data would need to be submitted to get this question correct.
     * If there is more than one correct answer, this question only needs to
     * return one possibility.
     *
     * @return array parameter name => value.
     */
    public abstract function get_correct_response();

    /**
     * Apply {@link format_text()} to some content with appropriate settings for
     * this question.
     *
     * @param string $text some content that needs to be output.
     * @param boolean $clean Whether the HTML needs to be cleaned. Generally,
     *      parts of the question do not need to be cleaned, and student input does.
     * @return string the text formatted for output by format_text.
     */
    public function format_text($text, $clean = false) {
        $formatoptions = new stdClass;
        $formatoptions->noclean = !$clean;
        $formatoptions->para = false;

        return format_text($text, $this->questiontextformat, $formatoptions);
    }

    /** @return the result of applying {@link format_text()} to the question text. */
    public function format_questiontext() {
        return $this->format_text($this->questiontext);
    }

    /** @return the result of applying {@link format_text()} to the general feedback. */
    public function format_generalfeedback() {
        return $this->format_text($this->generalfeedback);
    }
}


/**
 * This class represents a 'question' that actually does not allow the student
 * to respond, like the description 'question' type.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_information_item extends question_definition {
    public function __construct() {
        parent::__construct();
        $this->defaultgrade = 0;
        $this->penalty = 0;
        $this->length = 0;
    }

    public function make_interaction_model(question_attempt $qa, $preferredmodel) {
        question_engine::load_interaction_model_class('informationitem');
        return new qim_informationitem($qa, $preferredmodel);
    }

    public function get_expected_data() {
        return array();
    }

    public function get_correct_response() {
        return array();
    }
}


/**
 * Interface that a {@link question_definition} must implement to be usable by
 * the manual graded interaction model.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface question_manually_gradable {
    /**
     * Used by many of the interaction models, to work out whether the student's
     * response to the question is complete. That is, whether the question attempt
     * should move to the COMPLETE or INCOMPLETE state.
     *
     * @param array $response responses, as returned by {@link question_attempt_step::get_qt_data()}.
     * @return boolean whether this response is a complete answer to this question.
     */
    public function is_complete_response(array $response);

    /**
     * Use by many of the interaction models to determine whether the student's
     * response has changed. This is normally used to determine that a new set
     * of responses can safely be discarded.
     *
     * @param array $prevresponse the responses previously recorded for this question,
     *      as returned by {@link question_attempt_step::get_qt_data()}
     * @param array $newresponse the new responses, in the same format.
     * @return boolean whether the two sets of responses are the same - that is
     *      whether the new set of responses can safely be discarded.
     */
    public function is_same_response(array $prevresponse, array $newresponse);
}


/**
 * Interface that a {@link question_definition} must implement to be usable by
 * the various automatic grading interaction models.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface question_automatically_gradable extends question_manually_gradable {
    /**
     * Use by many of the interaction models to determine whether the student
     * has provided enough of an answer for the question to be graded automatically,
     * or whether it must be considered aborted.
     *
     * @param array $response responses, as returned by {@link question_attempt_step::get_qt_data()}.
     * @return boolean whether this response can be graded.
     */
    public function is_gradable_response(array $response);

    /**
     * Grade a response to the question, returning a fraction between get_min_fraction() and 1.0,
     * and the corresponding state CORRECT, PARTIALLY_CORRECT or INCORRECT.
     * @param array $response responses, as returned by {@link question_attempt_step::get_qt_data()}.
     * @return array (number, integer) the fraction, and the state.
     */
    public function grade_response(array $response);
}


/**
 * This class represents a real question. That is, one that is not a
 * {@link question_information_item}.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_with_responses extends question_definition
        implements question_manually_gradable {
}


/**
 * This class represents a question that can be graded automatically.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_graded_automatically extends question_with_responses
        implements question_automatically_gradable {
    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    abstract public function get_validation_error(array $response);
}


/**
 * This class represents a question that can be graded automatically by using
 * a {@link question_grading_strategy}.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_graded_by_strategy extends question_graded_automatically {
    /** @var question_grading_strategy the strategy to use for grading. */
    protected $gradingstrategy;

    /** @param question_grading_strategy  $strategy the strategy to use for grading. */
    public function __construct(question_grading_strategy $strategy) {
        parent::__construct();
        $this->gradingstrategy = $strategy;
    }

    public function get_correct_response() {
        $answer = $this->get_correct_answer();
        if (!$answer) {
            return array();
        }

        return array('answer' => $answer->answer);
    }

    /**
     * Get an answer that contains the feedback and fraction that should be
     * awarded for this resonse.
     * @param array $response a response.
     * @return question_answer the matching answer.
     */
    public function get_matching_answer(array $response) {
        return $this->gradingstrategy->grade($response);
    }

    /**
     * @return question_answer an answer that contains the a response that would
     *      get full marks.
     */
    public function get_correct_answer() {
        return $this->gradingstrategy->get_correct_answer();
    }

    public function grade_response(array $response) {
        $answer = $this->get_matching_answer($response);
        if ($answer) {
            return array($answer->fraction, question_state::graded_state_for_fraction($answer->fraction));
        } else {
            return array(0, question_state::$gradedwrong);
        }
    }
}


/**
 * Class to represent a question answer, loaded from the question_answers table
 * in the database.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_answer {
    /** @var string the answer. */
    public $answer;

    /** @var number the fraction this answer is worth. */
    public $fraction;

    /** @var string the feedback for this answer. */
    public $feedback;

    /**
     * Constructor.
     * @param string $answer the answer.
     * @param number $fraction the fraction this answer is worth.
     * @param string $feedback the feedback for this answer.
     */
    public function __construct($answer, $fraction, $feedback) {
        $this->answer = $answer;
        $this->fraction = $fraction;
        $this->feedback = $feedback;
    }
}


/**
 * Class to represent a hint associated with a question.
 * Used by iteractive mode, etc. A question has an array of these.
 *
 * @copyright © 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_hint {
    /** @var The feedback hint to be shown. */
    public $hint;

    /**
     * Constructor.
     * @param string $hint The hint text
     */
    public function __construct($hint) {
        $this->hint = $hint;
    }

    /**
     * Create a basic hint from a row loaded from the question_hints table in the database.
     * @param object $row with $row->hint set.
     * @return question_hint
     */
    public static function load_from_record($row) {
        return new question_hint($row->hint);
    }
}


/**
 * An extension of {@link question_hint} for questions like match and multiple
 * choice with multile answers, where there are options for whether to show the
 * number of parts right at each stage, and to reset the wrong parts.
 *
 * @copyright © 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_hint_with_parts extends question_hint {
    /** @var boolean option to show the number of sub-parts of the question that were right. */
    public $shownumcorrect;

    /** @var boolean option to clear the parts of the question that were wrong on retry. */
    public $clearwrong;

    /**
     * Constructor.
     * @param string $hint The hint text
     * @param boolean $shownumcorrect whether the number of right parts should be shown
     * @param boolean $clearwrong whether the wrong parts should be reset.
     */
    public function __construct($hint, $shownumcorrect, $clearwrong) {
        parent::__construct($hint);
        $this->shownumcorrect = $shownumcorrect;
        $this->clearwrong = $clearwrong;
    }

    /**
     * Create a basic hint from a row loaded from the question_hints table in the database.
     * @param object $row with $row->hint, ->shownumcorrect and ->clearwrong set.
     * @return question_hint_with_parts
     */
    public static function load_from_record($row) {
        return new question_hint_with_parts($row->hint, $row->shownumcorrect, $row->clearwrong);
    }
}


/**
 * This question_grading_strategy interface. Used to share grading code between
 * questions that that subclass {@link question_graded_by_strategy}.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface question_grading_strategy {
    /**
     * Return a question answer that describes the outcome (fraction and feeback)
     * for a particular respons.
     * @param array $response the response.
     * @return question_answer the answer describing the outcome.
     */
    public function grade(array $response);

    /**
     * @return question_answer an answer that contains the a response that would
     *      get full marks.
     */
    public function get_correct_answer();
}


/**
 * This interface defines the methods that a {@link question_definition} must
 * implement if it is to be graded by the
 * {@link question_first_matching_answer_grading_strategy}.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface question_response_answer_comparer {
    /** @return array of {@link question_answers}. */
    public function get_answers();

    /**
     * @param array $response the response.
     * @param question_answer $answer an answer.
     * @return boolean whether the response matches the answer.
     */
    public function compare_response_with_answer(array $response, question_answer $answer);
}


/**
 * This grading strategy is used by question types like shortanswer an numerical.
 * It gets a list of possible answers from the question, and returns the first one
 * that matches the given response. It returns the first answer with fraction 1.0
 * when asked for the correct answer.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_first_matching_answer_grading_strategy implements question_grading_strategy {
    /**
     * @var question_response_answer_comparer (presumably also a
     * {@link question_definition}) the question we are doing the grading for.
     */
    protected $question;

    /**
     * @param question_response_answer_comparer $question (presumably also a
     * {@link question_definition}) the question we are doing the grading for.
     */
    public function __construct(question_response_answer_comparer $question) {
        $this->question = $question;
    }

    public function grade(array $response) {
        foreach ($this->question->get_answers() as $answer) {
            if ($this->question->compare_response_with_answer($response, $answer)) {
                return $answer;
            }
        }
        return null;
    }

    public function get_correct_answer() {
        foreach ($this->question->get_answers() as $answer) {
            if ($answer->fraction > 0.9999999) {
                return $answer;
            }
        }
        return null;
    }
}