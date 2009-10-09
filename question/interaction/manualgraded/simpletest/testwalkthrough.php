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
 * This file contains tests that walks a question through the manual graded
 * interaction model.
 *
 * @package question-engine
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once(dirname(__FILE__) . '/../../../engine/simpletest/helpers.php');

class question_manualgraded_model_walkthrough_test extends UnitTestCase {
    public function test_manual_graded_essay() {

        // Create a true-false question with correct answer true.
        $essay = test_question_maker::make_an_essay_question();
        $displayoptions = new question_display_options();

        // Start a delayed feedback attempt and add the question to it.
        $essay->maxmark = 10;
        $quba = question_engine::make_questions_usage_by_activity('unit_test');
        $quba->set_preferred_interaction_model('delayedfeedback');
        $qnumber = $quba->add_question($essay);

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
        $this->assertPattern('/' . preg_quote($essay->questiontext) . '/', $html);

        // Simulate some data submitted by the student.
        $submitteddata = array('answer' => 'This is my wonderful essay!');
        $quba->process_action($qnumber, $submitteddata);
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::COMPLETE);
        $this->assertNull($quba->get_question_mark($qnumber));
        $this->assert(new ContainsTagWithAttribute('textarea', 'name',
                $quba->get_question_attempt($qnumber)->get_qt_field_name('answer')), $html);

        // Process the same data again, check it does not create a new step.
        $numsteps = $quba->get_question_attempt($qnumber)->get_num_steps();
        $quba->process_action($qnumber, $submitteddata);
        $this->assertEqual($quba->get_question_attempt($qnumber)->get_num_steps(), $numsteps);

        // Process different data, check it creates a new step.
        $quba->process_action($qnumber, array('answer' => ''));
        $this->assertEqual($quba->get_question_attempt($qnumber)->get_num_steps(), $numsteps + 1);
        $this->assertEqual($quba->get_question_state($qnumber), question_state::INCOMPLETE);

        // Change back, check it creates a new step.
        $quba->process_action($qnumber, $submitteddata);
        $this->assertEqual($quba->get_question_attempt($qnumber)->get_num_steps(), $numsteps + 2);

        // Finish the attempt.
        $quba->finish_all_questions();
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::NEEDS_GRADING);
        $this->assertNull($quba->get_question_mark($qnumber));

        // Process a manual comment.
        $quba->manual_grade($qnumber, 10, 'Not good enough!');
        $html = $quba->render_question($qnumber, $displayoptions);

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::MANUALLY_GRADED_CORRECT);
        $this->assertEqual($quba->get_question_mark($qnumber), 10);
        $this->assertPattern('/' . preg_quote('Not good enough!') . '/', $html);

        // Now change the max mark for the question and regrade.
        $essay->maxmark = 1;
        $html = $quba->regrade_all_questions();

        // Verify.
        $this->assertEqual($quba->get_question_state($qnumber), question_state::MANUALLY_GRADED_CORRECT);
        $this->assertEqual($quba->get_question_mark($qnumber), 1);
    }
}
