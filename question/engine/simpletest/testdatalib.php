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

class qubaid_condition_test extends UnitTestCase {

    protected function check_typical_question_attempts_query(qubaid_condition $qubaids, $expectedsql) {
        $sql = "SELECT qa.id, qa.maxmark
            FROM {$qubaids->from_question_attempts('qa')}
            WHERE {$qubaids->where()} AND qa.numberinusage = 1";
        $this->assertEqual($expectedsql, $sql);
    }

    protected function check_typical_in_query(qubaid_condition $qubaids, $expectedsql) {
        global $CFG;
        $sql = "SELECT qa.id, qa.maxmark
            FROM {$CFG->prefix}question_attempts qa
            WHERE qa.questionusageid {$qubaids->usage_id_in()}";
        $this->assertEqual($expectedsql, $sql);
    }

    public function test_qubaid_list_one_join() {
        global $CFG;
        $qubaids = new qubaid_list(array(1));
        $this->check_typical_question_attempts_query($qubaids,
                "SELECT qa.id, qa.maxmark
            FROM {$CFG->prefix}question_attempts_new qa
            WHERE qa.questionusageid = '1' AND qa.numberinusage = 1");
    }

    public function test_qubaid_list_several_join() {
        global $CFG;
        $qubaids = new qubaid_list(array(1, 3, 7));
        $this->check_typical_question_attempts_query($qubaids,
                "SELECT qa.id, qa.maxmark
            FROM {$CFG->prefix}question_attempts_new qa
            WHERE qa.questionusageid IN ('1','3','7') AND qa.numberinusage = 1");
    }

    public function test_qubaid_join() {
        global $CFG;
        $qubaids = new qubaid_join("{$CFG->prefix}other_table ot", 'ot.usageid', 'ot.id = 1');

        $this->check_typical_question_attempts_query($qubaids,
                "SELECT qa.id, qa.maxmark
            FROM {$CFG->prefix}other_table ot
                JOIN {$CFG->prefix}question_attempts_new qa ON qa.questionusageid = ot.usageid
            WHERE ot.id = 1 AND qa.numberinusage = 1");
    }

    public function test_qubaid_join_no_where_join() {
        global $CFG;
        $qubaids = new qubaid_join("{$CFG->prefix}other_table ot", 'ot.usageid');

        $this->check_typical_question_attempts_query($qubaids,
                "SELECT qa.id, qa.maxmark
            FROM {$CFG->prefix}other_table ot
                JOIN {$CFG->prefix}question_attempts_new qa ON qa.questionusageid = ot.usageid
            WHERE 1 = 1 AND qa.numberinusage = 1");
    }

    public function test_qubaid_list_one_in() {
        global $CFG;
        $qubaids = new qubaid_list(array(1));
        $this->check_typical_in_query($qubaids,
                "SELECT qa.id, qa.maxmark
            FROM {$CFG->prefix}question_attempts qa
            WHERE qa.questionusageid = '1'");
    }

    public function test_qubaid_list_several_in() {
        global $CFG;
        $qubaids = new qubaid_list(array(1, 2, 3));
        $this->check_typical_in_query($qubaids,
                "SELECT qa.id, qa.maxmark
            FROM {$CFG->prefix}question_attempts qa
            WHERE qa.questionusageid IN ('1','2','3')");
    }

    public function test_qubaid_join_in() {
        global $CFG;
        $qubaids = new qubaid_join("{$CFG->prefix}other_table ot", 'ot.usageid', 'ot.id = 1');

        $this->check_typical_in_query($qubaids,
                "SELECT qa.id, qa.maxmark
            FROM {$CFG->prefix}question_attempts qa
            WHERE qa.questionusageid IN (SELECT ot.usageid FROM {$CFG->prefix}other_table ot WHERE ot.id = 1)");
    }

    public function test_qubaid_join_no_where_in() {
        global $CFG;
        $qubaids = new qubaid_join("{$CFG->prefix}other_table ot", 'ot.usageid');

        $this->check_typical_in_query($qubaids,
                "SELECT qa.id, qa.maxmark
            FROM {$CFG->prefix}question_attempts qa
            WHERE qa.questionusageid IN (SELECT ot.usageid FROM {$CFG->prefix}other_table ot WHERE 1 = 1)");
    }
}