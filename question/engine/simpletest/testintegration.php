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
 * This file contains integration tests for the Moodle question engine.
 *
 * The tests her test the system working as a whole, rather than individual units.
 *
 * @package question-engine
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../lib.php');

class question_engine_integration_test extends UnitTestCase {

    public function setUp() {

    }

    public function tearDown() {

    }

    public function make_a_truefalse_question() {
        global $USER;

        $tf = new stdClass();
        $tf->id = 0;
        $tf->category = 0;
        $tf->parent = 0;
        $tf->name = 'True/false question';
        $tf->questiontext = 'The answer is true.';
        $tf->questiontextformat = FORMAT_HTML;
        $tf->image = '';
        $tf->generalfeedback = 'You should have selected true.';
        $tf->defaultgrade = 1;
        $tf->penalty = 1;
        $tf->qtype = 'truefalse';
        $tf->length = 1;
        $tf->stamp = make_unique_id_code();
        $tf->version = make_unique_id_code();
        $tf->hidden = 0;
        $tf->timecreated = time();
        $tf->timemodified = time();
        $tf->createdby = $USER->id;
        $tf->modifiedby = $USER->id;

        $trueanswer = new stdClass();
        $trueanswer->id = 1;
        $trueanswer->question = 0;
        $trueanswer->answer = get_string('true', 'qtype_truefalse');
        $trueanswer->fraction = 1;
        $trueanswer->feedback = 'This is the right answer.';

        $falseanswer = new stdClass();
        $falseanswer->id = 2;
        $falseanswer->question = 0;
        $falseanswer->answer = get_string('false', 'qtype_truefalse');
        $falseanswer->fraction = 0;
        $falseanswer->feedback = 'This is the wrong answer.';

        $tf->options->id = 0;
        $tf->options->question = 0;
        $tf->options->trueanswer = 1;
        $tf->options->falseanswer = 2;
        $tf->options->answers = array(
                1 => $trueanswer,
                2 => $falseanswer,
        );

        return $tf;
    }

    public function test_delayed_feedback_truefalse() {
        // Create a true-false question with correct answer true.
        $tf = $this->make_a_truefalse_question();
        $displayoptions = new question_display_options();

        // Start a delayed feedback attempt and add the question to it.
        $quba = question_engine::make_questions_usage_by_activity('unit_test');
        $quba->set_preferred_interaction_model('delayedfeedback');
        $qnumber = $quba->add_question($tf);
        // Different from $tf->id since the same question may be used twice in
        // the same attempt.

        // Verify.
        $this->assertEqual($qnumber, 1);
        $this->assertEqual($quba->question_count(), 1);
        $this->assertEqual($quba->get_question_state($qnumber), question_state::NOT_STARTED);

        // Begin the attempt. Creates an initial state for each question.
        $quba->start_all_questions();

        // Output the question in the initial state.
        //$html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::INCOMPLETE);
        $this->assertNull($quba->get_question_grade($qnumber));
        //$this->assertPattern('/' . preg_quote($tf->questiontext) . '/', $html);

        // Simulate some data submitted by the student.
        $prefix = $quba->get_field_prefix($qnumber);
        $answername = $prefix . 'true';
        $getdata = array(
            $answername => 1,
            'irrelevant' => 'should be ignored',
        );
        $submitteddata = $quba->extract_responses($qnumber, $getdata);

        // Verify.
        $this->assertEqual(array('true' => 1), $submitteddata);

        // Process the data extracted for this question.
        $quba->process_action($qnumber, $submitteddata);
        //$html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::COMPLETE);
        $this->assertNull($quba->get_question_grade($qnumber));
        //$this->assert(new ContainsTagWithAttributes('input',
        //        array('name' => $answername, 'value' => 1)), $html);
        //$this->assertNoPattern('/class=\"correctness/', $html);

        // Finish the attempt.
        $quba->finish_all_questions();
        //$html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::GRADED_CORRECT);
        $this->assertEqual($quba->get_question_grade($qnumber), 1);
        //$this->assertPattern(
        //        '/' . preg_quote(get_string('correct', 'question')) . '/',
        //        $html);

        // Process a manual comment.
        $quba->manual_grade($qnumber, 0.5, 'Not good enough!');
        //$html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::MANUALLY_GRADED_PARTCORRECT);
        $this->assertEqual($quba->get_question_grade($qnumber), 0.5);
        //$this->assertPattern('/' . preg_quote('Not good enough!') . '/', $html);
    }

}