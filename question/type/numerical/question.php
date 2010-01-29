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
 * Numerical question definition class.
 *
 * @package qtype_numerical
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/type/numerical/questiontype.php');


/**
 * Represents a numerical question.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_numerical_question extends question_graded_by_strategy
        implements question_response_answer_comparer {
    /** @var array of question_answer. */
    public $answers = array();
    public $units = array();

    public function __construct() {
        parent::__construct(new question_first_matching_answer_grading_strategy($this));
    }

    public function get_expected_data() {
        return array('answer' => PARAM_TRIM);
    }

    public function is_complete_response(array $response) {
        return array_key_exists('answer', $response) &&
                ($response['answer'] || $response['answer'] === '0' || $response['answer'] === 0);
    }

    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('youmustenterananswer', 'qtype_numerical');
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        return (empty($prevresponse['answer']) && empty($newresponse['answer'])) ||
                (!empty($prevresponse['answer']) && !empty($newresponse['answer']) &&
                $prevresponse['answer'] == $newresponse['answer']);
    }

    public function get_answers() {
        return $this->answers;
    }

    public function compare_response_with_answer(array $response, question_answer $answer) {
        $value = qtype_numerical::apply_unit($response['answer'], $this->units);
        return $answer->within_tolerance($value);
    }
}


/**
 * Subclass of {@link question_answer} with the extra information required by
 * the numerical question type.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_numerical_answer extends question_answer {
    /** @var float allowable margin of error. */
    public $tolerance;
    /** @var integer|string see {@link get_tolerance_interval()} for the meaning of this value. */
    public $tolerancetype = 2;

    public function __construct($answer, $fraction, $feedback, $tolerance) {
        parent::__construct($answer, $fraction, $feedback);
        $this->tolerance = abs($tolerance);
    }

    public function get_tolerance_interval() {
        if ($this->answer === '*') {
            throw new Exception('Cannot work out tolerance interval for answer *.');
        }

        // We need to add a tiny fraction depending on the set precision to make
        // the comparison work correctly, otherwise seemingly equal values can
        // yield false. See MDL-3225.
        $tolerance = (float) $this->tolerance + pow(10, -1 * ini_get('precision'));

        switch ($this->tolerancetype) {
            case 1: case 'relative':
                $range = abs($this->answer) * $tolerance;
                return array($this->answer - $range, $this->answer + $range);

            case 2: case 'nominal':
                $tolerance = $this->tolerance + pow(10, -1 * ini_get('precision')) *
                        min(1, abs($this->answer));
                return array($this->answer - $tolerance, $this->answer + $tolerance);

            case 3: case 'geometric':
                $quotient = 1 + abs($tolerance);
                return array($this->answer / $quotient, $this->answer * $quotient);

            default:
                throw new Exception('Unknown tolerance type ' . $this->tolerancetype);
        }
    }

    public function within_tolerance($value) {
        if ($this->answer === '*') {
            return true;
        }
        list($min, $max) = $this->get_tolerance_interval();
        return $min < $value && $value < $max;
    }
}

class qtype_numerical_answer_processor {
    /** @var array unit name => multiplier. */
    protected $units;
    /** @var string character used as decimal point. */
    protected $decsep;
    /** @var string character used as thousands separator. */
    protected $thousandssep;

    protected $regex = null;

    public function __construct($units, $decsep = null, $thousandssep = null) {
        if (is_null($decsep)) {
            $decsep = get_string('decsep', 'langconfig');
        }
        $this->decsep = $decsep;

        if (is_null($thousandssep)) {
            $thousandssep = get_string('thousandssep', 'langconfig');
        }
        $this->thousandssep = $thousandssep;

        $this->units = $units;
    }

    protected function build_regex() {
        if (!is_null($this->regex)) {
            return $this->regex;
        }

        $beforepointre = '([+-]?[' . preg_quote($this->thousandssep, '/') . '\d]*)';
        $decimalsre = preg_quote($this->decsep, '/') . '(\d*)';
        $exponentre = '(?:e|E|(?:x|\*|×)10(?:\^|\*\*))([+-]?\d+)';

        $escapedunits = array();
        foreach ($this->units as $unit => $notused) {
            $escapedunits[] = preg_quote($unit, '/');
        }
        $unitre = '(' . implode('|', $escapedunits) . ')';

        $this->regex = "/^$beforepointre(?:$decimalsre)?(?:$exponentre)?\s*(?:$unitre)?$/U";
        return $this->regex;
    }

    /**
     * 
     * @param string $value a value, optionally with a unit.
     * @return array(numeric, sting) the value with the unit stripped, and converted to the default unit.
     */
    public function parse_response($response) {
        if (!preg_match($this->build_regex(), $response, $matches)) {
            return array(null, null, null, null);
        }

        $matches += array('', '', '', '', ''); // Fill in any missing matches.
        list($notused, $beforepoint, $decimals, $exponent, $unit) = $matches;

        // Strip out thousands separators.
        $beforepoint = str_replace($this->thousandssep, '', $beforepoint);

        // Must be either something before, or something after the decimal point.
        // (The only way to do this in the regex would make it much more complicated.)
        if ($beforepoint === '' && $decimals === '') {
            return array(null, null, null, null);
        }

        return array($beforepoint, $decimals, $exponent, $unit);
    }

    /**
     * 
     * @param string $value a value, optionally with a unit.
     * @return array(numeric, sting) the value with the unit stripped, and converted to the default unit.
     */
    public function apply_units($response) {
        list($beforepoint, $decimals, $exponent, $unit) = $this->parse_response($response);

        if (is_null($beforepoint)) {
            return array(null, null);
        }

        $numberstring = $beforepoint . '.' . $decimals;
        if ($exponent) {
            $numberstring .= 'e' . $exponent;
        }

        if ($unit) {
            $value = $numberstring * $this->units[$unit];
        } else {
            $value = $numberstring * 1;
        }

        return array($value, $unit);
    }
}