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

        $mc->shuffleanswers = true;
        $mc->answernumbering = '123';

        test_question_maker::set_standard_combined_feedback_fields($mc);

        $mc->answers = array(
            13 => new question_answer('One', 1, 'One is odd.'),
            14 => new question_answer('Two', 0, 'Two is even.'),
            15 => new question_answer('Three', 1, 'Three is odd.'),
            16 => new question_answer('Four', 0, 'Four is even.'),
        );

        $mc->hints = array(
            new qtype_oumultiresponse_hint('Hint 1.', true, false, false),
            new qtype_oumultiresponse_hint('Hint 2.', true, true, true),
        );

        return $mc;
    }

    /**
     * Get the question data, as it would be loaded by get_question_options, for
     * the question returned by {@link make_an_oumultiresponse_two_of_four()}.
     * @return stdClass
     */
    public static function get_question_data() {
        global $USER;

        $qdata = new stdClass;
        $qdata->id = 0;
        $qdata->category = 0;
        $qdata->parent = 0;
        $qdata->stamp = make_unique_id_code();
        $qdata->version = make_unique_id_code();
        $qdata->timecreated = time();
        $qdata->timemodified = time();
        $qdata->createdby = $USER->id;
        $qdata->modifiedby = $USER->id;
        $qdata->qtype = 'oumultiresponse';
        $qdata->name = 'OU multiple response question';
        $qdata->questiontext = 'Which are the odd numbers?';
        $qdata->questiontextformat = FORMAT_HTML;
        $qdata->generalfeedback = 'The odd numbers are One and Three.';
        $qdata->defaultmark = 1;
        $qdata->length = 1;
        $qdata->penalty = 0.3333333;
        $qdata->hidden = 0;

        $qdata->options->shuffleanswers = 1;
        $qdata->options->answernumbering = '123';
        $qdata->options->correctfeedback = test_question_maker::STANDARD_OVERALL_CORRECT_FEEDBACK;
        $qdata->options->partiallycorrectfeedback = test_question_maker::STANDARD_OVERALL_PARTIALLYCORRECT_FEEDBACK;
        $qdata->options->shownumcorrect = 1;
        $qdata->options->incorrectfeedback = test_question_maker::STANDARD_OVERALL_INCORRECT_FEEDBACK;

        $qdata->options->answers = array(
            13 => (object) array(
                'id' => 13,
                'answer' => 'One',
                'fraction' => 1,
                'feedback' => 'One is odd.'
            ),
            14 => (object) array(
                'id' => 14,
                'answer' => 'Two',
                'fraction' => 0,
                'feedback' => 'Two is even.'
            ),
            15 => (object) array(
                'id' => 15,
                'answer' => 'Three',
                'fraction' => 1,
                'feedback' => 'Three is odd.'
            ),
            16 => (object) array(
                'id' => 16,
                'answer' => 'Four',
                'fraction' => 0,
                'feedback' => 'Four is even.'
            ),
        );

        $qdata->hints = array(
            (object) array(
                'hint' => 'Hint 1.',
                'shownumcorrect' => 1,
                'clearwrong' => 0,
                'options' => 0,
            ),
            (object) array(
                'hint' => 'Hint 2.',
                'shownumcorrect' => 1,
                'clearwrong' => 1,
                'options' => 1,
            ),
        );

        return $qdata;
    }
}
