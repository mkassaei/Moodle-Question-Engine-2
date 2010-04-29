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
 * This file contains tests for the question_engine class.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../lib.php');

class question_engine_test extends UnitTestCase {

    public function setUp() {

    }

    public function tearDown() {

    }

    public function test_load_behaviour_class() {
        // Exercise SUT
        question_engine::load_behaviour_class('deferredfeedback');
        // Verify
        $this->assertTrue(class_exists('qbehaviour_deferredfeedback'));
    }

    public function test_load_behaviour_class_missing() {
        // Set expectation.
        $this->expectException();
        // Exercise SUT
        question_engine::load_behaviour_class('nonexistantbehaviour');
    }
}