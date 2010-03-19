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
 * Question type class for the 'missingtype' type.
 *
 * @package qtype_missingtype
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Missing question type class
 *
 * When we encounter a question of a type that is not currently installed, then
 * we use this question type class instead so that some of the information about
 * this question can be seen, and the rest of the system keeps working.
 *
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_missingtype extends question_type {
    public function menu_name() {
        return false;
    }

    public function is_usable_by_random() {
        return false;
    }

    public function can_analyse_responses() {
        return false;
    }

    public function get_random_guess_score($questiondata) {
        return null;
    }

    public function display_question_editing_page($mform, $question, $wizardnow){
        print_heading(get_string('missingqtypewarning', 'qtype_missingtype'));
        $mform->display();
    }
}
question_register_questiontype(question_bank::get_qtype('missingtype'));
