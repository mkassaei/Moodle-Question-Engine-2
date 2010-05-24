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
 * OU multiple response question definition class.
 *
 * @package qtype_oumultiresponse
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/question/type/multichoice/question.php');


/**
 * Represents an OU multiple response question.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_oumultiresponse_question extends qtype_multichoice_multi_question {

    protected function get_num_correct_choices() {
        $numcorrect = 0;
        foreach ($this->answers as $ans) {
            if (!question_state::graded_state_for_fraction(
                    $ans->fraction)->is_incorrect()) {
                $numcorrect += 1;
            }
        }
        return $numcorrect;
    }

    public function grade_response(array $response) {
        list($right, $total) = $this->get_num_parts_right($response);
        $fraction = $right / $this->get_num_correct_choices();
        // TODO credit for earlier tries in interactive mode.
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }
}
