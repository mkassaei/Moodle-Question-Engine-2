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
 * This file contains tests for the question_state class.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../lib.php');

class question_state_test extends UnitTestCase {
    public function test_is_active() {
        $this->assertFalse(question_state::is_active(question_state::NOT_STARTED));
        $this->assertFalse(question_state::is_active(question_state::UNPROCESSED));
        $this->assertTrue(question_state::is_active(question_state::INCOMPLETE));
        $this->assertTrue(question_state::is_active(question_state::COMPLETE));
        $this->assertFalse(question_state::is_active(question_state::NEEDS_GRADING));
        $this->assertFalse(question_state::is_active(question_state::FINISHED));
        $this->assertFalse(question_state::is_active(question_state::GAVE_UP));
        $this->assertFalse(question_state::is_active(question_state::GRADED_INCORRECT));
        $this->assertFalse(question_state::is_active(question_state::GRADED_PARTCORRECT));
        $this->assertFalse(question_state::is_active(question_state::GRADED_CORRECT));
        $this->assertFalse(question_state::is_active(question_state::FINISHED_COMMENTED));
        $this->assertFalse(question_state::is_active(question_state::GAVE_UP_COMMENTED));
        $this->assertFalse(question_state::is_active(question_state::MANUALLY_GRADED_INCORRECT));
        $this->assertFalse(question_state::is_active(question_state::MANUALLY_GRADED_PARTCORRECT));
        $this->assertFalse(question_state::is_active(question_state::MANUALLY_GRADED_CORRECT));
    }

    public function test_is_finished() {
        $this->assertFalse(question_state::is_finished(question_state::NOT_STARTED));
        $this->assertFalse(question_state::is_finished(question_state::UNPROCESSED));
        $this->assertFalse(question_state::is_finished(question_state::INCOMPLETE));
        $this->assertFalse(question_state::is_finished(question_state::COMPLETE));
        $this->assertTrue(question_state::is_finished(question_state::NEEDS_GRADING));
        $this->assertTrue(question_state::is_finished(question_state::FINISHED));
        $this->assertTrue(question_state::is_finished(question_state::GAVE_UP));
        $this->assertTrue(question_state::is_finished(question_state::GRADED_INCORRECT));
        $this->assertTrue(question_state::is_finished(question_state::GRADED_PARTCORRECT));
        $this->assertTrue(question_state::is_finished(question_state::GRADED_CORRECT));
        $this->assertTrue(question_state::is_finished(question_state::FINISHED_COMMENTED));
        $this->assertTrue(question_state::is_finished(question_state::GAVE_UP_COMMENTED));
        $this->assertTrue(question_state::is_finished(question_state::MANUALLY_GRADED_INCORRECT));
        $this->assertTrue(question_state::is_finished(question_state::MANUALLY_GRADED_PARTCORRECT));
        $this->assertTrue(question_state::is_finished(question_state::MANUALLY_GRADED_CORRECT));
    }

    public function test_is_graded() {
        $this->assertFalse(question_state::is_graded(question_state::NOT_STARTED));
        $this->assertFalse(question_state::is_graded(question_state::UNPROCESSED));
        $this->assertFalse(question_state::is_graded(question_state::INCOMPLETE));
        $this->assertFalse(question_state::is_graded(question_state::COMPLETE));
        $this->assertFalse(question_state::is_graded(question_state::NEEDS_GRADING));
        $this->assertFalse(question_state::is_graded(question_state::FINISHED));
        $this->assertFalse(question_state::is_graded(question_state::GAVE_UP));
        $this->assertTrue(question_state::is_graded(question_state::GRADED_INCORRECT));
        $this->assertTrue(question_state::is_graded(question_state::GRADED_PARTCORRECT));
        $this->assertTrue(question_state::is_graded(question_state::GRADED_CORRECT));
        $this->assertFalse(question_state::is_graded(question_state::FINISHED_COMMENTED));
        $this->assertFalse(question_state::is_graded(question_state::GAVE_UP_COMMENTED));
        $this->assertTrue(question_state::is_graded(question_state::MANUALLY_GRADED_INCORRECT));
        $this->assertTrue(question_state::is_graded(question_state::MANUALLY_GRADED_PARTCORRECT));
        $this->assertTrue(question_state::is_graded(question_state::MANUALLY_GRADED_CORRECT));
    }

    public function test_is_commented() {
        $this->assertFalse(question_state::is_commented(question_state::NOT_STARTED));
        $this->assertFalse(question_state::is_commented(question_state::UNPROCESSED));
        $this->assertFalse(question_state::is_commented(question_state::INCOMPLETE));
        $this->assertFalse(question_state::is_commented(question_state::COMPLETE));
        $this->assertFalse(question_state::is_commented(question_state::NEEDS_GRADING));
        $this->assertFalse(question_state::is_commented(question_state::FINISHED));
        $this->assertFalse(question_state::is_commented(question_state::GAVE_UP));
        $this->assertFalse(question_state::is_commented(question_state::GRADED_INCORRECT));
        $this->assertFalse(question_state::is_commented(question_state::GRADED_PARTCORRECT));
        $this->assertFalse(question_state::is_commented(question_state::GRADED_CORRECT));
        $this->assertTrue(question_state::is_commented(question_state::FINISHED_COMMENTED));
        $this->assertTrue(question_state::is_commented(question_state::GAVE_UP_COMMENTED));
        $this->assertTrue(question_state::is_commented(question_state::MANUALLY_GRADED_INCORRECT));
        $this->assertTrue(question_state::is_commented(question_state::MANUALLY_GRADED_PARTCORRECT));
        $this->assertTrue(question_state::is_commented(question_state::MANUALLY_GRADED_CORRECT));
    }

    public function test_graded_state_for_fraction() {
        $this->assertEqual(question_state::GRADED_INCORRECT, question_state::graded_state_for_fraction(-1));
        $this->assertEqual(question_state::GRADED_INCORRECT, question_state::graded_state_for_fraction(0));
        $this->assertEqual(question_state::GRADED_PARTCORRECT, question_state::graded_state_for_fraction(0.0000001));
        $this->assertEqual(question_state::GRADED_PARTCORRECT, question_state::graded_state_for_fraction(0.9999999));
        $this->assertEqual(question_state::GRADED_CORRECT, question_state::graded_state_for_fraction(1));
    }

    public function test_manually_graded_state_for_other_state() {
        $this->assertEqual(question_state::FINISHED_COMMENTED,
                question_state::manually_graded_state_for_other_state(question_state::FINISHED, null));
        $this->assertEqual(question_state::GAVE_UP_COMMENTED,
                question_state::manually_graded_state_for_other_state(question_state::GAVE_UP, null));
        $this->assertEqual(question_state::FINISHED_COMMENTED,
                question_state::manually_graded_state_for_other_state(question_state::FINISHED_COMMENTED, null));
        $this->assertEqual(question_state::GAVE_UP_COMMENTED,
                question_state::manually_graded_state_for_other_state(question_state::GAVE_UP_COMMENTED, null));

        $this->assertEqual(question_state::MANUALLY_GRADED_INCORRECT,
                question_state::manually_graded_state_for_other_state(question_state::GAVE_UP, 0));
        $this->assertEqual(question_state::MANUALLY_GRADED_INCORRECT,
                question_state::manually_graded_state_for_other_state(question_state::NEEDS_GRADING, 0));
        $this->assertEqual(question_state::MANUALLY_GRADED_INCORRECT,
                question_state::manually_graded_state_for_other_state(question_state::GRADED_INCORRECT, 0));
        $this->assertEqual(question_state::MANUALLY_GRADED_INCORRECT,
                question_state::manually_graded_state_for_other_state(question_state::GRADED_PARTCORRECT, 0));
        $this->assertEqual(question_state::MANUALLY_GRADED_INCORRECT,
                question_state::manually_graded_state_for_other_state(question_state::GRADED_CORRECT, 0));
        $this->assertEqual(question_state::MANUALLY_GRADED_INCORRECT,
                question_state::manually_graded_state_for_other_state(question_state::MANUALLY_GRADED_INCORRECT, 0));
        $this->assertEqual(question_state::MANUALLY_GRADED_INCORRECT,
                question_state::manually_graded_state_for_other_state(question_state::MANUALLY_GRADED_PARTCORRECT, 0));
        $this->assertEqual(question_state::MANUALLY_GRADED_INCORRECT,
                question_state::manually_graded_state_for_other_state(question_state::MANUALLY_GRADED_CORRECT, 0));
    }
}