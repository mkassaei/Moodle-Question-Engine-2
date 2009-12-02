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

    public function __construct() {
    }

    public function get_type_name() {
        return $this->qtype->name();
    }

    public function make_interaction_model(question_attempt $qa, $preferredmodel) {
        return question_engine::make_archetypal_interaction_model($preferredmodel, $qa);
        question_engine::load_interaction_model_class($preferredmodel);
        $class = 'qim_' . $preferredmodel;
        return new $class($qa);
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
     * @return number minimum mark this question will every return.
     */
    public function get_min_fraction() {
        return 0;
    }

    /**
     * Get the renderer to use for outputting this question.
     * @return unknown_type
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
     * @return array parameter name => PARAM_... type constant.
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
    public function make_interaction_model(question_attempt $qa, $preferredmodel) {
        question_engine::load_interaction_model_class('informationitem');
        return new qim_informationitem($qa);
    }

    public function __construct() {
        parent::__construct();
        $this->defaultgrade = 0;
        $this->penalty = 0;
        $this->length = 0;
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
}


/**
 * This class represents a question that can be graded automatically.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class question_graded_by_strategy extends question_graded_automatically {
    protected $gradingstrategy;

    public function __construct($strategy) {
        parent::__construct();
        $this->gradingstrategy = $strategy;
    }

    public function get_matching_answer(array $response) {
        return $this->gradingstrategy->grade($response);
    }

    public function get_correct_response() {
        $answer = $this->get_correct_answer();
        if (!$answer) {
            return array();
        }

        return array('answer' => $answer->answer);
    }

    public function get_correct_answer() {
        return $this->gradingstrategy->get_correct_answer();
    }

    /**
     * Grade a response to the question, returning a fraction between get_min_fraction() and 1.0,
     * and the corresponding state CORRECT, PARTIALLY_CORRECT or INCORRECT.
     * @param array $response responses, as returned by {@link question_attempt_step::get_qt_data()}.
     * @return array (number, integer) the fraction, and the state.
     */
    public function grade_response(array $response) {
        $answer = $this->get_matching_answer($response);
        if ($answer) {
            return array($answer->fraction, question_state::graded_state_for_fraction($answer->fraction));
        } else {
            return array(0, question_state::GRADED_INCORRECT);
        }
    }
}


class question_answer {
    public $answer;
    public $fraction;
    public $feedback;
    public function __construct($answer, $fraction, $feedback) {
        $this->answer = $answer;
        $this->fraction = $fraction;
        $this->feedback = $feedback;
    }
}


interface question_grading_strategy {
    /**
     *
     * @param $response
     * @return question_answer
     */
    public function grade(array $response);

    /**
     * @return question_answer
     */
    public function get_correct_answer();
}


interface question_response_answer_comparer {
    /**
     * @return array of {@link question_answers}.
     */
    public function get_answers();

    /**
     * @param $response
     * @param $answer
     * @return boolean
     */
    public function compare_response_with_answer(array $response, question_answer $answer);
}


class question_first_matching_answer_grading_strategy implements question_grading_strategy {
    protected $question;
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