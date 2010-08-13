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

function xmldb_quizreport_overview_upgrade($oldversion) {

    global $CFG, $THEME, $db;

    $result = true;

/// And upgrade begins here. For each one, you'll need one
/// block of code similar to the next one. Please, delete
/// this comment lines once this file start handling proper
/// upgrade code.

    if ($result && $oldversion < 2010040600) {

    /// Wipe the quiz_question_regrade before we changes its structure. The data
    /// It contains is not important long-term, and it is almost impossible to upgrade.
        $result = $result && delete_records('quiz_question_regrade');
    }

    if ($result && $oldversion < 2010040601) {

    /// Rename field attemptid on table quiz_question_regrade to questionusageid
        $table = new XMLDBTable('quiz_question_regrade');
        $field = new XMLDBField('attemptid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'questionid');

    /// Launch rename field attemptid
        $result = $result && rename_field($table, $field, 'questionusageid');
    }

    if ($result && $oldversion < 2010040602) {

    /// Define field slot to be added to quiz_question_regrade
        $table = new XMLDBTable('quiz_question_regrade');
        $field = new XMLDBField('slot');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'questionusageid');

    /// Launch add field slot
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010040603) {

    /// Define field questionid to be dropped from quiz_question_regrade
        $table = new XMLDBTable('quiz_question_regrade');
        $field = new XMLDBField('questionid');

    /// Launch drop field questionid
        $result = $result && drop_field($table, $field);
    }

    if ($result && $oldversion < 2010040604) {

    /// Rename field newgrade on table quiz_question_regrade to newfraction
        $table = new XMLDBTable('quiz_question_regrade');
        $field = new XMLDBField('newgrade');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, null, null, null, 'slot');

    /// Launch rename field newfraction
        $result = $result && rename_field($table, $field, 'newfraction');
    }

    if ($result && $oldversion < 2010040605) {

    /// Rename field oldgrade on table quiz_question_regrade to oldfraction
        $table = new XMLDBTable('quiz_question_regrade');
        $field = new XMLDBField('oldgrade');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, null, null, null, 'slot');

    /// Launch rename field newfraction
        $result = $result && rename_field($table, $field, 'oldfraction');
    }

    if ($result && $oldversion < 2010040606) {

    /// Changing precision of field oldfraction on table quiz_question_regrade to (12, 7)
        $table = new XMLDBTable('quiz_question_regrade');
        $field = new XMLDBField('oldfraction');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, null, null, null, 'newfraction');

    /// Launch change of precision for field oldfraction
        $result = $result && change_field_precision($table, $field);
    }

    if ($result && $oldversion < 2010040607) {

    /// Changing precision of field newfraction on table quiz_question_regrade to (12, 7)
        $table = new XMLDBTable('quiz_question_regrade');
        $field = new XMLDBField('newfraction');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, null, null, null, 'slot');

    /// Launch change of precision for field newfraction
        $result = $result && change_field_precision($table, $field);
    }

    if ($result && $oldversion < 2010081200) {

    /// Rename field numberinusage on table quiz_question_regrade to slot
        $table = new XMLDBTable('quiz_question_regrade');
        $field = new XMLDBField('numberinusage');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'questionusageid');

    /// Launch rename field slot
        $result = $result && rename_field($table, $field, 'slot');
    }

    return $result;
}
