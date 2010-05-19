<?php  // $Id$

// This file keeps track of upgrades to
// the quiz module
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

function xmldb_quiz_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;

/// And upgrade begins here. For each one, you'll need one
/// block of code similar to the next one. Please, delete
/// this comment lines once this file start handling proper
/// upgrade code.

    if ($result && $oldversion < 2007022800) {
    /// Ensure that there are not existing duplicate entries in the database.
        $duplicateunits = get_records_select('question_numerical_units', "id > (SELECT MIN(iqnu.id)
                FROM {$CFG->prefix}question_numerical_units iqnu
                WHERE iqnu.question = {$CFG->prefix}question_numerical_units.question AND
                        iqnu.unit = {$CFG->prefix}question_numerical_units.unit)", '', 'id');
        if ($duplicateunits) {
            delete_records_select('question_numerical_units', 'id IN (' . implode(',', array_keys($duplicateunits)) . ')');
        }

    /// Define index question-unit (unique) to be added to question_numerical_units
        $table = new XMLDBTable('question_numerical_units');
        $index = new XMLDBIndex('question-unit');
        $index->setAttributes(XMLDB_INDEX_UNIQUE, array('question', 'unit'));

    /// Launch add index question-unit
        $result = $result && add_index($table, $index);
    }

    if ($result && $oldversion < 2007070200) {

    /// Changing precision of field timelimit on table quiz to (10)
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('timelimit');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timemodified');

    /// Launch change of precision for field timelimit
        $result = $result && change_field_precision($table, $field);
    }

    if ($result && $oldversion < 2007072200) {
        require_once $CFG->dirroot.'/mod/quiz/lib.php';
        // too much debug output
        $db->debug = false;
        quiz_update_grades();
        $db->debug = true;
    }

    // Separate control for when overall feedback is displayed, independant of the question feedback settings.
    if ($result && $oldversion < 2007072600) {

        // Adjust the quiz review options so that overall feedback is displayed whenever feedback is.
        $result = $result && execute_sql('UPDATE ' . $CFG->prefix . 'quiz SET review = ' .
                sql_bitor(sql_bitand('review', sql_bitnot(QUIZ_REVIEW_OVERALLFEEDBACK)),
                sql_bitor(sql_bitand('review', QUIZ_REVIEW_FEEDBACK & QUIZ_REVIEW_IMMEDIATELY) . ' * 65536',
                sql_bitor(sql_bitand('review', QUIZ_REVIEW_FEEDBACK & QUIZ_REVIEW_OPEN) . ' * 16384',
                          sql_bitand('review', QUIZ_REVIEW_FEEDBACK & QUIZ_REVIEW_CLOSED) . ' * 4096'))));

        // Same adjustment to the defaults for new quizzes.
        $result = $result && set_config('quiz_review', ($CFG->quiz_review & ~QUIZ_REVIEW_OVERALLFEEDBACK) |
                (($CFG->quiz_review & QUIZ_REVIEW_FEEDBACK & QUIZ_REVIEW_IMMEDIATELY) << 16) |
                (($CFG->quiz_review & QUIZ_REVIEW_FEEDBACK & QUIZ_REVIEW_OPEN) << 14) |
                (($CFG->quiz_review & QUIZ_REVIEW_FEEDBACK & QUIZ_REVIEW_CLOSED) << 12));
    }

//===== 1.9.0 upgrade line ======//

    begin_sql();

//===== The following changes are for the quetsion engine rewrite.     ======//
// The first lot of changes repeat changes that have already been made in Moodle 2.0.
// 2008000000 is a conventional timestamp, chosen to be after all the above changes,
// but before any of the real 2.0 ones.

    /// Changing the type of all the columns that store grades to be NUMBER(10, 5).
    if ($result && $oldversion < 2008000000) {
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('sumgrades');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null, null, 0, 'questions');
        $result = $result && change_field_type($table, $field);
        upgrade_mod_savepoint($result, 2008000000, 'quiz');
    }

    if ($result && $oldversion < 2008000001) {
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('grade');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null, null, 0, 'sumgrades');
        $result = $result && change_field_type($table, $field);
        upgrade_mod_savepoint($result, 2008000001, 'quiz');
    }

    if ($result && $oldversion < 2008000002) {
        $table = new XMLDBTable('quiz_attempts');
        $field = new XMLDBField('sumgrades');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null, null, 0, 'attempt');
        $result = $result && change_field_type($table, $field);
        upgrade_mod_savepoint($result, 2008000002, 'quiz');
    }

    if ($result && $oldversion < 2008000003) {
        $table = new XMLDBTable('quiz_feedback');
        $field = new XMLDBField('mingrade');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null, null, 0, 'feedbacktext');
        $result = $result && change_field_type($table, $field);
        upgrade_mod_savepoint($result, 2008000003, 'quiz');
    }

    if ($result && $oldversion < 2008000004) {
        $table = new XMLDBTable('quiz_feedback');
        $field = new XMLDBField('maxgrade');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null, null, 0, 'mingrade');
        $result = $result && change_field_type($table, $field);
        upgrade_mod_savepoint($result, 2008000004, 'quiz');
    }

    if ($result && $oldversion < 2008000005) {
        $table = new XMLDBTable('quiz_grades');
        $field = new XMLDBField('grade');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null, null, 0, 'userid');
        $result = $result && change_field_type($table, $field);
        upgrade_mod_savepoint($result, 2008000005, 'quiz');
    }

    if ($result && $oldversion < 2008000006) {
        $table = new XMLDBTable('quiz_question_instances');
        $field = new XMLDBField('grade');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 5', null, XMLDB_NOTNULL, null, null, null, 0, 'question');
        $result = $result && change_field_type($table, $field);
        upgrade_mod_savepoint($result, 2008000006, 'quiz');
    }

        if ($result && $oldversion < 2008000007) {
    /// Add new questiondecimaldigits setting, separate form the overall decimaldigits one.
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('questiondecimalpoints');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null, null, '-1', 'decimalpoints');
        if (!field_exists($table, $field)) {
            $result = $result && add_field($table, $field);
        }

    /// quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000007, 'quiz');
    }

    /// New field showuserpicture to be added to quiz
    if ($result && $oldversion < 2008000010) {
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('showuserpicture');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null, null, '0', 'delay2');
        if (!field_exists($table, $field)) {
            $result = $result && add_field($table, $field);
        }

        $result = $result && set_config('quiz_showuserpicture', 0);
        $result = $result && set_config('quiz_fix_showuserpicture', 0);

    /// quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000010, 'quiz');
    }

    if ($result && $oldversion < 2008000020) {
    /// Convert quiz.timelimit from minutes to seconds.
        $result = $result && execute_sql("UPDATE {$CFG->prefix}quiz SET timelimit = timelimit * 60");
        $result = $result && set_config('quiz_timelimit', 60 * $CFG->quiz_timelimit);

    /// quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000020, 'quiz');
    }

    if ($result && $oldversion < 2008000030) {

    /// Define field introformat to be added to quiz
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('introformat');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'intro');

    /// Launch add field introformat
        $result = $result && add_field($table, $field);

    /// set format to current
        $result = $result && set_field('quiz', 'introformat', FORMAT_MOODLE, '', '');

    /// quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000030, 'quiz');
    }

// The following change are new changes required by the rewrite.

    // Add new preferredmodel column to the quiz table.
    if ($result && $oldversion < 2008000100) {
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('preferredmodel');
        $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, null, null, null, null, null, 'timeclose');
        if (!field_exists($table, $field)) {
            $result = $result && add_field($table, $field);
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000100, 'quiz');
    }

    // Populate preferredmodel column based on old optionflags column.
    if ($result && $oldversion < 2008000101) {
        $result = $result && set_field_select('quiz', 'preferredmodel', 'deferredfeedback',
                'optionflags = 0');
        $result = $result && set_field_select('quiz', 'preferredmodel', 'adaptive',
                'optionflags <> 0 AND penaltyscheme <> 0');
        $result = $result && set_field_select('quiz', 'preferredmodel', 'adaptivenopenalty',
                'optionflags <> 0 AND penaltyscheme = 0');

        $result = $result && set_config('quiz_preferredmodel', 'deferredfeedback');
        $result = $result && set_config('quiz_fix_preferredmodel', 0);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000101, 'quiz');
    }

    // Add a not-NULL constraint to the preferredmodel field now that it is populated.
    if ($result && $oldversion < 2008000102) {
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('preferredmodel');
        $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null, 'timeclose');

        $result = $result && change_field_notnull($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000102, 'quiz');
    }

    // Drop the old optionflags field.
    if ($result && $oldversion < 2008000103) {
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('optionflags');
        $result = $result && drop_field($table, $field);

        $result = $result && unset_config('quiz_optionflags');
        $result = $result && unset_config('quiz_fix_optionflags');

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000103, 'quiz');
    }

    // Drop the old penaltyscheme field.
    if ($result && $oldversion < 2008000104) {
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('penaltyscheme');
        $result = $result && drop_field($table, $field);

        $result = $result && unset_config('quiz_penaltyscheme');
        $result = $result && unset_config('quiz_fix_penaltyscheme');

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000104, 'quiz');
    }

//    // Drop the old attemptonlast field.
//    if ($result && $oldversion < 2008000105) {
//        $table = new XMLDBTable('quiz');
//        $field = new XMLDBField('attemptonlast');
//        $result = $result && drop_field($table, $field);
//
//        $result = $result && unset_config('quiz_attemptonlast');
//        $result = $result && unset_config('quiz_fix_attemptonlast');
//
//        // quiz savepoint reached
//        upgrade_mod_savepoint($result, 2008000105, 'quiz');
//    }

    // Actually, we don't want to drop the old attemptonlast field. Put it back if it was dropped.
    if ($result && $oldversion < 2008000106) {
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('attemptonlast');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null, null, '0', 'attempts');

        if (!field_exists($table, $field)) {
            $result = $result && add_field($table, $field);        $table = new XMLDBTable('quiz');
            $result = $result && set_config('quiz_attemptonlast', 0);
            $result = $result && set_config('quiz_fix_attemptonlast', 1);
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000106, 'quiz');
    }

    // Convert question_attempt_steps.state from int to char.
    if ($result && $oldversion < 2008000107) {
        $table = new XMLDBTable('question_attempt_steps');
        $field = new XMLDBField('state');
        $field->setAttributes(XMLDB_TYPE_CHAR, '13', null, XMLDB_NOTNULL, null, null, null, null, 'sequencenumber');
        $result = $result && change_field_type($table, $field);

        $result = $result && set_field('question_attempt_steps', 'state', 'todo', 'state', 1);
        $result = $result && set_field('question_attempt_steps', 'state', 'complete', 'state', 2);
        $result = $result && set_field('question_attempt_steps', 'state', 'needsgrading', 'state', 16);
        $result = $result && set_field('question_attempt_steps', 'state', 'finished', 'state', 17);
        $result = $result && set_field('question_attempt_steps', 'state', 'gaveup', 'state', 18);
        $result = $result && set_field('question_attempt_steps', 'state', 'gradedwrong', 'state', 24);
        $result = $result && set_field('question_attempt_steps', 'state', 'gradedpartial', 'state', 25);
        $result = $result && set_field('question_attempt_steps', 'state', 'gradedright', 'state', 26);
        $result = $result && set_field('question_attempt_steps', 'state', 'manfinished', 'state', 49);
        $result = $result && set_field('question_attempt_steps', 'state', 'mangaveup', 'state', 50);
        $result = $result && set_field('question_attempt_steps', 'state', 'mangrwrong', 'state', 56);
        $result = $result && set_field('question_attempt_steps', 'state', 'mangrpartial', 'state', 57);
        $result = $result && set_field('question_attempt_steps', 'state', 'mangrright', 'state', 58);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000107, 'quiz');
    }

    if ($result && $oldversion < 2008000108) {

    /// Define table quiz_reports to be created
        $table = new XMLDBTable('quiz_reports');

    /// Adding fields to table quiz_reports
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);
        $table->addFieldInfo('displayorder', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('lastcron', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('cron', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('capability', XMLDB_TYPE_CHAR, '255', null, null, null, null, null, null);

    /// Adding keys to table quiz_reports
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

    /// Launch create table for quiz_reports
        $result = $result && create_table($table);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000108, 'quiz');
    }

    if ($result && $oldversion < 2008000110) {
        // Insert information about the default reports into the table.

        $reporttoinsert = new stdClass;
        $reporttoinsert->name = 'overview';
        $reporttoinsert->displayorder = 10000;
        $result = $result && insert_record('quiz_reports', $reporttoinsert);

        $reporttoinsert = new stdClass;
        $reporttoinsert->name = 'responses';
        $reporttoinsert->displayorder = 9000;
        $result = $result && insert_record('quiz_reports', $reporttoinsert);

        $reporttoinsert = new stdClass;
        $reporttoinsert->name = 'grading';
        $reporttoinsert->displayorder = 6000;
        $result = $result && insert_record('quiz_reports', $reporttoinsert);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000110, 'quiz');
    }

    if ($result && $oldversion < 2008000111) {

        // Changing nullability of field sumgrades on table quiz_attempts to null
        $table = new XMLDBTable('quiz_attempts');
        $field = new XMLDBField('sumgrades');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '10, 5', null, null, null, null, null, null, 'attempt');

        // Launch change of nullability for field sumgrades
        $result = $result && change_field_notnull($table, $field);

        // Launch change of default for field sumgrades
        $result = $result && change_field_default($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000111, 'quiz');
    }

    if ($result && $oldversion < 2008000112) {

        // Chage im vars from !... to -... for validity reasons.
        $result = $result && execute_sql("
            UPDATE {$CFG->prefix}question_attempt_step_data
            SET name = " . sql_concat("'-'", 'substring(name FROM 2)') . "
            WHERE name LIKE '!%'
        ");

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000112, 'quiz');
    }

    if ($result && $oldversion < 2008000113) {

        // Rename field preferredmodel on table question_usages to preferredbehaviour.
        $table = new XMLDBTable('question_usages');
        $field = new XMLDBField('preferredmodel');
        $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null, 'owningplugin');

        // Launch rename field preferredbehaviour
        $result = $result && rename_field($table, $field, 'preferredbehaviour');

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000113, 'quiz');
    }

    if ($result && $oldversion < 2008000114) {

        // Rename field interactionmodel on table question_attempts to behaviour.
        $table = new XMLDBTable('question_attempts_new');
        $field = new XMLDBField('interactionmodel');
        $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null, 'owningplugin');

        // Launch rename field preferredbehaviour
        $result = $result && rename_field($table, $field, 'behaviour');

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000114, 'quiz');
    }

    if ($result && $oldversion < 2008000115) {

        // Rename field preferredmodel on table quiz to preferredbehaviour
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('preferredmodel');
        $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null, 'owningplugin');

        // Launch rename field preferredbehaviour
        $result = $result && rename_field($table, $field, 'preferredbehaviour');

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000115, 'quiz');
    }

    if ($result && $oldversion < 2008000116) {

        // Rename the corresponding config variable.
        set_config('quiz_preferredbehaviour', $CFG->quiz_preferredmodel);
        set_config('quiz_fix_preferredbehaviour', $CFG->quiz_fix_preferredmodel);
        unset_config('quiz_preferredmodel');
        unset_config('quiz_fix_preferredmodel');

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000116, 'quiz');
    }

    if ($result && $oldversion < 2008000117) {

        // Changing the default of field penalty on table question to 0.3333333
        $table = new XMLDBTable('question');
        $field = new XMLDBField('penalty');
        $field->setAttributes(XMLDB_TYPE_FLOAT, null, null, XMLDB_NOTNULL, null, null, null, '0.3333333', 'defaultgrade');

        // Launch change of default for field penalty
        $result = $result && change_field_default($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000117, 'quiz');
    }

    commit_sql();

    return $result;
}

