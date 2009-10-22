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
 * Code for loading and saving quiz attempts to and from the database.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * This class controls the loading and saving of question engine data to and from
 * the database.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_engine_data_mapper {
    public function insert_question_attempt(question_attempt $qa) {
        $record = new stdClass;
        $record->questionusageid = $qa->get_usage_id();
        $record->numberinusage = $qa->get_number_in_usage();
        $record->interactionmodel = $qa->get_im_name();
        $record->questionid = $qa->get_question()->id;
        $record->maxmark = $qa->get_max_mark();
        $record->minfraction = $qa->get_min_fraction();
        $record->flagged = $qa->is_flagged();
        $record->questionsummary = null;
        $record->rightanswer = null;
        $record->responsesummary = null;
        $record->id = insert_record('question_attempt', $record);

        foreach ($qa->get_step_iterator() as $seq => $step) {
            $this->insert_question_attempt_step($step, $record->id);
        }
    }

    public function insert_question_attempt_step(question_attempt_step $step, $questionattemptid, $seq) {
        $record = new stdClass;
        $record->questionattemptid = $questionattemptid;
        $record->sequencenumber = $seq;
        $record->state = $step->get_state();
        $record->fraction = $step->get_fraction();
        $record->timecreated = $step->get_timecreated();
        $record->userid = $step->get_user_id();

        $record->id = insert_record('question_attempt_step', $record);

        foreach ($step->get_all_data() as $name => $value) {
            $data = new stdClass;
            $data->attemptstepid = $record->id;
            $data->name = $name;
            $data->value = $value;
            insert_record('question_attempt_step_data', $data, false);
        }
    }

    public function load_question_attempt_step($stepid) {
        global $CFG;
        $records = get_records_sql("
SELECT
    qasd.id,
    qas.id AS attemptstepid,
    qas.questionattemptid,
    qas.sequencenumber,
    qas.state,
    qas.fraction,
    qas.timecreated,
    qas.userid,
    qasd.name,
    qasd.value

FROM {$CFG->prefix}question_attempt_step qas
LEFT JOIN {$CFG->prefix}question_attempt_step_data qasd ON qasd.attemptstepid = qas.id

WHERE
    qas.id = $stepid
        ");

        return question_attempt_step::load_from_records($records, $stepid);
    }
}

/**
 * Implementation of the unit of work pattern for the question engine.
 *
 * See http://martinfowler.com/eaaCatalog/unitOfWork.html
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_engine_unit_of_work {
    protected $loadedstates = array();

}