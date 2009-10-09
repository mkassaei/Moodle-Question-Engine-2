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
 * This file contains helper classes for testing the question engine.
 *
 * @package question-engine
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../lib.php');


/**
 * Makes some protected methods of question_attempt public to facilitate testing.
 *
 * @copyright © 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_question_attempt extends question_attempt {
    public function add_step($step) {#
        parent::add_step($step);
    }
}


/**
 * This class creates questions of various types, which can then be used when
 * testing.
 *
 * @copyright © 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_question_maker {
    /**
     * Initialise the common fields of a question of any type..
     */
    private static function initialise_a_question($q) {
        global $USER;

        $q->id = 0;
        $q->category = 0;
        $q->parent = 0;
        $q->questiontextformat = FORMAT_HTML;
        $q->defaultgrade = 1;
        $q->penalty = 0.1;
        $q->length = 1;
        $q->stamp = make_unique_id_code();
        $q->version = make_unique_id_code();
        $q->hidden = 0;
        $q->timecreated = time();
        $q->timemodified = time();
        $q->createdby = $USER->id;
        $q->modifiedby = $USER->id;
    }

    /**
     * Makes a truefalse question with correct ansewer true, defaultgrade 1.
     * @return question_truefalse
     */
    public static function make_a_truefalse_question() {
        $tf = new question_truefalse();
        self::initialise_a_question($tf);
        $tf->name = 'True/false question';
        $tf->questiontext = 'The answer is true.';
        $tf->generalfeedback = 'You should have selected true.';
        $tf->penalty = 1;
        $tf->qtype = question_engine::get_qtype('truefalse');

        $tf->rightanswer = true;
        $tf->truefeedback = 'This is the right answer.';
        $tf->falsefeedback = 'This is the wrong answer.';

        return $tf;
    }


    /**
     * Makes a truefalse question with correct ansewer true, defaultgrade 1.
     * @return question_truefalse
     */
    public static function make_an_essay_question() {
        $essay = new question_essay();
        self::initialise_a_question($essay);
        $essay->name = 'Essay question';
        $essay->questiontext = 'Write an essay.';
        $essay->generalfeedback = 'I hope you wrote an interesting essay.';
        $essay->penalty = 0;
        $essay->qtype = question_engine::get_qtype('essay');

        return $essay;
    }
}