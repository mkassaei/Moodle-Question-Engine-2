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
 * @package question-engine
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once(dirname(__FILE__) . '/../../../engine/simpletest/helpers.php');

class qim_deferredcbm_walkthrough_test extends UnitTestCase {
    public function test_delayed_cbm_truefalse_high_certainty() {

        // Create a true-false question with correct answer true.
        $tf = test_question_maker::make_a_truefalse_question();
        $displayoptions = new question_display_options();

        // Start a delayed feedback attempt with CBM and add the question to it.
        $tf->maxmark = 2;
        $quba = question_engine::make_questions_usage_by_activity('unit_test');
        $quba->set_preferred_interaction_model('deferredcbm');
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
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::INCOMPLETE);
        $this->assertNull($quba->get_question_mark($qnumber));
        $this->assertPattern('/' . preg_quote($tf->questiontext) . '/', $html);

        // Simulate some data submitted by the student.
        $prefix = $quba->get_field_prefix($qnumber);
        $answername = $prefix . 'answer';
        $certaintyname = $prefix . '!certainty';
        $getdata = array(
            $answername => 1,
            $certaintyname => 3,
            'irrelevant' => 'should be ignored',
        );
        $submitteddata = $quba->extract_responses($qnumber, $getdata);

        // Verify.
        $this->assertEqual(array('answer' => 1, '!certainty' => 3), $submitteddata);

        // Process the data extracted for this question.
        $quba->process_action($qnumber, $submitteddata);
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::COMPLETE);
        $this->assertNull($quba->get_question_mark($qnumber));
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $answername, 'value' => 1, 'checked' => 'checked')), $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $certaintyname, 'value' => 3, 'checked' => 'checked'),
                array('disabled' => 'disabled')), $html);
        $this->assertNoPattern('/class=\"correctness/', $html);

        // Process the same data again, check it does not create a new step.
        $numsteps = $quba->get_question_attempt($qnumber)->get_num_steps();
        $quba->process_action($qnumber, $submitteddata);
        $this->assertEqual($quba->get_question_attempt($qnumber)->get_num_steps(), $numsteps);

        // Process different data, check it creates a new step.
        $quba->process_action($qnumber, array('answer' => 1, '!certainty' => 1));
        $this->assertEqual($quba->get_question_attempt($qnumber)->get_num_steps(), $numsteps + 1);
        $this->assertEqual($quba->get_question_state($qnumber), question_state::COMPLETE);

        // Change back, check it creates a new step.
        $quba->process_action($qnumber, $submitteddata);
        $this->assertEqual($quba->get_question_attempt($qnumber)->get_num_steps(), $numsteps + 2);

        // Finish the attempt.
        $quba->finish_all_questions();
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::GRADED_CORRECT);
        $this->assertEqual($quba->get_question_mark($qnumber), 2);
        $this->assertPattern(
                '/' . preg_quote(get_string('correct', 'question')) . '/',
                $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $certaintyname, 'value' => 3,
                'checked' => 'checked', 'disabled' => 'disabled')), $html);

        // Process a manual comment.
        $quba->manual_grade($qnumber, 1, 'Not good enough!');
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::MANUALLY_GRADED_PARTCORRECT);
        $this->assertEqual($quba->get_question_mark($qnumber), 1);
        $this->assertPattern('/' . preg_quote('Not good enough!') . '/', $html);

        // Now change the correct answer to the question, and regrade.
        $tf->rightanswer = false;
        $quba->regrade_all_questions();

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::MANUALLY_GRADED_PARTCORRECT);
        $this->assertEqual($quba->get_question_mark($qnumber), 1);
        $numsteps = $quba->get_question_attempt($qnumber)->get_num_steps();
        $autogradedstep = $quba->get_question_attempt($qnumber)->get_step($numsteps - 2);
        $this->assertWithinMargin($autogradedstep->get_fraction(), -2, 0.0000001);
    }

    public function test_delayed_cbm_truefalse_low_certainty() {

        // Create a true-false question with correct answer true.
        $tf = test_question_maker::make_a_truefalse_question();
        $displayoptions = new question_display_options();

        // Start a delayed feedback attempt and add the question to it.
        $tf->maxmark = 2;
        $quba = question_engine::make_questions_usage_by_activity('unit_test');
        $quba->set_preferred_interaction_model('deferredcbm');
        $qnumber = $quba->add_question($tf);
        // Different from $tf->id since the same question may be used twice in
        // the same attempt.
        $prefix = $quba->get_field_prefix($qnumber);
        $certaintyname = $prefix . '!certainty';

        // Begin the attempt. Creates an initial state with no certainty - should be incomplete.
        $quba->start_all_questions();
        $quba->process_action($qnumber, array('answer' => 1));
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::INCOMPLETE);
        $this->assertNull($quba->get_question_mark($qnumber));
        $this->assertNoPattern('/class=\"correctness/', $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $certaintyname, 'value' => 1)), $html);

        // Sublint ansewer with low certainty.
        $quba->start_all_questions();
        $quba->process_action($qnumber, array('answer' => 1, '!certainty' => 1));
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::COMPLETE);
        $this->assertNull($quba->get_question_mark($qnumber));
        $this->assertNoPattern('/class=\"correctness/', $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $certaintyname, 'value' => 1,
                'checked' => 'checked'), array('disabled' => 'disabled')), $html);

        // Finish the attempt.
        $quba->finish_all_questions();
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::GRADED_CORRECT);
        $this->assertWithinMargin($quba->get_question_mark($qnumber), 0.6666667, 0.0000001);
        $this->assertPattern(
                '/' . preg_quote(get_string('correct', 'question')) . '/',
                $html);
        $this->assert(new ContainsTagWithAttributes('input',
                array('type' => 'radio', 'name' => $certaintyname, 'value' => 1,
                'checked' => 'checked', 'disabled' => 'disabled')), $html);
    }
}

