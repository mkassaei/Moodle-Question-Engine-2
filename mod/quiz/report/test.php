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
 * DB query experiments.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once('../../../config.php');
require_once('reportlib.php');
require_once('../locallib.php');

abstract class sql_expr {
    abstract public function __toString();
}

class sql_concat_expr {
    private $subexprs = array();
    public function __construct() {
        $subexprs = func_get_args();
        foreach ($subexprs as $subexpr) {
            $this->add($subexpr);
        }
    }
    public function add($part) {
        $this->subexprs[] = $part;
    }
    public function __toString() {
        return call_user_func_array('sql_concat', $this->subexprs);
    }
}

class sql_as_expr {
    private $exp;
    private $alias;
    public function __construct($exp, $alias) {
        $this->exp = $exp;
        $this->alias = $alias;
    }
    public function __toString() {
        return $this->exp . ' AS ' . $this->alias;
    }
}

class sql_test_against_value {
    private $field;
    private $value;
    private $op;

    public function __construct($field, $value, $op = '=') {
        $this->field = $field;
        $this->value = $value;
        $this->op = $op;
    }
    public function __toString() {
        return "$this->field $this->op '$this->value'";
    }
}

class sql_test_against_optional_value {
    private $field;
    private $value;
    private $op;

    public function __construct($field, $value, $op = '=') {
        $this->field = $field;
        $this->value = $value;
        $this->op = $op;
    }
    public function __toString() {
        return "$this->field $this->op '$this->value' OR $this->field  IS NULL";
    }
}

class sql_test_against_list {
    private $field;
    private $values;
    private $op;

    public function __construct($field, $values, $op = 'IN') {
        $this->field = $field;
        $this->value = $values;
        $this->op = $op;
    }
    public function __toString() {
        return "$this->field $this->op ('" . implode("','", $this->value) . "')";
    }
}

class sql_and_expr extends sql_expr {
    private $subexprs = array();
    public function __construct() {
        $subexprs = func_get_args();
        foreach ($subexprs as $subexpr) {
            $this->add($subexpr);
        }
    }
    public function add($test) {
        $this->subexprs[] = $test;
    }
    public function __toString() {
        return '(' . implode(") AND\n    (", $this->subexprs) . ')';
    }
}

class sql_select_query {
    private $fields = array();
    private $tables = array();
    public $where;
    public $sort = '';
    public function __construct($basetable) {
        global $CFG;
        $this->tables[] = $CFG->prefix . $basetable;
        $this->where = new sql_and_expr();
    }
    public function add_join($join) {
        $this->tables[] = $join;
    }
    public function add_field($field) {
        $this->fields[] = $field;
    }
    public function add_fields() {
        $fields = func_get_args();
        foreach ($fields as $field) {
            $this->add_field($field);
        }
    }
    public function __toString() {
        $sql = "SELECT \n    " . implode(",\n    ", $this->fields) .
                "\n\nFROM " . implode("\n", $this->tables) . "\n\nWHERE\n    " .
                $this->where;
        if (!empty($this->sort)) {
            $sql .= "\n\nORDER BY\n    " . $this->sort;
        }
        return $sql;
    }
    public function get_count_query() {
        $newquery = new sql_select_query('');
        $newquery->fields = array('COUNT(1)');
        $newquery->tables = $this->tables;
        $newquery->where = clone($this->where);
        return $newquery;
    }
}

function get_report_query($mode, $quizid, $grademethod, $onlygraded, $userids, $sort) {
    global $CFG;
    if ($grademethod == QUIZ_GRADEAVERAGE && $onlygraded) {
         throw new Exception();
    }

    $reportquery = new sql_select_query('quiz_attempts qa');

    $reportquery->add_fields(
        new sql_as_expr(new sql_concat_expr('u.id', "'#'", 'COALESCE(qa.uniqueid, 0)'), 'uniqueindex'),
        'u.id AS userid',
        'u.firstname',
        'u.lastname',
        'u.idnumber',
        'u.picture',
        'u.imagealt',
        'qa.uniqueid',
        'qa.id AS attemptid',
        'qa.timestart',
        'qa.timefinish',
        'qa.sumgrades');

    $outer = ($mode == QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO) ||
            ($mode == QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS);

    $userjoin = 'JOIN ' . $CFG->prefix . 'user u ON qa.userid = u.id';
    if ($outer) {
        $userjoin = 'RIGHT ' . $userjoin;
    }

    if ($outer) {
        $quizidtest = new sql_test_against_optional_value('qa.quiz', $quizid);
        $previewtest = new sql_test_against_optional_value('qa.preview', 0);
    } else {
        $quizidtest = new sql_test_against_value('qa.quiz', $quizid);
        $previewtest = new sql_test_against_value('qa.preview', 0);
    }

    if ($mode != QUIZ_REPORT_ATTEMPTS_ALL) {
        $reportquery->where->add(new sql_test_against_list('u.id', $userids));
    }
    $reportquery->where->add($quizidtest);
    if ($mode == QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO) {
        $reportquery->where->add('qa.id IS NULL');
    } else {
        $reportquery->where->add($previewtest);
    }

    if ($grademethod == QUIZ_GRADEAVERAGE) {
        $reportquery->add_field('0 AS isthegradedattempt');
    } else {
        $reportquery->add_field('CASE WHEN qa.id = gradedattempt.gradedid THEN 1 ELSE 0 END AS isthegradedattempt');
        switch ($grademethod) {
            case QUIZ_GRADEHIGHEST:
                $reportquery->add_join("
JOIN (
    SELECT qa1.userid, MIN(id) AS gradedid
    FROM {$CFG->prefix}quiz_attempts qa1
    JOIN (
        SELECT userid, MAX(sumgrades) AS maxgrade FROM {$CFG->prefix}quiz_attempts GROUP BY userid
    ) bestgrade ON bestgrade.userid = qa1.userid
    WHERE qa1.sumgrades = bestgrade.maxgrade
    GROUP BY qa1.userid
) gradedattempt ON gradedattempt.userid = qa.userid");
                break;
            case QUIZ_ATTEMPTFIRST:
                $reportquery->add_join("
JOIN (
    SELECT userid, MIN(id) AS gradedid
    FROM {$CFG->prefix}quiz_attempts
    WHERE quiz = 1
    GROUP BY userid
) gradedattempt ON gradedattempt.userid = qa.userid");
                break;
            case QUIZ_ATTEMPTLAST:
                $reportquery->add_join("
JOIN (
    SELECT userid, MAX(id) AS gradedid
    FROM {$CFG->prefix}quiz_attempts
    WHERE quiz = 1
    GROUP BY userid
) gradedattempt ON gradedattempt.userid = qa.userid");
                break;
        }
    }

    if ($onlygraded) {
        $isgradedtest = 'qa.id = gradedattempt.gradedid';
        if ($outer) {
            $isgradedtest .= ' OR (qa.id IS NULL AND gradedattempt.gradedid IS NULL)';
        }
        $reportquery->where->add($isgradedtest);
    }

    if (preg_match('/^q(\d+)$/', $sort, $matches)) {
        $qnumber = $matches[1];
        $reportquery->add_join("
JOIN {$CFG->prefix}question_attempts_new qasort ON qasort.questionusageid = qa.uniqueid AND qasort.numberinusage = $qnumber
JOIN (
    SELECT questionattemptid, MAX(id) AS latestid FROM {$CFG->prefix}question_attempt_steps GROUP BY questionattemptid
) lateststepidsort ON lateststepidsort.questionattemptid = qasort.id
JOIN {$CFG->prefix}question_attempt_steps qstepsort ON qstepsort.id = lateststepidsort.latestid");
        $reportquery->sort = 'qstepsort.fraction';
    } else {
        $reportquery->sort = $sort;
    }

    $reportquery->add_join("\n" . $userjoin);

    return $reportquery;
}


function test_query($mode, $quizid, $grademethod, $onlygraded, $userids, $sort) {
    echo "$mode, $quizid, $grademethod, $onlygraded, (" . implode(',', $userids) . "), $sort";
    $reportquery = get_report_query($mode, $quizid, $grademethod, $onlygraded, $userids, $sort);
    print_object('' . $reportquery);
    print_object(get_records_sql($reportquery));
}

// Test the various combinations.
test_query(QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS, 1, QUIZ_GRADEHIGHEST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS, 1, QUIZ_GRADEHIGHEST, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS, 1, QUIZ_GRADEAVERAGE, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS, 1, QUIZ_ATTEMPTFIRST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS, 1, QUIZ_ATTEMPTFIRST, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS, 1, QUIZ_ATTEMPTLAST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS, 1, QUIZ_ATTEMPTLAST, false, array(3, 4), 'q1');

test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO, 1, QUIZ_GRADEHIGHEST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO, 1, QUIZ_GRADEHIGHEST, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO, 1, QUIZ_GRADEAVERAGE, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO, 1, QUIZ_ATTEMPTFIRST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO, 1, QUIZ_ATTEMPTFIRST, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO, 1, QUIZ_ATTEMPTLAST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO, 1, QUIZ_ATTEMPTLAST, false, array(3, 4), 'q1');

test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH, 1, QUIZ_GRADEHIGHEST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH, 1, QUIZ_GRADEHIGHEST, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH, 1, QUIZ_GRADEAVERAGE, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH, 1, QUIZ_ATTEMPTFIRST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH, 1, QUIZ_ATTEMPTFIRST, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH, 1, QUIZ_ATTEMPTLAST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH, 1, QUIZ_ATTEMPTLAST, false, array(3, 4), 'q1');

test_query(QUIZ_REPORT_ATTEMPTS_ALL, 1, QUIZ_GRADEHIGHEST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL, 1, QUIZ_GRADEHIGHEST, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL, 1, QUIZ_GRADEAVERAGE, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL, 1, QUIZ_ATTEMPTFIRST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL, 1, QUIZ_ATTEMPTFIRST, false, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL, 1, QUIZ_ATTEMPTLAST, true, array(3, 4), 'q1');
test_query(QUIZ_REPORT_ATTEMPTS_ALL, 1, QUIZ_ATTEMPTLAST, false, array(3, 4), 'q1');

// Config options that affect the query.
//QUIZ_REPORT_ATTEMPTS_ALL
//QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH_NO
//QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH
//QUIZ_REPORT_ATTEMPTS_ALL_STUDENTS

//QUIZ_GRADEHIGHEST
//QUIZ_GRADEAVERAGE
//QUIZ_ATTEMPTFIRST
//QUIZ_ATTEMPTLAST

