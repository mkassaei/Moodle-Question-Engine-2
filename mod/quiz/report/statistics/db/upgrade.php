<?php

// This file keeps track of upgrades to
// the quiz statistics report.
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_quizreport_statistics_upgrade($oldversion) {

    global $CFG, $THEME, $db;

    $result = true;

/// And upgrade begins here. For each one, you'll need one
/// block of code similar to the next one. Please, delete
/// this comment lines once this file start handling proper
/// upgrade code.

    if ($result && $oldversion < 2010031700) {

    /// Define field randomguessscore to be added to quiz_question_statistics
        $table = new XMLDBTable('quiz_question_statistics');
        $field = new XMLDBField('randomguessscore');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', null, null, null, null, null, null, 'positions');

    /// Launch add field randomguessscore
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010032400) {

    /// Define field qnumber to be added to quiz_question_statistics
        $table = new XMLDBTable('quiz_question_statistics');
        $field = new XMLDBField('qnumber');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'questionid');

    /// Launch add field qnumber
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010032401) {

    /// Rename field maxgrade on table quiz_question_statistics to maxmark
        $table = new XMLDBTable('quiz_question_statistics');
        $field = new XMLDBField('maxgrade');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', XMLDB_UNSIGNED, null, null, null, null, null, 'subquestions');

    /// Launch rename field maxmark
        $result = $result && rename_field($table, $field, 'maxmark');

    /// Delete all cached data
        $result = $result && delete_records('quiz_question_response_stats');
        $result = $result && delete_records('quiz_question_statistics');
        $result = $result && delete_records('quiz_statistics');
    }

    return $result;
}
