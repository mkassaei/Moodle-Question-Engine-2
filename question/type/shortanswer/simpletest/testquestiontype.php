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
 * Unit tests for the shortanswer question definition class.
 *
 * @package qtype_shortanswer
 * @copyright &copy; 2007 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/type/shortanswer/questiontype.php');

/**
 * Unit tests for the shortanswer question definition class.
 *
 * @copyright &copy; 2007 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_shortanswer_test extends UnitTestCase {
    var $qtype;

    public function setUp() {
        $this->qtype = new qtype_shortanswer();
    }

    public function tearDown() {
        $this->qtype = null;   
    }

    public function test_name() {
        $this->assertEqual($this->qtype->name(), 'shortanswer');
    }

/*
    public function test_check_response() {
        $answer1 = new stdClass;
        $answer1->id = 17;
        $answer1->answer = "celine";
        $answer1->fraction = 1;
        $answer2 = new stdClass;
        $answer2->id = 23;
        $answer2->answer = "c*line";
        $answer2->fraction = 0.8;
        $answer3 = new stdClass;
        $answer3->id = 23;
        $answer3->answer = "*line";
        $answer3->fraction = 0.7;
        $answer4 = new stdClass;
        $answer4->id = 29;
        $answer4->answer = "12\*13";
        $answer4->fraction = 0.5;

        $question = new stdClass;
        $question->options->answers = array(
            17 => $answer1,
            23 => $answer2,
            29 => $answer3,
            31 => $answer4
        );
        $question->options->usecase = true;

        $state = new stdClass;

        $state->responses = array('' => 'celine');
        $this->assertEqual($this->qtype->check_response($question, $state), 17);

        $state->responses = array('' => 'caline');
        $this->assertEqual($this->qtype->check_response($question, $state), 23);

        $state->responses = array('' => 'aline');
        $this->assertEqual($this->qtype->check_response($question, $state), 29);

        $state->responses = array('' => 'frog');
        $this->assertFalse($this->qtype->check_response($question, $state));

        $state->responses = array('' => '12*13');
        $this->assertEqual($this->qtype->check_response($question, $state), 31);

        $question->options->usecase = false;

        $answer1->answer = "Fred's";
        $question->options->answers[17] = $answer1;

        $state->responses = array('' => 'frog');
        $this->assertFalse($this->qtype->check_response($question, $state));

        $state->responses = array('' => "fred\'s");
        $this->assertEqual($this->qtype->check_response($question, $state), 17);

        $state->responses = array('' => '12*13');
        $this->assertEqual($this->qtype->check_response($question, $state), 31);

        $state->responses = array('' => 'caLINe');
        $this->assertEqual($this->qtype->check_response($question, $state), 23);

        $state->responses = array('' => 'ALIne');
        $this->assertEqual($this->qtype->check_response($question, $state), 29);
    }

    public function test_compare_responses() {
        $question = new stdClass;
        $question->options->usecase = false;

        $state = new stdClass;
        $teststate = new stdClass;
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => '');
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));

        $state = new stdClass;
        $teststate->responses = array('' => '');
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => '');
        $this->assertTrue($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frog');
        $teststate->responses = array('' => 'frog');
        $this->assertTrue($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frog');
        $teststate->responses = array('' => 'Frog');
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => "\'");
        $teststate->responses = array('' => "\'");
        $this->assertTrue($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frog*toad');
        $teststate->responses = array('' => 'frog*TOAD');
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frog*');
        $teststate->responses = array('' => 'frogs');
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frogs');
        $teststate->responses = array('' => 'frog*');
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));

        $question->options->usecase = true;

        $state->responses = array('' => '');
        $teststate->responses = array('' => '');
        $this->assertTrue($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frog');
        $teststate->responses = array('' => 'frog');
        $this->assertTrue($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frog');
        $teststate->responses = array('' => 'Frog');
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => "\'");
        $teststate->responses = array('' => "\'");
        $this->assertTrue($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frog*toad');
        $teststate->responses = array('' => 'frog*toad');
        $this->assertTrue($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frog*');
        $teststate->responses = array('' => 'frogs');
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));

        $state->responses = array('' => 'frogs');
        $teststate->responses = array('' => 'frog*');
        $this->assertFalse($this->qtype->compare_responses($question, $state, $teststate));
    }

    public function test_get_correct_responses() {
        $answer1 = new stdClass;
        $answer1->id = 17;
        $answer1->answer = "frog";
        $answer1->fraction = 1;
        $answer2 = new stdClass;
        $answer2->id = 23;
        $answer2->answer = "f*g";
        $answer2->fraction = 1;
        $answer3 = new stdClass;
        $answer3->id = 29;
        $answer3->answer = "12\*13";
        $answer3->fraction = 1;
        $answer4 = new stdClass;
        $answer4->id = 31;
        $answer4->answer = "*";
        $answer4->fraction = 0;
        $question = new stdClass;
        $question->options->answers = array(
            17 => $answer1,
            23 => $answer2,
            29 => $answer3,
            31 => $answer4
        );
        $state = new stdClass;
        $this->assertEqual($this->qtype->get_correct_responses($question, $state), array('' => 'frog'));
        $question->options->answers[17]->fraction = 0;
        $this->assertEqual($this->qtype->get_correct_responses($question, $state), array('' => 'f*g'));
        $question->options->answers[23]->fraction = 0;
        $this->assertEqual($this->qtype->get_correct_responses($question, $state), array('' => '12*13'));
        $question->options->answers[29]->fraction = 0;
        $this->assertNull($this->qtype->get_correct_responses($question, $state));
    }
*/
}
