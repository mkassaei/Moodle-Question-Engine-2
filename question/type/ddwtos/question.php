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
 * Drag-and-drop words into sentences question definition class.
 *
 * @package qtype_ddwtos
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Represents a drag-and-drop words into sentences question.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddwtos_question extends question_graded_automatically_with_countback {
    /** @var boolean Whether the question stems should be shuffled. */
    public $shufflechoices;

    public $correctfeedback;
    public $partiallycorrectfeedback;
    public $incorrectfeedback;

    /** @var array of arrays. The keys are the choice group numbers. The values
     * are arrays of qtype_ddwtos_choice objects. */
    public $choices;

    /**
     * @var array place number => group number of the places in the question
     * text where choices can be put. Places are numbered from 1.
     */
    public $places;

    /**
     * @var array of strings, one longer than $places, which is achieved by
     * indexing from 0. The bits of question text that go between the placeholders.
     */
    public $textfragments;

    /** @var array index of the right choice for each stem. */
    public $rightchoices;

    /** @var array shuffled choice indexes. */
    protected $choiceorder;

    public function init_first_step(question_attempt_step $step) {
        foreach ($this->choices as $group => $choices) {
            $varname = '_choiceorder' . $group;

            if ($step->has_qt_var($varname)) {
                $choiceorder = explode(',', $step->get_qt_var($varname));

            } else {
                $choiceorder = array_keys($choices);
                if ($this->shufflechoices) {
                    shuffle($choiceorder);
                }
            }

            foreach ($choiceorder as $key => $value) {
                $this->choiceorder[$group][$key + 1] = $value;
            }

            if (!$step->has_qt_var($varname)) {
                $step->set_qt_var($varname, implode(',', $this->choiceorder[$group]));
            }
        }
    }

    public function get_question_summary() {
        $question = html_to_text($this->format_questiontext(), 0, false);
        $groups = array();
        foreach ($this->choices as $group => $choices) {
            $cs = array();
            foreach ($choices as $choice) {
                $cs[] = html_to_text($this->format_text($choice->text), 0, false);
            }
            $groups[] = '[[' . $group . ']] -> {' . implode(' / ', $cs) . '}';
        }
        return $question . '; ' . implode('; ', $groups);
    }

    protected function get_selected_choice($group, $shuffledchoicenumber) {
        $choiceno = $this->choiceorder[$group][$shuffledchoicenumber];
        return $this->choices[$group][$choiceno];
    }

    public function summarise_response(array $response) {
        $matches = array();
        $allblank = true;
        foreach ($this->places as $place => $group) {
            if (array_key_exists($this->field($place), $response) &&
                    $response[$this->field($place)]) {
                $choices[] = '{' . html_to_text($this->format_text($this->get_selected_choice(
                        $group, $response[$this->field($place)])->text), 0, false) . '}';
                $allblank = false;
            } else {
                $choices[] = '{}';
            }
        }
        if ($allblank) {
            return null;
        }
        return implode(' ', $choices);
    }

    public function get_random_guess_score() {
        $accum = 0;

        foreach ($this->places as $placegroup) {
            $accum += 1 / count($this->choices[$placegroup]);
        }

        return $accum / count($this->places);
    }

    public function clear_wrong_from_response(array $response) {
        foreach ($this->places as $place => $notused) {
            if (array_key_exists($this->field($place), $response) &&
                    $response[$this->field($place)] != $this->get_right_choice_for($place)) {
                $response[$this->field($place)] = '0';
            }
        }
        return $response;
    }

    public function get_num_parts_right(array $response) {
        $numright = 0;
        foreach ($this->places as $place => $notused) {
            if (!array_key_exists($this->field($place), $response)) {
                continue;
            }
            if ($response[$this->field($place)] == $this->get_right_choice_for($place)) {
                $numright += 1;
            }
        }
        return array($numright, count($this->places));
    }

    /**
     * @param integer $key stem number
     * @return string the question-type variable name.
     */
    public function field($place) {
        return 'p' . $place;
    }

    public function get_expected_data() {
        $vars = array();
        foreach ($this->places as $place => $notused) {
            $vars[$this->field($place)] = PARAM_INTEGER;
        }
        return $vars;
    }

    public function get_correct_response() {
        $response = array();
        foreach ($this->places as $place => $notused) {
            $response[$this->field($place)] = $this->get_right_choice_for($place);
        }
        return $response;
    }

    public function get_right_choice_for($place) {
        $group = $this->places[$place];
        foreach ($this->choiceorder[$group] as $choicekey => $choiceid) {
            if ($this->rightchoices[$place] == $choiceid) {
                return $choicekey;
            }
        }
    }

    public function get_ordered_choices($group) {
        $choices = array();
        foreach ($this->choiceorder[$group] as $choicekey => $choiceid) {
            $choices[$choicekey] = $this->choices[$group][$choiceid];
        }
        return $choices;
    }

    public function is_complete_response(array $response) {
        $complete = true;
        foreach ($this->places as $place => $notused) {
            $complete = $complete && !empty($response[$this->field($place)]);
        }
        return $complete;
    }

    public function is_gradable_response(array $response) {
        foreach ($this->places as $place => $notused) {
            if (!empty($response[$this->field($place)])) {
                return true;
            }
        }
        return false;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        foreach ($this->places as $place => $notused) {
            $fieldname = $this->field($place);
            if (!question_utils::arrays_same_at_key_integer(
                    $prevresponse, $newresponse, $fieldname)) {
                return false;
            }
        }
        return true;
    }

    public function get_validation_error(array $response) {
        if ($this->is_complete_response($response)) {
            return '';
        }
        return get_string('pleaseputananswerineachbox', 'qtype_ddwtos');
    }

    public function grade_response(array $response) {
        list($right, $total) = $this->get_num_parts_right($response);
        $fraction = $right / $total;
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    public function compute_final_grade($responses, $totaltries) {
        $totalscore = 0;
        foreach ($this->places as $place => $notused) {
            $fieldname = $this->field($place);

            $lastwrongindex = -1;
            $finallyright = false;
            foreach ($responses as $i => $response) {
                if (!array_key_exists($fieldname, $response) ||
                        $response[$fieldname] != $this->get_right_choice_for($place)) {
                    $lastwrongindex = $i;
                    $finallyright = false;
                } else {
                    $finallyright = true;
                }
            }

            if ($finallyright) {
                $totalscore += max(0, 1 - ($lastwrongindex + 1) * $this->penalty);
            }
        }

        return $totalscore / count($this->places);
    }

    public function classify_response(array $response) {
        $parts = array();
        foreach ($this->places as $place => $group) {
            if (!array_key_exists($this->field($place), $response) ||
                    !$response[$this->field($place)]) {
                $parts[$place] = question_classified_response::no_response();
                continue;
            }

            $fieldname = $this->field($place);
            $choiceno = $this->choiceorder[$group][$response[$fieldname]];
            $choice = $this->choices[$group][$choiceno];
            $parts[$place] = new question_classified_response(
                    $choiceno, html_to_text($this->format_text($choice->text), 0, false),
                    $this->get_right_choice_for($place) == $response[$fieldname]);
        }
        return $parts;
    }
}


/**
 * Represents one of the choices (draggable boxes).
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddwtos_choice {
    public $text;
    public $draggroup;
    public $isinfinite;

    public function __construct($text, $draggroup = 1, $isinfinite = false) {
        $this->text = $text;
        $this->draggroup = $draggroup;
        $this->isinfinite = $isinfinite;
    }
}
