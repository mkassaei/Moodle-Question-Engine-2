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
 * Unit tests for the matching question definition class.
 *
 * @package qtype_match
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/type/match/questiontype.php');

/**
 * Unit tests for the matching question definition class.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_match_test extends UnitTestCase {
    /** @var qtype_match instance of the question type class to test. */
    protected $qtype;

    public function setUp() {
        $this->qtype = new qtype_match();
    }

    public function tearDown() {
        $this->qtype = null;
    }

    protected function get_test_question_data() {
        global $USER;
        $q = new stdClass;
        $q->id = 0;
        $q->name = 'Matching question';
        $q->category = 0;
        $q->parent = 0;
        $q->questiontext = 'Classify the animals.';
        $q->questiontextformat = FORMAT_HTML;
        $q->generalfeedback = 'General feedback.';
        $q->defaultgrade = 1;
        $q->penalty = 0.3333333;
        $q->length = 1;
        $q->stamp = make_unique_id_code();
        $q->version = make_unique_id_code();
        $q->hidden = 0;
        $q->timecreated = time();
        $q->timemodified = time();
        $q->createdby = $USER->id;
        $q->modifiedby = $USER->id;

        $q->options->shuffleanswers = false;
        test_question_maker::set_standard_overall_feedback_fields($q->options);

        $q->options->subquestions = array(
            14 => (object) array('id' => 14, 'questiontext' => 'frog', 'answertext' => 'amphibian'),
            15 => (object) array('id' => 15, 'questiontext' => 'cat', 'answertext' => 'mammal'),
            16 => (object) array('id' => 16, 'questiontext' => 'newt', 'answertext' => 'amphibian'),
            17 => (object) array('id' => 17, 'questiontext' => '', 'answertext' => 'insect'),
        );

        return $q;
    }

    public function test_name() {
        $this->assertEqual($this->qtype->name(), 'match');
    }

    public function test_can_analyse_responses() {
        $this->assertTrue($this->qtype->can_analyse_responses());
    }

    public function test_get_random_guess_score() {
        $q = $this->get_test_question_data();
        $this->assertWithinMargin(0.3333333, $this->qtype->get_random_guess_score($q), 0.0000001);
    }

    public function test_get_possible_responses() {
        $q = $this->get_test_question_data();
        $responses = $this->qtype->get_possible_responses($q);

        $this->assertEqual(3, count($responses));

        $response = $responses[14];
        $this->assertEqual(3, count($response));

        $this->assertEqual(1, $response[14]->fraction);
        $this->assertEqual('frog: amphibian', $response[14]->responseclass);

        $this->assertEqual(0, $response[15]->fraction);
        $this->assertEqual('frog: mammal', $response[15]->responseclass);

        $this->assertEqual(0, $response[17]->fraction);
        $this->assertEqual('frog: insect', $response[17]->responseclass);

        $response = $responses[15];
        $this->assertEqual(3, count($response));

        $this->assertEqual(0, $response[14]->fraction);
        $this->assertEqual('cat: amphibian', $response[14]->responseclass);

        $this->assertEqual(1, $response[15]->fraction);
        $this->assertEqual('cat: mammal', $response[15]->responseclass);

        $this->assertEqual(0, $response[17]->fraction);
        $this->assertEqual('cat: insect', $response[17]->responseclass);

        $response = $responses[16];
        $this->assertEqual(3, count($response));

        $this->assertEqual(1, $response[14]->fraction);
        $this->assertEqual('newt: amphibian', $response[14]->responseclass);

        $this->assertEqual(0, $response[15]->fraction);
        $this->assertEqual('newt: mammal', $response[15]->responseclass);

        $this->assertEqual(0, $response[17]->fraction);
        $this->assertEqual('newt: insect', $response[17]->responseclass);
    }
}
