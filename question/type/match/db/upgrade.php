<?php  // $Id$

// This file keeps track of upgrades to 
// the match qtype plugin
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

function xmldb_qtype_match_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;

/// And upgrade begins here. For each one, you'll need one 
/// block of code similar to the next one. Please, delete 
/// this comment lines once this file start handling proper
/// upgrade code.

/// if ($result && $oldversion < YYYYMMDD00) { //New version in version.php
///     $result = result of "/lib/ddllib.php" function calls
/// }

    if ($result && $oldversion < 2010042800) {

    /// Define field correctfeedback to be added to question_match
        $table = new XMLDBTable('question_match');
        $field = new XMLDBField('correctfeedback');
        $field->setAttributes(XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL, null, null, null, '', 'shuffleanswers');

    /// Launch add field correctfeedback
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010042801) {

    /// Define field partiallycorrectfeedback to be added to question_match
        $table = new XMLDBTable('question_match');
        $field = new XMLDBField('partiallycorrectfeedback');
        $field->setAttributes(XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL, null, null, null, '', 'correctfeedback');

    /// Launch add field partiallycorrectfeedback
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010042802) {

    /// Define field incorrectfeedback to be added to question_match
        $table = new XMLDBTable('question_match');
        $field = new XMLDBField('incorrectfeedback');
        $field->setAttributes(XMLDB_TYPE_TEXT, 'small', null, XMLDB_NOTNULL, null, null, null, '', 'partiallycorrectfeedback');

    /// Launch add field incorrectfeedback
        $result = $result && add_field($table, $field);
    }

    if ($result && $oldversion < 2010042803) {

    /// Define field shownumcorrect to be added to question_match
        $table = new XMLDBTable('question_match');
        $field = new XMLDBField('shownumcorrect');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, null, null, '0', 'incorrectfeedback');

    /// Launch add field shownumcorrect
        $result = $result && add_field($table, $field);
    }

    return $result;
}

?>
