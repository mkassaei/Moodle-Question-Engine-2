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
 * This file contains tests for the {@link question_utils} class.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../lib.php');

class question_utils_test extends UnitTestCase {
    public function test_arrays_have_same_keys_and_values() {
        $this->assertTrue(question_utils::arrays_have_same_keys_and_values(
                array(),
                array()));
        $this->assertTrue(question_utils::arrays_have_same_keys_and_values(
                array('key' => 1),
                array('key' => '1')));
        $this->assertFalse(question_utils::arrays_have_same_keys_and_values(
                array(),
                array('key' => 1)));
        $this->assertFalse(question_utils::arrays_have_same_keys_and_values(
                array('key' => 2),
                array('key' => 1)));
        $this->assertFalse(question_utils::arrays_have_same_keys_and_values(
                array('key' => 1),
                array('otherkey' => 1)));
        $this->assertFalse(question_utils::arrays_have_same_keys_and_values(
                array('sub0' => '2', 'sub1' => '2', 'sub2' => '3', 'sub3' => '1'),
                array('sub0' => '1', 'sub1' => '2', 'sub2' => '3', 'sub3' => '1')));
    }

    public function test_arrays_same_at_key() {
        $this->assertTrue(question_utils::arrays_same_at_key(
                array(),
                array(),
                'key'));
        $this->assertFalse(question_utils::arrays_same_at_key(
                array(),
                array('key' => 1),
                'key'));
        $this->assertFalse(question_utils::arrays_same_at_key(
                array('key' => 1),
                array(),
                'key'));
        $this->assertTrue(question_utils::arrays_same_at_key(
                array('key' => 1),
                array('key' => 1),
                'key'));
        $this->assertFalse(question_utils::arrays_same_at_key(
                array('key' => 1),
                array('key' => 2),
                'key'));
        $this->assertTrue(question_utils::arrays_same_at_key(
                array('key' => 1),
                array('key' => '1'),
                'key'));
        $this->assertFalse(question_utils::arrays_same_at_key(
                array('key' => 0),
                array('key' => ''),
                'key'));
        $this->assertFalse(question_utils::arrays_same_at_key(
                array(),
                array('key' => ''),
                'key'));
    }

    public function test_arrays_same_at_key_missing_is_blank() {
        $this->assertTrue(question_utils::arrays_same_at_key_missing_is_blank(
                array(),
                array(),
                'key'));
        $this->assertFalse(question_utils::arrays_same_at_key_missing_is_blank(
                array(),
                array('key' => 1),
                'key'));
        $this->assertFalse(question_utils::arrays_same_at_key_missing_is_blank(
                array('key' => 1),
                array(),
                'key'));
        $this->assertTrue(question_utils::arrays_same_at_key_missing_is_blank(
                array('key' => 1),
                array('key' => 1),
                'key'));
        $this->assertFalse(question_utils::arrays_same_at_key_missing_is_blank(
                array('key' => 1),
                array('key' => 2),
                'key'));
        $this->assertTrue(question_utils::arrays_same_at_key_missing_is_blank(
                array('key' => 1),
                array('key' => '1'),
                'key'));
        $this->assertFalse(question_utils::arrays_same_at_key_missing_is_blank(
                array('key' => '0'),
                array('key' => ''),
                'key'));
        $this->assertTrue(question_utils::arrays_same_at_key_missing_is_blank(
                array(),
                array('key' => ''),
                'key'));
    }
}