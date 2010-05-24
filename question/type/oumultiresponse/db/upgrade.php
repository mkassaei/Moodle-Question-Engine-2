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
 * Upgrade code for the OU multiple response question type.
 *
 * @package qtype_oumultiresponse
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * OU multiple response upgrade script.
 * @param integer $oldversion the version we are upgrading from.
 */
function xmldb_qtype_oumultiresponse_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;

    if ($result && $oldversion < 2010052000) {

    /// Rename field correctresponsesfeedback on table question_oumultiresponse to shownumcorrect
        $table = new XMLDBTable('question_oumultiresponse');
        $field = new XMLDBField('correctresponsesfeedback');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'incorrectfeedback');

    /// Launch rename field shownumcorrect
        $result = $result && rename_field($table, $field, 'shownumcorrect');
    }

    if ($result && $oldversion < 2010052001) {

        // Change the answernumbering field so that iii and IIII are disinguished
        // by more than case, otherwise it won't work on MySQL.
        $result = $result && set_field('question_oumultiresponse', 'answernumbering', 'IIII',
                'answernumbering', 'III');
    }

    return $result;
}
