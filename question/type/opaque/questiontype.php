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
 * The questiontype class for the Opaque question type.
 *
 * @package qtype_opaque
 * @copyright &copy; 2006 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/locallib.php');

/**
 * The Opaque question type.
 */
class qtype_opaque extends question_type {

    public function can_analyse_responses() {
        return false;
    }

    function extra_question_fields() {
        return array('question_opaque', 'engineid', 'remoteid', 'remoteversion');
    }

    function save_question($question, $form, $course) {
        $form->questiontext = '';
        $form->questiontextformat = FORMAT_MOODLE;
        $form->unlimited = 0;
        $form->penalty = 0;
        return parent::save_question($question, $form, $course);
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        // TODO
        $question->engineid = $questiondata->options->engineid;
        $question->remoteid = $questiondata->options->remoteid;
        $question->remoteversion = $questiondata->options->remoteversion;
    }

    public function get_random_guess_score($questiondata) {
        return null;
    }

    function export_to_xml($question, $format, $extra=null) {
        $expout = '';
        $expout .= '    <remoteid>' . $question->options->remoteid . "</remoteid>\n";
        $expout .= '    <remoteversion>' . $question->options->remoteversion . "</remoteversion>\n";
        $expout .= "    <engine>\n";
        $engine = load_engine_def($question->options->engineid);
        $expout .= '      <name>' . $format->writetext($engine->name, 0, true) . "</name>\n";
        $expout .= '      <passkey>' . $format->writetext($engine->passkey, 0, true) . "</passkey>\n";
        foreach ($engine->questionengines as $qe) {
            $expout .= '      <qe>' . $format->writetext($qe, 0, true) . "</qe>\n";
        }
        foreach ($engine->questionbanks as $qb) {
            $expout .= '      <qb>' . $format->writetext($qb, 0, true) . "</qb>\n";
        }
        $expout .= "    </engine>\n";
        return $expout;
    }

    function import_from_xml( $data, $question, $format, $extra=null ) {
        if (!isset($data['@']['type']) || $data['@']['type'] != 'opaque') {
            return false;
        }

        $question = $format->import_headers($data);
        $question->qtype = 'opaque';
        $question->remoteid = $format->getpath($data, array('#','remoteid',0,'#'), '', false,
                get_string('missingremoteidinimport', 'qtype_opaque'));
        $question->remoteversion = $format->getpath($data, array('#','remoteversion',0,'#'), '', false,
                get_string('missingremoteversioninimport', 'qtype_opaque'));

        // Engine bit.
        $strerror = get_string('missingenginedetailsinimport', 'qtype_opaque'); 
        if (!isset($data['#']['engine'][0])) {
             $format->error($strerror);
        }
        $enginedata = $data['#']['engine'][0];
        $engine = new stdClass();
        $engine->name = $format->import_text($enginedata['#']['name'][0]['#']['text']);
        $engine->passkey = $format->import_text($enginedata['#']['passkey'][0]['#']['text']);
        $engine->questionengines = array();
        $engine->questionbanks = array();
        if (isset($enginedata['#']['qe'])) {
            foreach ($enginedata['#']['qe'] as $qedata) {
                $engine->questionengines[] = $format->import_text($qedata['#']['text']);
            }
        }
        if (isset($enginedata['#']['qb'])) {
            foreach ($enginedata['#']['qb'] as $qbdata) {
                $engine->questionbanks[] = $format->import_text($qbdata['#']['text']);
            }
        }
        $question->engineid = find_or_create_engineid($engine);
        return $question;
    }

    /**
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    function backup($bf,$preferences,$question,$level=6) {
        // Load required data.
        $opaqueoptions = get_record('question_opaque', 'questionid', $question);
        if (!$opaqueoptions) {
            return false;
        }
        $engine = load_engine_def($opaqueoptions->engineid);
        if (is_string($engine)) {
            return false;
        }

        // Start Opaque-specific bit.
        $status = true;
        $status = $status && fwrite($bf, start_tag("OPAQUE", $level, true));

        // Engine info.
        $status = $status && fwrite($bf, start_tag("OPAQUE_ENGINE", $level + 1, true));
        $status = $status && fwrite($bf, full_tag("NAME", $level + 2, false, $engine->name));
        $status = $status && fwrite($bf, full_tag("PASSKEY", $level + 2, false, $engine->passkey));
        foreach ($engine->questionengines as $qe) {
            $status = $status && fwrite($bf, full_tag("QE", $level + 2, false, $qe));
        }
        foreach ($engine->questionbanks as $qb) {
            $status = $status && fwrite($bf, full_tag("QB", $level + 2, false, $qb));
        }
        $status = $status && fwrite($bf, end_tag("OPAQUE_ENGINE", $level + 1, true));
        
        // Other settings.
        $status = $status && fwrite($bf, full_tag("REMOTEID", $level + 1, false, $opaqueoptions->remoteid));
        $status = $status && fwrite($bf, full_tag("REMOTEVERSION", $level + 1, false, $opaqueoptions->remoteversion));

        // Finish up.
        $status = $status && fwrite($bf, end_tag("OPAQUE", $level, true));
        return $status;
    }

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    function restore($old_question_id,$new_question_id,$info,$restore) {
        $status = true;

        // Check the data is there.
        if (!isset($info['#']['OPAQUE'][0])) {
            return false;
        }
        $opaqueinfo = $info['#']['OPAQUE'][0];
        if (!isset($opaqueinfo['#']['OPAQUE_ENGINE'][0])) {
            return false;
        }
        $engineinfo = $opaqueinfo['#']['OPAQUE_ENGINE'][0];

        // Read in the engine info, and create a new engien def. is necessary.
        $engine = new stdClass;
        $engine->name = backup_todb($engineinfo['#']['NAME']['0']['#']);
        $engine->passkey = backup_todb($engineinfo['#']['PASSKEY']['0']['#']);
        $engine->questionengines = array();
        $engine->questionbanks = array();
        if (isset($engineinfo['#']['QE'])) {
            foreach ($engineinfo['#']['QE'] as $qedata) {
                $engine->questionengines[] = backup_todb($qedata['#']);
            }
        }
        if (isset($engineinfo['#']['QB'])) {
            foreach ($engineinfo['#']['QB'] as $qbdata) {
                $engine->questionbanks[] = backup_todb($qbdata['#']);
            }
        }
        $engine->id = find_or_create_engineid($engine);

        // Read in the rest.
        $opaqueoptions = new stdClass;
        $opaqueoptions->questionid = $new_question_id;
        $opaqueoptions->engineid = $engine->id;
        $opaqueoptions->remoteid = backup_todb($opaqueinfo['#']['REMOTEID']['0']['#']);
        $opaqueoptions->remoteversion = backup_todb($opaqueinfo['#']['REMOTEVERSION']['0']['#']);
        $status = $status && insert_record('question_opaque', $opaqueoptions, false);

        return $status;
    }

}
