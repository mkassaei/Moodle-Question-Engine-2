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
 * This file contains tests that walks a question through the deferred feedback
 * interaction model.
 *
 * @package qim_adaptive
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once(dirname(__FILE__) . '/../../../engine/simpletest/helpers.php');

class qim_adaptive_walkthrough_test extends UnitTestCase {
    public function test_adaptive_multichoice() {

        // Create a true-false question with correct answer true.
        $mc = test_question_maker::make_a_multichoice_single_question();
        $displayoptions = new question_display_options();

        // Start a delayed feedback attempt and add the question to it.
        $mc->maxmark = 3;
        $mc->penalty = 0.3333333;
        $quba = question_engine::make_questions_usage_by_activity('unit_test');
        $quba->set_preferred_interaction_model('adaptive');
        $qnumber = $quba->add_question($mc);
        // Different from $mc->id since the same question may be used twice in
        // the same attempt.
        $prefix = $quba->get_field_prefix($qnumber);
        $answername = $prefix . 'answer';
        $submitname = $prefix . '!submit';

        // Verify.
        $this->assertEqual($qnumber, 1);
        $this->assertEqual($quba->question_count(), 1);
        $this->assertEqual($quba->get_question_state($qnumber), question_state::NOT_STARTED);

        // Begin the attempt. Creates an initial state for each question.
        $quba->start_all_questions();
        $order = $mc->get_order($quba->get_question_attempt($qnumber));
        foreach ($order as $i => $ansid) {
            if ($mc->answers[$ansid]->fraction == 1) {
                $rightindex = $i;
                break;
            }
        }
        $wrongindex = ($rightindex + 1) % 3;

        // Output the question in the initial state.
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::INCOMPLETE);
        $this->assertNull($quba->get_question_mark($qnumber));
        $this->assertPattern('/' . preg_quote($mc->questiontext) . '/', $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => 2),
                array('disabled' => 'disabled')), $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => 0),
                array('disabled' => 'disabled')), $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'submit', 'name' => $submitname),
                array('disabled' => 'disabled')), $html);

        // Simulate some data submitted by the student.
        $getdata = array(
            $answername => $wrongindex,
            $submitname => 'Submit',
            'irrelevant' => 'should be ignored',
        );
        $submitteddata = $quba->extract_responses($qnumber, $getdata);

        // Verify.
        $this->assertEqual(array('answer' => $wrongindex, '!submit' => 1), $submitteddata);

        // Process the data extracted for this question.
        $quba->process_action($qnumber, $submitteddata);
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::INCOMPLETE);
        $this->assertNotNull($quba->get_question_mark($qnumber));
        $this->assertEqual($quba->get_question_mark($qnumber), 0);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => $wrongindex, 'checked' => 'checked'),
                array('disabled' => 'disabled')), $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => ($wrongindex + 1) % 3),
                array('disabled' => 'disabled', 'checked' => 'checked')), $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => ($wrongindex + 2) % 3),
                array('disabled' => 'disabled', 'checked' => 'checked')), $html);
        $this->assertPattern('/class=\"correctness/', $html);
        $this->assertPattern('/' . preg_quote(get_string('incorrect', 'question')) . '/', $html);

        // Process the data extracted for this question.
        $quba->process_action($qnumber, array('answer' => $rightindex));
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::INCOMPLETE);
        $this->assertEqual($quba->get_question_mark($qnumber), 0);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => $rightindex, 'checked' => 'checked'),
                array('disabled' => 'disabled')), $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => ($rightindex + 1) % 3),
                array('disabled' => 'disabled', 'checked' => 'checked')), $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => ($rightindex + 2) % 3),
                array('disabled' => 'disabled', 'checked' => 'checked')), $html);
        $this->assertPattern('/class=\"correctness/', $html);
        $this->assertPattern('/' . preg_quote(get_string('incorrect', 'question')) . '/', $html);

        // Process the data extracted for this question.
        $quba->process_action($qnumber, array('answer' => $rightindex, '!submit' => 1));
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::COMPLETE);
        $this->assertEqual($quba->get_question_mark($qnumber), 3 * (1 - $mc->penalty));
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => $rightindex, 'checked' => 'checked'),
                array('disabled' => 'disabled')), $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => ($rightindex + 1) % 3),
                array('disabled' => 'disabled', 'checked' => 'checked')), $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => ($rightindex + 2) % 3),
                array('disabled' => 'disabled', 'checked' => 'checked')), $html);
        $this->assertPattern('/class=\"correctness/', $html);
        $this->assertPattern('/' . preg_quote(get_string('correct', 'question')) . '/', $html);

        // Finish the attempt.
        $quba->finish_all_questions();
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::GRADED_CORRECT);
        $this->assertEqual($quba->get_question_mark($qnumber), 3 * (1 - $mc->penalty));
        $this->assertPattern('/' . preg_quote(get_string('correct', 'question')) . '/', $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => $rightindex,
                'disabled' => 'disabled')), $html);

        // Process a manual comment.
        $quba->manual_grade($qnumber, 1, 'Not good enough!');
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::MANUALLY_GRADED_PARTCORRECT);
        $this->assertEqual($quba->get_question_mark($qnumber), 1);
        $this->assertPattern('/' . preg_quote('Not good enough!') . '/', $html);

        // Now change the correct answer to the question, and regrade.
        $mc->answers[13]->fraction = -0.33333333;
        $mc->answers[15]->fraction = 1;
        $quba->regrade_all_questions();
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::MANUALLY_GRADED_PARTCORRECT);
        $this->assertWithinMargin($quba->get_question_mark($qnumber), 1, 0.0000001);
        $this->assertPattern(
                '/' . preg_quote(get_string('incorrect', 'question')) . '/',
                $html);

        $numsteps = $quba->get_question_attempt($qnumber)->get_num_steps();
        $autogradedstep = $quba->get_question_attempt($qnumber)->get_step($numsteps - 2);
        $this->assertWithinMargin($autogradedstep->get_fraction(), 0, 0.0000001);
    }
}
