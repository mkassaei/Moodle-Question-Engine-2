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
}