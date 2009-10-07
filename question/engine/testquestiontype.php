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
 * Simple test question type, for working out the new qtype API.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class test_question_type {
    public function get_interaction_model(question_attempt $qa, $preferredmodel) {
        return new question_deferredfeedback_model($qa);
    }

    public function get_renderer($question) {
        return renderer_factory::get_renderer('qtype_truefalse');
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        // Check that the two arrays have exactly the same keys and values.
        $diff1 = array_diff_assoc($prevresponse, $newresponse);
        if (!empty($diff1)) {
            return false;
        }
        $diff2 = array_diff_assoc($newresponse, $prevresponse);
        return empty($diff2);
    }

    public function is_complete_response(array $response) {
        return isset($response['true']) || isset($response['false']);
    }

    public function is_gradable_response(array $response) {
        return $this->is_complete_response($response);
    }

    public function grade_response(array $response) {
        if (isset($response['true']) && $response['true']) {
            $grade = 1;
        } else {
            $grade = 0;
        }
        return array($grade, question_state::graded_state_for_grade($grade));
    }
}


class qtype_truefalse_renderer extends qtype_renderer {
}


