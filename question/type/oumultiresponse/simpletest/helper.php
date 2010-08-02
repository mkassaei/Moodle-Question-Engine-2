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
 * Test helper class for the OU multiple response question type.
 *
 * @package qtype_oumultiresponse
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_oumultiresponse_test_helper {
    /**
     * @return qtype_oumultiresponse_question
     */
    public static function make_an_oumultiresponse_two_of_four() {
        question_bank::load_question_definition_classes('oumultiresponse');
        $mc = new qtype_oumultiresponse_question();

        test_question_maker::initialise_a_question($mc);

        $mc->name = 'OU multiple response question';
        $mc->questiontext = 'Which are the odd numbers?';
        $mc->generalfeedback = 'The odd numbers are One and Three.';
        $mc->qtype = question_bank::get_qtype('oumultiresponse');

        $mc->shufflechoices = true;
        $mc->answernumbering = 'abc';

        test_question_maker::set_standard_combined_feedback_fields($mc);

        $mc->answers = array(
            13 => new question_answer('One', 1, 'One is odd.'),
            14 => new question_answer('Two', 0, 'Two is even.'),
            15 => new question_answer('Three', 1, 'Three is odd.'),
            16 => new question_answer('Four', 0, 'Four is even.'),
        );

        $mc->hints = array(
            new qtype_oumultiresponse_hint('Hint 1', true, false, false),
            new qtype_oumultiresponse_hint('Hint 1', true, true, true),
        );

        return $mc;
    }
}
