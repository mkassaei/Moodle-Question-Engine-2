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
        define('QUIZ_FEEDBACK_MASK', 4*0x1041);
        define('QUIZ_OVERALLFEEDBACK_MASK', 1*0x4440000);
        define('QUIZ_REVIEW_IMMEDIATELY_MASK', 0x3c003f);
        define('QUIZ_REVIEW_OPEN_MASK', 0x3c00fc0);
        define('QUIZ_REVIEW_CLOSED_MASK', 0x3c03f000);

        // Adjust the quiz review options so that overall feedback is displayed whenever feedback is.
        $result = $result && execute_sql('UPDATE ' . $CFG->prefix . 'quiz SET review = ' .
                sql_bitor(sql_bitand('review', sql_bitnot(QUIZ_OVERALLFEEDBACK_MASK)),
                sql_bitor(sql_bitand('review', QUIZ_FEEDBACK_MASK & QUIZ_REVIEW_IMMEDIATELY_MASK) . ' * 65536',
                sql_bitor(sql_bitand('review', QUIZ_FEEDBACK_MASK & QUIZ_REVIEW_OPEN_MASK) . ' * 16384',
                          sql_bitand('review', QUIZ_FEEDBACK_MASK & QUIZ_REVIEW_CLOSED_MASK) . ' * 4096'))));

        // Same adjustment to the defaults for new quizzes.
        $result = $result && set_config('quiz_review', ($CFG->quiz_review & ~QUIZ_OVERALLFEEDBACK_MASK) |
                (($CFG->quiz_review & QUIZ_FEEDBACK_MASK & QUIZ_REVIEW_IMMEDIATELY_MASK) << 16) |
                (($CFG->quiz_review & QUIZ_FEEDBACK_MASK & QUIZ_REVIEW_OPEN_MASK) << 14) |
                (($CFG->quiz_review & QUIZ_FEEDBACK_MASK & QUIZ_REVIEW_CLOSED_MASK) << 12));
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
        if (table_exists($table)) {
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
        }

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
        $table = new XMLDBTable('question_attempt_step_data');
        if (table_exists($table)) {

            // Chage im vars from !... to -... for validity reasons.
            $result = $result && execute_sql("
                UPDATE {$CFG->prefix}question_attempt_step_data
                SET name = " . sql_concat("'-'", 'substring(name FROM 2)') . "
                WHERE name LIKE '!%'
            ");

        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000112, 'quiz');
    }

    if ($result && $oldversion < 2008000113) {

        // Rename field preferredmodel on table question_usages to preferredbehaviour.
        $table = new XMLDBTable('question_usages');
        if (table_exists($table)) {
            $field = new XMLDBField('preferredmodel');
            $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null, 'owningplugin');

            // Launch rename field preferredbehaviour
            $result = $result && rename_field($table, $field, 'preferredbehaviour');
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000113, 'quiz');
    }

    if ($result && $oldversion < 2008000114) {

        // Rename field interactionmodel on table question_attempts to behaviour.
        $table = new XMLDBTable('question_attempts_new');
        if (table_exists($table)) {
            $field = new XMLDBField('interactionmodel');
            $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null, 'owningplugin');

            // Launch rename field preferredbehaviour
            $result = $result && rename_field($table, $field, 'behaviour');
        }

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

// Update the quiz from the old single review column to seven new columns.

    if ($result && $oldversion < 2008000200) {

        // Define field reviewattempt to be added to quiz
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('reviewattempt');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'review');

        // Launch add field reviewattempt
        $result = $result && add_field($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000200, 'quiz');
    }

    if ($result && $oldversion < 2008000201) {

        // Define field reviewattempt to be added to quiz
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('reviewcorrectness');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'reviewattempt');

        // Launch add field reviewattempt
        $result = $result && add_field($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000201, 'quiz');
    }

    if ($result && $oldversion < 2008000202) {

        // Define field reviewattempt to be added to quiz
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('reviewmarks');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'reviewcorrectness');

        // Launch add field reviewattempt
        $result = $result && add_field($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000202, 'quiz');
    }

    if ($result && $oldversion < 2008000203) {

        // Define field reviewattempt to be added to quiz
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('reviewspecificfeedback');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'reviewmarks');

        // Launch add field reviewattempt
        $result = $result && add_field($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000203, 'quiz');
    }

    if ($result && $oldversion < 2008000204) {

        // Define field reviewattempt to be added to quiz
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('reviewgeneralfeedback');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'reviewspecificfeedback');

        // Launch add field reviewattempt
        $result = $result && add_field($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000204, 'quiz');
    }

    if ($result && $oldversion < 2008000205) {

        // Define field reviewattempt to be added to quiz
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('reviewrightanswer');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'reviewgeneralfeedback');

        // Launch add field reviewattempt
        $result = $result && add_field($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000205, 'quiz');
    }

    if ($result && $oldversion < 2008000206) {

        // Define field reviewattempt to be added to quiz
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('reviewoverallfeedback');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '6', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'reviewrightanswer');

        // Launch add field reviewattempt
        $result = $result && add_field($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000206, 'quiz');
    }

    define('QUIZ_NEW_DURING',            0x10000);
    define('QUIZ_NEW_IMMEDIATELY_AFTER', 0x01000);
    define('QUIZ_NEW_LATER_WHILE_OPEN',  0x00100);
    define('QUIZ_NEW_AFTER_CLOSE',       0x00010);

    define('QUIZ_OLD_IMMEDIATELY', 0x3c003f);
    define('QUIZ_OLD_OPEN',        0x3c00fc0);
    define('QUIZ_OLD_CLOSED',      0x3c03f000);

    define('QUIZ_OLD_RESPONSES',       1*0x1041); // Show responses
    define('QUIZ_OLD_SCORES',          2*0x1041); // Show scores
    define('QUIZ_OLD_FEEDBACK',        4*0x1041); // Show question feedback
    define('QUIZ_OLD_ANSWERS',         8*0x1041); // Show correct answers
    define('QUIZ_OLD_SOLUTIONS',      16*0x1041); // Show solutions
    define('QUIZ_OLD_GENERALFEEDBACK',32*0x1041); // Show question general feedback
    define('QUIZ_OLD_OVERALLFEEDBACK', 1*0x4440000); // Show quiz overall feedback

    // Copy the old review settings
    if ($result && $oldversion < 2008000210) {
        $result = $result && execute_sql("
            UPDATE {$CFG->prefix}quiz
            SET reviewattempt = " . sql_bitor(sql_bitor(
                    QUIZ_NEW_DURING,
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_RESPONSES) .
                        ' <> 0 THEN ' . QUIZ_NEW_IMMEDIATELY_AFTER . ' ELSE 0 END'), sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_OPEN & QUIZ_OLD_RESPONSES) .
                        ' <> 0 THEN ' . QUIZ_NEW_LATER_WHILE_OPEN . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_CLOSED & QUIZ_OLD_RESPONSES) .
                        ' <> 0 THEN ' . QUIZ_NEW_AFTER_CLOSE . ' ELSE 0 END')) . "
        ");

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000210, 'quiz');
    }

    if ($result && $oldversion < 2008000211) {
        $result = $result && execute_sql("
            UPDATE {$CFG->prefix}quiz
            SET reviewcorrectness = " . sql_bitor(sql_bitor(
                    QUIZ_NEW_DURING,
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_SCORES) .
                        ' <> 0 THEN ' . QUIZ_NEW_IMMEDIATELY_AFTER . ' ELSE 0 END'), sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_OPEN & QUIZ_OLD_SCORES) .
                        ' <> 0 THEN ' . QUIZ_NEW_LATER_WHILE_OPEN . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_CLOSED & QUIZ_OLD_SCORES) .
                        ' <> 0 THEN ' . QUIZ_NEW_AFTER_CLOSE . ' ELSE 0 END')) . "
        ");

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000211, 'quiz');
    }

    if ($result && $oldversion < 2008000212) {
        $result = $result && execute_sql("
            UPDATE {$CFG->prefix}quiz
            SET reviewmarks = " . sql_bitor(sql_bitor(
                    QUIZ_NEW_DURING,
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_SCORES) .
                        ' <> 0 THEN ' . QUIZ_NEW_IMMEDIATELY_AFTER . ' ELSE 0 END'), sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_OPEN & QUIZ_OLD_SCORES) .
                        ' <> 0 THEN ' . QUIZ_NEW_LATER_WHILE_OPEN . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_CLOSED & QUIZ_OLD_SCORES) .
                        ' <> 0 THEN ' . QUIZ_NEW_AFTER_CLOSE . ' ELSE 0 END')) . "
        ");

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000212, 'quiz');
    }

    if ($result && $oldversion < 2008000213) {
        $result = $result && execute_sql("
            UPDATE {$CFG->prefix}quiz
            SET reviewspecificfeedback = " . sql_bitor(sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_FEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_DURING . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_FEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_IMMEDIATELY_AFTER . ' ELSE 0 END'), sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_OPEN & QUIZ_OLD_FEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_LATER_WHILE_OPEN . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_CLOSED & QUIZ_OLD_FEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_AFTER_CLOSE . ' ELSE 0 END')) . "
        ");

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000213, 'quiz');
    }

    if ($result && $oldversion < 2008000214) {
        $result = $result && execute_sql("
            UPDATE {$CFG->prefix}quiz
            SET reviewgeneralfeedback = " . sql_bitor(sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_GENERALFEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_DURING . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_GENERALFEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_IMMEDIATELY_AFTER . ' ELSE 0 END'), sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_OPEN & QUIZ_OLD_GENERALFEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_LATER_WHILE_OPEN . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_CLOSED & QUIZ_OLD_GENERALFEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_AFTER_CLOSE . ' ELSE 0 END')) . "
        ");

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000214, 'quiz');
    }

    if ($result && $oldversion < 2008000215) {
        $result = $result && execute_sql("
            UPDATE {$CFG->prefix}quiz
            SET reviewrightanswer = " . sql_bitor(sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_ANSWERS) .
                        ' <> 0 THEN ' . QUIZ_NEW_DURING . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_ANSWERS) .
                        ' <> 0 THEN ' . QUIZ_NEW_IMMEDIATELY_AFTER . ' ELSE 0 END'), sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_OPEN & QUIZ_OLD_ANSWERS) .
                        ' <> 0 THEN ' . QUIZ_NEW_LATER_WHILE_OPEN . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_CLOSED & QUIZ_OLD_ANSWERS) .
                        ' <> 0 THEN ' . QUIZ_NEW_AFTER_CLOSE . ' ELSE 0 END')) . "
        ");

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000215, 'quiz');
    }

    if ($result && $oldversion < 2008000216) {
        $result = $result && execute_sql("
            UPDATE {$CFG->prefix}quiz
            SET reviewoverallfeedback = " . sql_bitor(sql_bitor(
                    0,
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_OVERALLFEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_IMMEDIATELY_AFTER . ' ELSE 0 END'), sql_bitor(
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_OPEN & QUIZ_OLD_OVERALLFEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_LATER_WHILE_OPEN . ' ELSE 0 END',
                    'CASE WHEN ' . sql_bitand('review', QUIZ_OLD_CLOSED & QUIZ_OLD_OVERALLFEEDBACK) .
                        ' <> 0 THEN ' . QUIZ_NEW_AFTER_CLOSE . ' ELSE 0 END')) . "
        ");

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000216, 'quiz');
    }

    // And, do the same for the defaults
    if ($result && $oldversion < 2008000217) {
        if (empty($CFG->quiz_review)) {
            $CFG->quiz_review = 0;
        }

        set_config('quiz_reviewattempt',
                QUIZ_NEW_DURING |
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_RESPONSES ? QUIZ_NEW_IMMEDIATELY_AFTER : 0) |
                ($CFG->quiz_review & QUIZ_OLD_OPEN & QUIZ_OLD_RESPONSES ? QUIZ_NEW_LATER_WHILE_OPEN : 0) |
                ($CFG->quiz_review & QUIZ_OLD_CLOSED & QUIZ_OLD_RESPONSES ? QUIZ_NEW_AFTER_CLOSE : 0));

        set_config('quiz_reviewcorrectness',
                QUIZ_NEW_DURING |
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_SCORES ? QUIZ_NEW_IMMEDIATELY_AFTER : 0) |
                ($CFG->quiz_review & QUIZ_OLD_OPEN & QUIZ_OLD_SCORES ? QUIZ_NEW_LATER_WHILE_OPEN : 0) |
                ($CFG->quiz_review & QUIZ_OLD_CLOSED & QUIZ_OLD_SCORES ? QUIZ_NEW_AFTER_CLOSE : 0));

        set_config('quiz_reviewmarks',
                QUIZ_NEW_DURING |
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_SCORES ? QUIZ_NEW_IMMEDIATELY_AFTER : 0) |
                ($CFG->quiz_review & QUIZ_OLD_OPEN & QUIZ_OLD_SCORES ? QUIZ_NEW_LATER_WHILE_OPEN : 0) |
                ($CFG->quiz_review & QUIZ_OLD_CLOSED & QUIZ_OLD_SCORES ? QUIZ_NEW_AFTER_CLOSE : 0));

        set_config('quiz_reviewspecificfeedback',
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_FEEDBACK ? QUIZ_NEW_DURING : 0) |
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_FEEDBACK ? QUIZ_NEW_IMMEDIATELY_AFTER : 0) |
                ($CFG->quiz_review & QUIZ_OLD_OPEN & QUIZ_OLD_FEEDBACK ? QUIZ_NEW_LATER_WHILE_OPEN : 0) |
                ($CFG->quiz_review & QUIZ_OLD_CLOSED & QUIZ_OLD_FEEDBACK ? QUIZ_NEW_AFTER_CLOSE : 0));

        set_config('quiz_reviewgeneralfeedback',
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_GENERALFEEDBACK ? QUIZ_NEW_DURING : 0) |
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_GENERALFEEDBACK ? QUIZ_NEW_IMMEDIATELY_AFTER : 0) |
                ($CFG->quiz_review & QUIZ_OLD_OPEN & QUIZ_OLD_GENERALFEEDBACK ? QUIZ_NEW_LATER_WHILE_OPEN : 0) |
                ($CFG->quiz_review & QUIZ_OLD_CLOSED & QUIZ_OLD_GENERALFEEDBACK ? QUIZ_NEW_AFTER_CLOSE : 0));

        set_config('quiz_reviewrightanswer',
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_ANSWERS ? QUIZ_NEW_DURING : 0) |
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_ANSWERS ? QUIZ_NEW_IMMEDIATELY_AFTER : 0) |
                ($CFG->quiz_review & QUIZ_OLD_OPEN & QUIZ_OLD_ANSWERS ? QUIZ_NEW_LATER_WHILE_OPEN : 0) |
                ($CFG->quiz_review & QUIZ_OLD_CLOSED & QUIZ_OLD_ANSWERS ? QUIZ_NEW_AFTER_CLOSE : 0));

        set_config('quiz_reviewoverallfeedback',
                0 |
                ($CFG->quiz_review & QUIZ_OLD_IMMEDIATELY & QUIZ_OLD_OVERALLFEEDBACK ? QUIZ_NEW_IMMEDIATELY_AFTER : 0) |
                ($CFG->quiz_review & QUIZ_OLD_OPEN & QUIZ_OLD_OVERALLFEEDBACK ? QUIZ_NEW_LATER_WHILE_OPEN : 0) |
                ($CFG->quiz_review & QUIZ_OLD_CLOSED & QUIZ_OLD_OVERALLFEEDBACK ? QUIZ_NEW_AFTER_CLOSE : 0));

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000217, 'quiz');
    }

    // Finally drop the old column
    if ($result && $oldversion < 2008000220) {
        // Define field review to be dropped from quiz
        $table = new XMLDBTable('quiz');
        $field = new XMLDBField('review');

        // Launch drop field review
        $result = $result && drop_field($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000220, 'quiz');
    }

    if ($result && $oldversion < 2008000221) {
        unset_config('quiz_review');

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000221, 'quiz');
    }

    if ($result && $oldversion < 2008000500) {

        // Rename field slot on table question_attempts_new to slot
        $table = new XMLDBTable('question_attempts_new');
        if (table_exists($table)) {
            $field = new XMLDBField('numberinusage');
            $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'questionusageid');

            // Launch rename field slot
            $result = $result && rename_field($table, $field, 'slot');
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000500, 'quiz');
    }

    if ($result && $oldversion < 2008000501) {

        // Rename field defaultgrade on table question to defaultmark
        $table = new XMLDBTable('question');
        $field = new XMLDBField('defaultgrade');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'generalfeedback');

        // Launch rename field defaultmark
        $result = $result && rename_field($table, $field, 'defaultmark');

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000501, 'quiz');
    }

    if ($result && $oldversion < 2008000502) {

        // Changing type of field defaultmark on table question to (12, 7)
        $table = new XMLDBTable('question');
        $field = new XMLDBField('defaultmark');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'generalfeedback');

        // Launch change of type for field defaultmark
        $result = $result && change_field_type($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000502, 'quiz');
    }

    if ($result && $oldversion < 2008000503) {

        // Changing type of field penalty on table question to (12, 7)
        $table = new XMLDBTable('question');
        $field = new XMLDBField('penalty');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, null, null, '0.3333333', 'defaultmark');

        // Launch change of type for field penalty
        $result = $result && change_field_type($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000503, 'quiz');
    }

    if ($result && $oldversion < 2008000504) {

        // Changing type of field fraction on table question_answers to (12, 7)
        $table = new XMLDBTable('question_answers');
        $field = new XMLDBField('fraction');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, null, null, '0.3333333', 'defaultmark');

        // Launch change of type for field penalty
        $result = $result && change_field_type($table, $field);

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000504, 'quiz');
    }

    if ($result && $oldversion < 2008000505) {
        // If we already have the new tables, update their structure, and drop
        // any old-style tables that are around. (If we don't have the new tables,
        // we will create them in a minute.)

        $table = new XMLDBTable('question_usages');
        if (table_exists($table)) {
            // Rename field owningplugin on table question_usages to component
            $field = new XMLDBField('owningplugin');
            $field->setAttributes(XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null, 'contextid');
            $result = $result && rename_field($table, $field, 'component');

            // Drop any old tables.
            $table = new XMLDBTable('question_attempts');
            if (table_exists($table)) {
                drop_table($table);
            }
            $table = new XMLDBTable('question_states');
            if (table_exists($table)) {
                drop_table($table);
            }
            $table = new XMLDBTable('question_sessions');
            if (table_exists($table)) {
                drop_table($table);
            }

        } else {
            // Rename one of the old tables whose name clashes with a new table.
            $table = new XMLDBTable('question_attempts');
            if (table_exists($table)) {
                $result = $result && rename_table($table, 'question_attempts_old');
            }
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000505, 'quiz');
    }

    if ($result && $oldversion < 2008000506) {
        // If we have previously installed the question_attempts_new table,
        // rename it question_attempts.

        $table = new XMLDBTable('question_attempts_new');
        if (table_exists($table)) {
            $result = $result && rename_table($table, 'question_attempts');
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000506, 'quiz');
    }

    if ($result && $oldversion < 2008000507) {
        $table = new XMLDBTable('question_attempts_old');
        if (table_exists($table)) {
            // Rename to create the question_usages table.
            $result = $result && rename_table($table, 'question_usages');

            // Rename the modulename field to component ...
            $table = new XMLDBTable('question_usages');
            $field = new XMLDBField('modulename');
            $field->setAttributes(XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null, 'contextid');
            $result = $result && rename_field($table, $field, 'component');

            // ... and update its contents.
            $result = $result && set_field('question_usages', 'component', 'mod_quiz', 'component', 'quiz');

            // Add the contextid field.
            $field = new XMLDBField('contextid');
            $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'id');
            $result = $result && add_field($table, $field);

            // And populate it.
            $quizmoduleid = get_field('modules', 'id', 'name', 'quiz');
            $result = $result && execute_sql("
                UPDATE {$CFG->prefix}question_usages SET contextid = (
                    SELECT ctx.id
                    FROM {$CFG->prefix}context ctx
                    JOIN {$CFG->prefix}course_modules cm ON cm.id = ctx.instanceid AND cm.module = $quizmoduleid
                    JOIN {$CFG->prefix}quiz_attempts quiza ON quiza.quiz = cm.instance
                    WHERE ctx.contextlevel = " . CONTEXT_MODULE . "
                    AND quiza.uniqueid = {$CFG->prefix}question_usages.id
                )
            ");

            // Then make it NOT NULL.
            $field = new XMLDBField('contextid');
            $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null, 'id');
            $result = $result && change_field_notnull($table, $field);

            // Add the preferredbehaviour column. Populate it with '' for now.
            // We will fill in the appropriate behaviour name when updating all
            // the rest of the attempt data.
            $field = new XMLDBField('preferredbehaviour');
            $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, '', 'component');
            $result = $result && add_field($table, $field);

            // Then remove the default value, now the column is populated.
            $field = new XMLDBField('preferredbehaviour');
            $field->setAttributes(XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null, 'component');
            $result = $result && change_field_default($table, $field);
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000507, 'quiz');
    }

    if ($result && $oldversion < 2008000508) {

        // Define table question_attempts to be created
        $table = new XMLDBTable('question_attempts');
        if (!table_exists($table)) {

            // Adding fields to table question_attempts
            $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
            $table->addFieldInfo('questionusageid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('slot', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('behaviour', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('questionid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('maxmark', XMLDB_TYPE_NUMBER, '12, 7', null, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('minfraction', XMLDB_TYPE_NUMBER, '12, 7', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('flagged', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
            $table->addFieldInfo('questionsummary', XMLDB_TYPE_TEXT, 'small', null, null, null, null, null, null);
            $table->addFieldInfo('rightanswer', XMLDB_TYPE_TEXT, 'small', null, null, null, null, null, null);
            $table->addFieldInfo('responsesummary', XMLDB_TYPE_TEXT, 'small', null, null, null, null, null, null);
            $table->addFieldInfo('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);

            // Adding keys to table question_attempts
            $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->addKeyInfo('questionid', XMLDB_KEY_FOREIGN, array('questionid'), 'question', array('id'));
            $table->addKeyInfo('questionusageid', XMLDB_KEY_FOREIGN, array('questionusageid'), 'question_usages', array('id'));

            // Adding indexes to table question_attempts
            $table->addIndexInfo('questionusageid-numberinusage', XMLDB_INDEX_UNIQUE, array('questionusageid', 'slot'));

            // Launch create table for question_attempts
            $result = $result && create_table($table);
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000508, 'quiz');
    }

    if ($result && $oldversion < 2008000509) {

        // Define table question_attempt_steps to be created
        $table = new XMLDBTable('question_attempt_steps');
        if (!table_exists($table)) {

            // Adding fields to table question_attempt_steps
            $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
            $table->addFieldInfo('questionattemptid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('sequencenumber', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('state', XMLDB_TYPE_CHAR, '13', null, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('fraction', XMLDB_TYPE_NUMBER, '12, 7', null, null, null, null, null, null);
            $table->addFieldInfo('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);

            // Adding keys to table question_attempt_steps
            $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->addKeyInfo('questionattemptid', XMLDB_KEY_FOREIGN, array('questionattemptid'), 'question_attempts_new', array('id'));
            $table->addKeyInfo('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));

            // Adding indexes to table question_attempt_steps
            $table->addIndexInfo('questionattemptid-sequencenumber', XMLDB_INDEX_UNIQUE, array('questionattemptid', 'sequencenumber'));

            // Launch create table for question_attempt_steps
            $result = $result && create_table($table);
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000509, 'quiz');
    }

    if ($result && $oldversion < 2008000510) {

        // Define table question_attempt_step_data to be created
        $table = new XMLDBTable('question_attempt_step_data');
        if (!table_exists($table)) {

            // Adding fields to table question_attempt_step_data
            $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
            $table->addFieldInfo('attemptstepid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('name', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null, null, null);
            $table->addFieldInfo('value', XMLDB_TYPE_TEXT, 'small', null, null, null, null, null, null);

            // Adding keys to table question_attempt_step_data
            $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->addKeyInfo('attemptstepid', XMLDB_KEY_FOREIGN, array('attemptstepid'), 'question_attempt_steps', array('id'));

            // Adding indexes to table question_attempt_step_data
            $table->addIndexInfo('attemptstepid-name', XMLDB_INDEX_UNIQUE, array('attemptstepid', 'name'));

            // Launch create table for question_attempt_step_data
            $result = $result && create_table($table);
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000510, 'quiz');
    }

    commit_sql();

    if ($result) {
        set_field('modules', 'version', 2008000510, 'name', 'quiz');
    }

    if ($result && $oldversion < 2008000511) {
        $table = new XMLDBTable('question_states');
        if (table_exists($table)) {
            // Now update all the old attempt data.
            require_once($CFG->dirroot . '/question/engine/upgradefromoldqe/upgrade.php');
            $upgrader = new question_engine_attempt_upgrader();
            $result = $result && $upgrader->convert_all_quiz_attempts();
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000511, 'quiz');
    }

    begin_sql();

    if ($result && $oldversion < 2008000512) {

        // Define table question_states to be dropped
        $table = new XMLDBTable('question_states');
        if (table_exists($table)) {

            // Launch drop table for question_states
            $result = $result && drop_table($table);
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000512, 'quiz');
    }

    if ($result && $oldversion < 2008000513) {

        // Define table question_states to be dropped
        $table = new XMLDBTable('question_states');
        if (table_exists($table)) {

            // Launch drop table for question_states
            $result = $result && drop_table($table);
        }

        // quiz savepoint reached
        upgrade_mod_savepoint($result, 2008000513, 'quiz');
    }

    commit_sql();

    return $result;
}

