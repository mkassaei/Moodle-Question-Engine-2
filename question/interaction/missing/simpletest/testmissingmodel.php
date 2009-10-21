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
 * This file contains tests for the 'missing' interaction model.
 *
 * @package qim_missing
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../engine/lib.php');
require_once(dirname(__FILE__) . '/../../../engine/simpletest/helpers.php');
require_once(dirname(__FILE__) . '/../model.php');

class qim_missing_test extends UnitTestCase {
    public function test_missing_cannot_start() {
        $qa = new question_attempt(test_question_maker::make_a_truefalse_question(), 0);
        $model = new qim_missing($qa);
        $this->expectException();
        $model->init_first_step(null);
    }

    public function test_missing_cannot_process() {
        $qa = new question_attempt(test_question_maker::make_a_truefalse_question(), 0);
        $model = new qim_missing($qa);
        $this->expectException();
        $model->process_action(null);
    }

    public function test_missing_cannot_get_min_grade() {
        $qa = new question_attempt(test_question_maker::make_a_truefalse_question(), 0);
        $model = new qim_missing($qa);
        $this->expectException();
        $model->get_min_fraction();
    }

    // TODO test you can render an state with a missing model loaded, as if from the DB.
}
