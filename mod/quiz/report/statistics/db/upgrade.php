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

    /// Define field slot to be added to quiz_question_statistics
        $table = new XMLDBTable('quiz_question_statistics');
        $field = new XMLDBField('slot');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'questionid');

    /// Launch add field slot
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010032401) {

    /// Delete all cached data
        $result = $result && delete_records('quiz_question_response_stats');
        $result = $result && delete_records('quiz_question_statistics');
        $result = $result && delete_records('quiz_statistics');

    /// Rename field maxgrade on table quiz_question_statistics to maxmark
        $table = new XMLDBTable('quiz_question_statistics');
        $field = new XMLDBField('maxgrade');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', XMLDB_UNSIGNED, null, null, null, null, null, 'subquestions');

    /// Launch rename field maxmark
        $result = $result && rename_field($table, $field, 'maxmark');
    }

    if ($result && $oldversion < 2010062200) {

    /// Changing nullability of field aid on table quiz_question_response_stats to null
        $table = new XMLDBTable('quiz_question_response_stats');
        $field = new XMLDBField('aid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'subqid');

    /// Launch change of nullability for field aid
        $result = $result && change_field_notnull($table, $field);
    }

    if ($result && $oldversion < 2010070300) {

    /// Rename field qnumber on table quiz_question_statistics to slot, if
    /// it is not already called that. The 2010032400 change above was amended to
    /// Create a column slot directly.
        $table = new XMLDBTable('quiz_question_statistics');
        $field = new XMLDBField('qnumber');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'questionid');

    /// Launch rename field slot
        if (field_exists($table, $field)) {
            $result = $result && rename_field($table, $field, 'slot');
        }
    }

    if ($result && $oldversion < 2010070301) {

    /// Changing type of field maxmark on table quiz_question_statistics to number
        $table = new XMLDBTable('quiz_question_statistics');
        $field = new XMLDBField('maxmark');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', XMLDB_UNSIGNED, null, null, null, null, null, 'subquestions');

    /// Launch change of type for field maxmark
        $result = $result && change_field_type($table, $field);
    }

    return $result;
}
