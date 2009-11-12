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
    public function insert_questions_usage_by_activity(question_usage_by_activity $quba) {
        $record = new stdClass;
        $record->contextid = $quba->get_owning_context()->id;
        $record->owningplugin = $quba->get_owning_plugin();
        $record->preferredmodel = $quba->get_preferred_interaction_model();

        $newid = insert_record('question_usages', $record);
        if (!$newid) {
            throw new Exception('Failed to save questions_usage_by_activity.');
        }
        $quba->set_id_from_database($newid);

        foreach ($quba->get_attempt_iterator() as $qa) {
            $this->insert_question_attempt($qa);
        }
    }

    public function insert_question_attempt(question_attempt $qa) {
        $record = new stdClass;
        $record->questionusageid = $qa->get_usage_id();
        $record->numberinusage = $qa->get_number_in_usage();
        $record->interactionmodel = $qa->get_interaction_model_name();
        $record->questionid = $qa->get_question()->id;
        $record->maxmark = $qa->get_max_mark();
        $record->minfraction = $qa->get_min_fraction();
        $record->flagged = $qa->is_flagged();
        $record->questionsummary = null;
        $record->rightanswer = null;
        $record->responsesummary = null;
        $record->timemodified = time();
        $record->id = insert_record('question_attempts_new', $record);
        if (!$record->id) {
            throw new Exception('Failed to save question_attempt ' . $qa->get_number_in_usage());
        }

        foreach ($qa->get_step_iterator() as $seq => $step) {
            $this->insert_question_attempt_step($step, $record->id, $seq);
        }
    }

    public function insert_question_attempt_step(question_attempt_step $step,
            $questionattemptid, $seq) {
        $record = new stdClass;
        $record->questionattemptid = $questionattemptid;
        $record->sequencenumber = $seq;
        $record->state = $step->get_state();
        $record->fraction = $step->get_fraction();
        $record->timecreated = $step->get_timecreated();
        $record->userid = $step->get_user_id();

        $record->id = insert_record('question_attempt_steps', $record);
        if (!$record->id) {
            throw new Exception('Failed to save question_attempt_step' . $seq .
                    ' for question attempt id ' . $questionattemptid);
        }

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

FROM {$CFG->prefix}question_attempt_steps qas
LEFT JOIN {$CFG->prefix}question_attempt_step_data qasd ON qasd.attemptstepid = qas.id

WHERE
    qas.id = $stepid
        ");

        if (!$records) {
            throw new Exception('Failed to load question_attempt_step ' . $stepid);
        }

        return question_attempt_step::load_from_records($records, $stepid);
    }

    public function load_question_attempt($questionattemptid) {
        global $CFG;
        $records = get_records_sql("
SELECT
    qasd.id,
    qa.id AS questionattemptid,
    qa.questionusageid,
    qa.numberinusage,
    qa.interactionmodel,
    qa.questionid,
    qa.maxmark,
    qa.minfraction,
    qa.flagged,
    qa.questionsummary,
    qa.rightanswer,
    qa.responsesummary,
    qa.timemodified,
    qas.id AS attemptstepid,
    qas.sequencenumber,
    qas.state,
    qas.fraction,
    qas.timecreated,
    qas.userid,
    qasd.name,
    qasd.value

FROM {$CFG->prefix}question_attempts_new qa
LEFT JOIN {$CFG->prefix}question_attempt_steps qas ON qas.questionattemptid = qa.id
LEFT JOIN {$CFG->prefix}question_attempt_step_data qasd ON qasd.attemptstepid = qas.id

WHERE
    qa.id = $questionattemptid

ORDER BY
    qas.sequencenumber
        ");

        if (!$records) {
            throw new Exception('Failed to load question_attempt ' . $questionattemptid);
        }

        return question_attempt::load_from_records($records, $questionattemptid);
    }

    public function load_questions_usage_by_activity($qubaid) {
        global $CFG;
        $records = get_records_sql("
SELECT
    qasd.id,
    quba.id AS qubaid,
    quba.contextid,
    quba.owningplugin,
    quba.preferredmodel,
    qa.id AS questionattemptid,
    qa.questionusageid,
    qa.numberinusage,
    qa.interactionmodel,
    qa.questionid,
    qa.maxmark,
    qa.minfraction,
    qa.flagged,
    qa.questionsummary,
    qa.rightanswer,
    qa.responsesummary,
    qa.timemodified,
    qas.id AS attemptstepid,
    qas.sequencenumber,
    qas.state,
    qas.fraction,
    qas.timecreated,
    qas.userid,
    qasd.name,
    qasd.value

FROM {$CFG->prefix}question_usages quba
LEFT JOIN {$CFG->prefix}question_attempts_new qa ON qa.questionusageid = quba.id
LEFT JOIN {$CFG->prefix}question_attempt_steps qas ON qas.questionattemptid = qa.id
LEFT JOIN {$CFG->prefix}question_attempt_step_data qasd ON qasd.attemptstepid = qas.id

WHERE
    quba.id = $qubaid

ORDER BY
    qa.numberinusage,
    qas.sequencenumber
    ");

        if (!$records) {
            throw new Exception('Failed to load questions_usage_by_activity ' . $qubaid);
        }

        return question_usage_by_activity::load_from_records($records, $qubaid);
    }

    public function update_questions_usage_by_activity(question_usage_by_activity $quba) {
        $record = new stdClass;
        $record->id = $quba->get_id();
        $record->contextid = $quba->get_owning_context()->id;
        $record->owningplugin = $quba->get_owning_plugin();
        $record->preferredmodel = $quba->get_preferred_interaction_model();

        if (!update_record('question_usages', $record)) {
            throw new Exception('Failed to update question_usage_by_activity ' . $record->id);
        }
    }

    public function update_question_attempt(question_attempt $qa) {
        $record = new stdClass;
        $record->id = $qa->get_database_id();
        $record->maxmark = $qa->get_max_mark();
        $record->minfraction = $qa->get_min_fraction();
        $record->flagged = $qa->is_flagged();
        $record->questionsummary = null;
        $record->rightanswer = null;
        $record->responsesummary = null;
        $record->timemodified = time();

        if (!update_record('question_attempts_new', $record)) {
            throw new Exception('Failed to update question_attempt ' . $record->id);
        }
    }

    public function delete_questions_usage_by_activity($qubaid) {
        global $CFG;
        delete_records_select('question_attempt_step_data', "attemptstepid IN (
                SELECT qas.id
                FROM {$CFG->prefix}question_attempts_new qa
                JOIN {$CFG->prefix}question_attempt_steps qas ON qas.questionattemptid = qa.id
                WHERE qa.questionusageid = $qubaid)");
        delete_records_select('question_attempt_steps', "questionattemptid IN (
                SELECT qa.id
                FROM {$CFG->prefix}question_attempts_new qa
                WHERE qa.questionusageid = $qubaid)");
        delete_records('question_attempts_new', 'questionusageid', $qubaid);
        delete_records('question_usages', 'id', $qubaid);
    }
}

/**
 * Implementation of the unit of work pattern for the question engine.
 *
 * See http://martinfowler.com/eaaCatalog/unitOfWork.html
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_engine_unit_of_work implements question_usage_observer {
    protected $quba;
    protected $modified = false;
    protected $attemptsmodified = array();
    protected $attemptsadded = array();
    protected $stepsadded = array();

    public function __construct($quba) {
        $this->quba = $quba;
    }

    public function notify_modified() {
        $this->modified = true;
    }

    public function notify_attempt_modified(question_attempt $qa) {
        $no = $qa->get_number_in_usage();
        if (!array_key_exists($no, $this->attemptsadded)) {
            $this->attemptsmodified[$no] = $qa;
        }
    }

    public function notify_attempt_added(question_attempt $qa) {
        $this->attemptsadded[$qa->get_number_in_usage()] = $qa;
    }

    public function notify_step_added(question_attempt_step $step, question_attempt $qa, $seq) {
        $no = $qa->get_number_in_usage();
        if (array_key_exists($no, $this->attemptsadded)) {
            return;
        }
        $this->stepsadded[] = array($step, $qa->get_database_id(), $seq);
    }

    public function save() {
        $dm = new question_engine_data_mapper();
        foreach ($this->stepsadded as $stepinfo) {
            list($step, $questionattemptid, $seq) = $stepinfo;
            $dm->insert_question_attempt_step($step, $questionattemptid, $seq);
        }
        foreach ($this->attemptsadded as $qa) {
            $dm->insert_question_attempt($qa);
        }
        foreach ($this->attemptsmodified as $qa) {
            $dm->update_question_attempt($qa);
        }
        if ($this->modified) {
            $dm->update_questions_usage_by_activity($this->quba);
        }
    }
}