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
 * Unit tests for question/type/numerical/questiontype.php.
 *
 * @package qtype_numerical
 * @copyright 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/type/numerical/questiontype.php');


/**
 * Unit tests for question/type/numerical/questiontype.php.
 *
 * @copyright 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_numerical_test extends UnitTestCase {
    protected $tolerance = 0.00000001;
    protected $qtype;

    public function setUp() {
        $this->qtype = new qtype_numerical();
    }

    public function tearDown() {
        $this->qtype = null;   
    }

    public function test_name() {
        $this->assertEqual($this->qtype->name(), 'numerical');
    }
}
