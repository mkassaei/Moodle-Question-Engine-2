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
    /**
     * Store an entire {@link question_usage_by_activity} in the database,
     * including all the question_attempts that comprise it.
     * @param question_usage_by_activity $quba the usage to store.
     */
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

    /**
     * Store an entire {@link question_attempt} in the database,
     * including all the question_attempt_steps that comprise it.
     * @param question_attempt $qa the question attempt to store.
     */
    public function insert_question_attempt(question_attempt $qa) {
        $record = new stdClass;
        $record->questionusageid = $qa->get_usage_id();
        $record->numberinusage = $qa->get_number_in_usage();
        $record->interactionmodel = $qa->get_interaction_model_name();
        $record->questionid = $qa->get_question()->id;
        $record->maxmark = $qa->get_max_mark();
        $record->minfraction = $qa->get_min_fraction();
        $record->flagged = $qa->is_flagged();
        $record->questionsummary = $qa->get_question_summary();
        $record->rightanswer = $qa->get_right_answer_summary();
        $record->responsesummary = $qa->get_response_summary();
        $record->timemodified = time();
        $record->id = insert_record('question_attempts_new', $record);
        if (!$record->id) {
            throw new Exception('Failed to save question_attempt ' . $qa->get_number_in_usage());
        }

        foreach ($qa->get_step_iterator() as $seq => $step) {
            $this->insert_question_attempt_step($step, $record->id, $seq);
        }
    }

    /**
     * Store a {@link question_attempt_step} in the database.
     * @param question_attempt_step $qa the step to store.
     */
    public function insert_question_attempt_step(question_attempt_step $step,
            $questionattemptid, $seq) {
        $record = new stdClass;
        $record->questionattemptid = $questionattemptid;
        $record->sequencenumber = $seq;
        $record->state = '' . $step->get_state();
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

    /**
     * Load a {@link question_attempt_step} from the database.
     * @param integer $stepid the id of the step to load.
     * @param question_attempt_step the step that was loaded.
     */
    public function load_question_attempt_step($stepid) {
        global $CFG;
        $records = get_records_sql("
SELECT
    COALESCE(qasd.id, -1 * qas.id) AS id,
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

    /**
     * Load a {@link question_attempt} from the database, including all its
     * steps.
     * @param integer $questionattemptid the id of the question attempt to load.
     * @param question_attempt the question attempt that was loaded.
     */
    public function load_question_attempt($questionattemptid) {
        global $CFG;
        $records = get_records_sql("
SELECT
    COALESCE(qasd.id, -1 * qas.id) AS id,
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

    /**
     * Load a {@link question_usage_by_activity} from the database, including
     * all its {@link question_attempt}s and all their steps.
     * @param integer $qubaid the id of the usage to load.
     * @param question_usage_by_activity the usage that was loaded.
     */
    public function load_questions_usage_by_activity($qubaid) {
        global $CFG;
        $records = get_records_sql("
SELECT
    COALESCE(qasd.id, -1 * qas.id) AS id,
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

    public function reload_question_state_in_quba(question_usage_by_activity $quba, $qnumber, $seq = null) {
        global $CFG;
        $questionattemptid = $quba->get_question_attempt($qnumber)->get_database_id();

        $seqtest = '';
        if (!is_null($seq)) {
            $seqtest = 'AND qas.sequencenumber <= ' . $seq;
        }

        $records = get_records_sql("
SELECT
    COALESCE(qasd.id, -1 * qas.id) AS id,
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
    $seqtest

ORDER BY
    qas.sequencenumber
        ");

        if (!$records) {
            throw new Exception('Failed to load question_attempt ' . $questionattemptid);
        }

        $qa = question_attempt::load_from_records($records, $questionattemptid,
                new question_usage_null_observer());
        $quba->replace_loaded_question_attempt_info($qnumber, $qa);
    }

    /**
     * Load a {@link question_usage_by_activity} from the database, including
     * all its {@link question_attempt}s and all their steps.
     * @param integer $qubaid the id of the usage to load.
     * @param question_usage_by_activity the usage that was loaded.
     */
    public function load_questions_usages_latest_steps($qubaids, $qnumbers) {
        global $CFG;

        if (empty($qubaids)) {
            return array();
        } else if (is_array($qubaids)) {
            list($where, $params) = get_in_or_equal($qubaids, SQL_PARAMS_NAMED, 'qubaid0000');
            $qubaidswhere = "qa.questionusageid $where";
            $qajoin = "FROM {$CFG->prefix}question_attempts_new qa";
        } else {
            $qubaidswhere = $qubaids->where;
            $qajoin = $qubaids->from .
                    "\nJOIN {$CFG->prefix}question_attempts_new qa ON qa.questionusageid = " .
                    $qubaids->usageidcolumn;
        }

        list($qnumbertest, $params) = get_in_or_equal($qnumbers, SQL_PARAMS_NAMED, 'qnumber0000');

        $records = get_records_sql("
SELECT
    qas.id,
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
    qas.userid

$qajoin
JOIN (
    SELECT questionattemptid, MAX(id) AS latestid FROM {$CFG->prefix}question_attempt_steps GROUP BY questionattemptid
) lateststepid ON lateststepid.questionattemptid = qa.id
JOIN {$CFG->prefix}question_attempt_steps qas ON qas.id = lateststepid.latestid

WHERE
    $qubaidswhere AND
    qa.numberinusage $qnumbertest
        ");

        return $records;
    }

    /**
     * Load a {@link question_usage_by_activity} from the database, including
     * all its {@link question_attempt}s and all their steps.
     * @param integer $qubaid the id of the usage to load.
     * @param question_usage_by_activity the usage that was loaded.
     */
    public function load_average_marks($qubaids, $qnumbers = null) {
        global $CFG;

        if (is_array($qubaids)) {
            list($where, $params) = get_in_or_equal($qubaids, SQL_PARAMS_NAMED, 'qubaid0000');
            $qubaidswhere = "qa.questionusageid $where";
            $qajoin = "FROM {$CFG->prefix}question_attempts_new qa";

        } else if (is_string($qubaids)) {
            $qubaidswhere = "qa.questionusageid IN ($qubaids)";
            $qajoin = "FROM {$CFG->prefix}question_attempts_new qa";

        } else {
            $qubaidswhere = $qubaids->where;
            $qajoin = $qubaids->from .
                    "\nJOIN {$CFG->prefix}question_attempts_new qa ON qa.questionusageid = " .
                    $qubaids->usageidcolumn;
        }

        if (!empty($qnumbers)) {
            list($qnumbertest, $params) = get_in_or_equal($qnumbers, SQL_PARAMS_NAMED, 'qnumber0000');
            $qnumberwhere = " AND qa.numberinusage $qnumbertest";
        } else {
            $qnumberwhere = '';
        }

        list($statetest) = get_in_or_equal(array(
                question_state::$gradedwrong,
                question_state::$gradedpartial,
                question_state::$gradedright,
                question_state::$mangrwrong,
                question_state::$mangrpartial,
                question_state::$mangrright));

        $records = get_records_sql("
SELECT
    qa.numberinusage,
    AVG(qas.fraction) AS averagefraction,
    COUNT(1) AS numaveraged

$qajoin
JOIN (
    SELECT questionattemptid, MAX(id) AS latestid FROM {$CFG->prefix}question_attempt_steps GROUP BY questionattemptid
) lateststepid ON lateststepid.questionattemptid = qa.id
JOIN {$CFG->prefix}question_attempt_steps qas ON qas.id = lateststepid.latestid

WHERE
    $qubaidswhere
    $qnumberwhere
    AND qas.state $statetest

GROUP BY qa.numberinusage

ORDER BY qa.numberinusage
        ");

        return $records;
    }

    /**
     * Update a question_usages row to refect any changes in a usage (but not
     * any of its question_attempts.
     * @param question_usage_by_activity $quba the usage that has changed.
     */
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

    /**
     * Update a question_attempts row to refect any changes in a question_attempt
     * (but not any of its steps).
     * @param question_attempt $qa the question attempt that has changed.
     */
    public function update_question_attempt(question_attempt $qa) {
        $record = new stdClass;
        $record->id = $qa->get_database_id();
        $record->maxmark = $qa->get_max_mark();
        $record->minfraction = $qa->get_min_fraction();
        $record->flagged = $qa->is_flagged();
        $record->questionsummary = $qa->get_question_summary();
        $record->rightanswer = $qa->get_right_answer_summary();
        $record->responsesummary = $qa->get_response_summary();
        $record->timemodified = time();

        if (!update_record('question_attempts_new', $record)) {
            throw new Exception('Failed to update question_attempt ' . $record->id);
        }
    }

    /**
     * Delete a question_usage_by_activity and all its associated
     * {@link question_attempts} and {@link question_attempt_steps} from the
     * database.
     * @param string $where a where clause. Becuase of MySQL limitations, you
     *      must refer to {$CFG->prefix}question_usages.id in full like that.
     */
    public function delete_questions_usage_by_activities($where) {
        global $CFG;
        delete_records_select('question_attempt_step_data', "attemptstepid IN (
                SELECT qas.id
                FROM {$CFG->prefix}question_attempts_new qa
                JOIN {$CFG->prefix}question_attempt_steps qas ON qas.questionattemptid = qa.id
                JOIN {$CFG->prefix}question_usages ON qa.questionusageid = {$CFG->prefix}question_usages.id
                WHERE $where)");
        delete_records_select('question_attempt_steps', "questionattemptid IN (
                SELECT qa.id
                FROM {$CFG->prefix}question_attempts_new qa
                JOIN {$CFG->prefix}question_usages ON qa.questionusageid = {$CFG->prefix}question_usages.id
                WHERE $where)");
        delete_records_select('question_attempts_new', "questionusageid IN (
                SELECT id
                FROM {$CFG->prefix}question_usages
                WHERE $where)");
        delete_records_select('question_usages', $where);
    }

    /**
     * Update the flagged state of a question in the database.
     * @param integer $qubaid the question usage id.
     * @param integer $questionid the question id.
     * @param integer $sessionid the question_attempt id.
     * @param boolean $newstate the new state of the flag. true = flagged.
     */
    public function update_question_attempt_flag($qubaid, $questionid, $qaid, $newstate) {
        if (!record_exists('question_attempts_new', 'id', $qaid, 
                'questionusageid', $qubaid, 'questionid', $questionid)) {
            throw new Exception('invalid ids');
        }

        if (!set_field('question_attempts_new', 'flagged', $newstate, 'id', $qaid)) {
            throw new Exception('flag update failed');
        }
    }
}

/**
 * Implementation of the unit of work pattern for the question engine.
 *
 * See http://martinfowler.com/eaaCatalog/unitOfWork.html. This tracks all the
 * changes to a {@link question_usage_by_activity}, and its constituent parts,
 * so that the changes can be saved to the database when {@link save()} is called.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_engine_unit_of_work implements question_usage_observer {
    /** @var question_usage_by_activity the usage being tracked. */
    protected $quba;

    /** @var boolean whether any of the fields of the usage have been changed. */
    protected $modified = false;

    /**
     * @var array list of number in usage => {@link question_attempt}s that
     * were already in the usage, and which have been modified.
     */
    protected $attemptsmodified = array();

    /**
     * @var array list of number in usage => {@link question_attempt}s that
     * have been added to the usage.
     */
    protected $attemptsadded = array();

    /**
     * @var array list of array(question_attempt_step, question_attempt id, seq number)
     * of steps that have been added to question attempts in this usage.
     */
    protected $stepsadded = array();

    /**
     * Constructor.
     * @param question_usage_by_activity $quba the usage to track.
     */
    public function __construct(question_usage_by_activity $quba) {
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

    /**
     * Write all the changes we have recorded to the database.
     * @param question_engine_data_mapper $dm the mapper to use to update the database.
     */
    public function save(question_engine_data_mapper $dm) {
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