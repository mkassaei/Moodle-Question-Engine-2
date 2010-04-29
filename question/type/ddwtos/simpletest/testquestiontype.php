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
 * Unit tests for the drag-and-drop words into sentences question definition class.
 *
 * @package qtype_ddwtos
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');
require_once($CFG->dirroot . '/question/type/ddwtos/simpletest/helper.php');


/**
 * Unit tests for the drag-and-drop words into sentences question definition class.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddwtos_test extends UnitTestCase {
    /** @var qtype_ddwtos instance of the question type class to test. */
    protected $qtype;

    public function setUp() {
        $this->qtype = question_bank::get_qtype('ddwtos');;
    }

    public function tearDown() {
        $this->qtype = null;
    }

    /**
     * @return object the data to construct a question like
     * {@link qtype_ddwtos_test_helper::make_a_ddwtos_question()}.
     */
    protected function get_test_question_data() {
        global $USER;

        $dd = new stdClass;
        $dd->id = 0;
        $dd->category = 0;
        $dd->parent = 0;
        $dd->questiontextformat = FORMAT_HTML;
        $dd->defaultgrade = 1;
        $dd->penalty = 0.3333333;
        $dd->length = 1;
        $dd->stamp = make_unique_id_code();
        $dd->version = make_unique_id_code();
        $dd->hidden = 0;
        $dd->timecreated = time();
        $dd->timemodified = time();
        $dd->createdby = $USER->id;
        $dd->modifiedby = $USER->id;

        $dd->name = 'Drag-and-drop words into sentences question';
        $dd->questiontext = 'The [[1]] brown [[2]] jumped over the [[3]] dog.';
        $dd->generalfeedback = 'This sentence uses each letter of the alphabet.';
        $dd->qtype = 'ddwtos';

        $dd->options->shuffleanswers = true;

        test_question_maker::set_standard_overall_feedback_fields($dd->options);

        $dd->options->answers = array(
            (object) array('answer' => 'quick', 'feedback' => 'O:8:"stdClass":2:{s:9:"draggroup";s:1:"1";s:8:"infinite";i:0;}'),
            (object) array('answer' => 'fox', 'feedback' => 'O:8:"stdClass":2:{s:9:"draggroup";s:1:"2";s:8:"infinite";i:0;}'),
            (object) array('answer' => 'lazy', 'feedback' => 'O:8:"stdClass":2:{s:9:"draggroup";s:1:"3";s:8:"infinite";i:0;}'),
            (object) array('answer' => 'assiduous', 'feedback' => 'O:8:"stdClass":2:{s:9:"draggroup";s:1:"3";s:8:"infinite";i:0;}'),
            (object) array('answer' => 'dog', 'feedback' => 'O:8:"stdClass":2:{s:9:"draggroup";s:1:"2";s:8:"infinite";i:0;}'),
            (object) array('answer' => 'slow', 'feedback' => 'O:8:"stdClass":2:{s:9:"draggroup";s:1:"1";s:8:"infinite";i:0;}'),
        );

        return $dd;
    }

    public function test_name() {
        $this->assertEqual($this->qtype->name(), 'ddwtos');
    }

    public function test_can_analyse_responses() {
        $this->assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_initialise_question_instance() {
        $qdata = $this->get_test_question_data();

        $expected = qtype_ddwtos_test_helper::make_a_ddwtos_question();
        $expected->stamp = $qdata->stamp;
        $expected->version = $qdata->version;

        $q = $this->qtype->make_question($qdata);

        $this->assertEqual($expected, $q);
    }

    public function test_get_random_guess_score() {
        $q = $this->get_test_question_data();
        $this->assertWithinMargin(0.5, $this->qtype->get_random_guess_score($q), 0.0000001);
    }

    public function x_test_get_possible_responses() { // TODO
        $q = $this->get_test_question_data();
        $responses = $this->qtype->get_possible_responses($q);

        $this->assertEqual(3, count($responses));

        $response = array_shift($responses);
        $this->assertEqual(2, count($response));

        $this->assertEqual(1, $response[0]->fraction);
        $this->assertEqual('frog: amphibian', $response[0]->responseclass);

        $this->assertEqual(0, $response[1]->fraction);
        $this->assertEqual('frog: mammal', $response[1]->responseclass);

        $response = array_shift($responses);
        $this->assertEqual(2, count($response));

        $this->assertEqual(1, $response[1]->fraction);
        $this->assertEqual('cat: mammal', $response[1]->responseclass);

        $this->assertEqual(0, $response[2]->fraction);
        $this->assertEqual('cat: insect', $response[2]->responseclass);

        $response = array_shift($responses);
        $this->assertEqual(2, count($response));

        $this->assertEqual(1, $response[1]->fraction);
        $this->assertEqual('cat: mammal', $response[1]->responseclass);

        $this->assertEqual(0, $response[2]->fraction);
        $this->assertEqual('cat: insect', $response[2]->responseclass);
    }
}
