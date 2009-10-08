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
 * This file contains tests for the question_attempt_setp class.
 *
 * @package question-engine
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../lib.php');

class question_attempt_step_test extends UnitTestCase {
    public function test_initial_state_unprocessed() {
        $step = new question_attempt_step();
        $this->assertEqual(question_state::UNPROCESSED, $step->get_state());
    }

    public function test_get_set_state() {
        $step = new question_attempt_step();
        $step->set_state(question_state::GRADED_CORRECT);
        $this->assertEqual(question_state::GRADED_CORRECT, $step->get_state());
    }

    public function test_initial_grade_null() {
        $step = new question_attempt_step();
        $this->assertNull($step->get_grade());
    }

    public function test_get_set_grade() {
        $step = new question_attempt_step();
        $step->set_grade(0.5);
        $this->assertEqual(0.5, $step->get_grade());
    }

    public function test_has_var() {
        $step = new question_attempt_step(array('x' => 1, '!y' => 'frog'));
        $this->assertTrue($step->has_qt_var('x'));
        $this->assertTrue($step->has_im_var('y'));
        $this->assertFalse($step->has_qt_var('y'));
        $this->assertFalse($step->has_im_var('x'));
    }

    public function test_get_var() {
        $step = new question_attempt_step(array('x' => 1, '!y' => 'frog'));
        $this->assertEqual('1', $step->get_qt_var('x'));
        $this->assertEqual('frog', $step->get_im_var('y'));
        $this->expectException();
        $step->get_qt_var('y');
    }

    public function test_set_var() {
        $step = new question_attempt_step();
        $step->set_qt_var('_x', 1);
        $step->set_im_var('_x', 2);
        $this->assertEqual('1', $step->get_qt_var('_x'));
        $this->assertEqual('2', $step->get_im_var('_x'));
    }

    public function test_cannot_set_qt_var_without_underscore() {
        $step = new question_attempt_step();
        $this->expectException();
        $step->set_qt_var('x', 1);
    }

    public function test_cannot_set_im_var_without_underscore() {
        $step = new question_attempt_step();
        $this->expectException();
        $step->set_im_var('x', 1);
    }

    public function test_get_data() {
        $step = new question_attempt_step(array('x' => 1, '!y' => 'frog'));
        $this->assertEqual(array('x' => '1'), $step->get_qt_data());
        $this->assertEqual(array('y' => 'frog'), $step->get_im_data());
    }

    public function test_constructor_default_params() {
        global $USER;
        $step = new question_attempt_step();
        $this->assertWithinMargin(time(), $step->get_timestamp(), 5);
        $this->assertEqual($USER->id, $step->get_user_id());
        $this->assertEqual(array(), $step->get_qt_data());
        $this->assertEqual(array(), $step->get_im_data());

    }

    public function test_constructor_given_params() {
        global $USER;
        $step = new question_attempt_step(array(), 123, 5);
        $this->assertEqual(123, $step->get_timestamp());
        $this->assertEqual(5, $step->get_user_id());
        $this->assertEqual(array(), $step->get_qt_data());
        $this->assertEqual(array(), $step->get_im_data());

    }
}