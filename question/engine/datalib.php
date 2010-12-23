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
 * @copyright 2009 The Open University
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
        $record->component = addslashes($quba->get_owning_component());
        $record->preferredbehaviour = addslashes($quba->get_preferred_behaviour());

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
        $record->slot = $qa->get_slot();
        $record->behaviour = addslashes($qa->get_behaviour_name());
        $record->questionid = $qa->get_question()->id;
        $record->maxmark = $qa->get_max_mark();
        $record->minfraction = $qa->get_min_fraction();
        $record->flagged = $qa->is_flagged();
        $record->questionsummary = addslashes($qa->get_question_summary());
        $record->rightanswer = addslashes($qa->get_right_answer_summary());
        $record->responsesummary = addslashes($qa->get_response_summary());
        $record->timemodified = time();
        $record->id = insert_record('question_attempts', $record);
        if (!$record->id) {
            throw new Exception('Failed to save question_attempt ' . $qa->get_slot());
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
        $record->state = addslashes('' . $step->get_state());
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
            $data->name = addslashes($name);
            $data->value = addslashes($value);
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
    quba.preferredbehaviour,
    qa.id AS questionattemptid,
    qa.questionusageid,
    qa.slot,
    qa.behaviour,
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

FROM {$CFG->prefix}question_attempts qa
JOIN {$CFG->prefix}question_usages quba ON quba.id = qa.questionusageid
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

        $record = current($records);
        return question_attempt::load_from_records($records, $questionattemptid,
                new question_usage_null_observer(), $record->preferredbehaviour);
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
    quba.component,
    quba.preferredbehaviour,
    qa.id AS questionattemptid,
    qa.questionusageid,
    qa.slot,
    qa.behaviour,
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
LEFT JOIN {$CFG->prefix}question_attempts qa ON qa.questionusageid = quba.id
LEFT JOIN {$CFG->prefix}question_attempt_steps qas ON qas.questionattemptid = qa.id
LEFT JOIN {$CFG->prefix}question_attempt_step_data qasd ON qasd.attemptstepid = qas.id

WHERE
    quba.id = $qubaid

ORDER BY
    qa.slot,
    qas.sequencenumber
    ");

        if (!$records) {
            throw new Exception('Failed to load questions_usage_by_activity ' . $qubaid);
        }

        return question_usage_by_activity::load_from_records($records, $qubaid);
    }

    /**
     * Load information about the latest state of each question from the database.
     *
     * @param qubaid_condition $qubaids used to restrict which usages are included
     * in the query. See {@link qubaid_condition}.
     * @param array $slots A list of slots for the questions you want to konw about.
     * @return array of records. See the SQL in this function to see the fields available.
     */
    public function load_questions_usages_latest_steps(qubaid_condition $qubaids, $slots) {
        global $CFG;

        list($slottest, $params) = get_in_or_equal($slots, SQL_PARAMS_NAMED, 'slot0000');

        $records = get_records_sql("
SELECT
    qas.id,
    qa.id AS questionattemptid,
    qa.questionusageid,
    qa.slot,
    qa.behaviour,
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

FROM {$qubaids->from_question_attempts('qa')}
JOIN {$CFG->prefix}question_attempt_steps qas ON
        qas.id = {$this->latest_step_for_qa_subquery()}

WHERE
    {$qubaids->where()} AND
    qa.slot $slottest
        ");

        if (!$records) {
            $records = array();
        }

        return $records;
    }

    /**
     * Load summary information about the state of each question in a group of attempts.
     * This is used by the quiz manual grading report, to show how many attempts
     * at each question need to be graded.
     *
     * @param qubaid_condition $qubaids used to restrict which usages are included
     * in the query. See {@link qubaid_condition}.
     * @param array $slots A list of slots for the questions you want to konw about.
     * @return array The array keys are slot,qestionid. The values are objects with
     * fields $slot, $questionid, $inprogress, $name, $needsgrading, $autograded,
     * $manuallygraded and $all.
     */
    public function load_questions_usages_question_state_summary(qubaid_condition $qubaids, $slots) {
        global $CFG;

        list($slottest, $params) = get_in_or_equal($slots, SQL_PARAMS_NAMED, 'slot0000');

        $rs = get_recordset_sql("
SELECT
    qa.slot,
    qa.questionid,
    q.name,
    CASE qas.state
        {$this->full_states_to_summary_state_sql()}
    END AS summarystate,
    COUNT(1) AS numattempts

FROM {$qubaids->from_question_attempts('qa')}
JOIN {$CFG->prefix}question_attempt_steps qas ON
        qas.id = {$this->latest_step_for_qa_subquery()}
JOIN {$CFG->prefix}question q ON q.id = qa.questionid

WHERE
    {$qubaids->where()} AND
    qa.slot $slottest

GROUP BY
    qa.slot,
    qa.questionid,
    q.name,
    q.id,
    summarystate

ORDER BY 
    qa.slot,
    qa.questionid,
    q.name,
    q.id
        ");

        if (!$rs) {
            throw new moodle_exception('errorloadingdata');
        }

        $results = array();
        while ($row = rs_fetch_next_record($rs)) {
            $index = $row->slot . ',' . $row->questionid;

            if (!array_key_exists($index, $results)) {
                $res = new stdClass;
                $res->slot = $row->slot;
                $res->questionid = $row->questionid;
                $res->name = $row->name;
                $res->inprogress = 0;
                $res->needsgrading = 0;
                $res->autograded = 0;
                $res->manuallygraded = 0;
                $res->all = 0;
                $results[$index] = $res;
            }

            $results[$index]->{$row->summarystate} = $row->numattempts;
            $results[$index]->all += $row->numattempts;
        }
        rs_close($rs);

        return $results;
    }

    /**
     * Get a list of usage ids where the question with slot $slot, and optionally
     * also with question id $questionid, is in summary state $summarystate. Also
     * return the total count of such states.
     *
     * Only a subset of the ids can be returned by using $orderby, $limitfrom and
     * $limitnum. A special value 'random' can be passed as $orderby, in which case
     * $limitfrom is ignored.
     *
     * @param qubaid_condition $qubaids used to restrict which usages are included
     * in the query. See {@link qubaid_condition}.
     * @param integer $slot The slot for the questions you want to konw about.
     * @param integer $questionid (optional) Only return attempts that were of this specific question.
     * @param string $summarystate the summary state of interest, or 'all'.
     * @param string $orderby the column to order by.
     * @param integer $limitfrom implements paging of the results.
     *      Ignored if $orderby = random or $limitnum is null.
     * @param integer $limitnum implements paging of the results. null = all.
     * @return array with two elements, an array of usage ids, and a count of the total number.
     */
    public function load_questions_usages_where_question_in_state(
            qubaid_condition $qubaids, $summarystate, $slot, $questionid = null,
            $orderby = 'random', $limitfrom = 0, $limitnum = null) {
        global $CFG;

        $extrawhere = '';
        if ($questionid) {
            $extrawhere .= ' AND qa.questionid = ' . $questionid;
        }
        if ($summarystate != 'all') {
            $test = $this->in_summary_state_test($summarystate);
            $extrawhere .= ' AND qas.state ' . $test;
        }

        if ($orderby == 'random') {
            $sqlorderby = '';
        } else if ($orderby) {
            $sqlorderby = 'ORDER BY ' . $orderby;
        } else {
            $sqlorderby = '';
        }

        // We always want the total count, as well as the partcular list of ids,
        // based on the paging and sort order. Becuase the list of ids is never
        // going to be too rediculously long. My worst-case scenario is
        // 10,000 students in the coures, each doing 5 quiz attempts. That
        // is a 50,000 element int => int array, which PHP seems to use 5MB
        // memeory to store on a 64 bit server.
        $qubaids = get_records_sql_menu("
SELECT
    qa.questionusageid,
    1

FROM {$qubaids->from_question_attempts('qa')}
JOIN {$CFG->prefix}question_attempt_steps qas ON
        qas.id = {$this->latest_step_for_qa_subquery()}
JOIN {$CFG->prefix}question q ON q.id = qa.questionid

WHERE
    {$qubaids->where()} AND
    qa.slot = $slot
    $extrawhere

$sqlorderby
        ");

        $qubaids = array_keys($qubaids);
        $count = count($qubaids);

        if ($orderby == 'random') {
            shuffle($qubaids);
            $limitfrom = 0;
        }

        if (!is_null($limitnum)) {
            $qubaids = array_slice($qubaids, $limitfrom, $limitnum);
        }

        return array($qubaids, $count);
    }

    /**
     * Load a {@link question_usage_by_activity} from the database, including
     * all its {@link question_attempt}s and all their steps.
     * @param qubaid_condition $qubaids used to restrict which usages are included
     * in the query. See {@link qubaid_condition}.
     * @param array $slots if null, load info for all quesitions, otherwise only
     * load the averages for the specified questions.
     */
    public function load_average_marks(qubaid_condition $qubaids, $slots = null) {
        global $CFG;

        if (!empty($slots)) {
            list($slottest, $params) = get_in_or_equal($slots, SQL_PARAMS_NAMED, 'slot0000');
            $slotwhere = " AND qa.slot $slottest";
        } else {
            $slotwhere = '';
        }

        list($statetest) = get_in_or_equal(array(
                question_state::$gaveup,
                question_state::$gradedwrong,
                question_state::$gradedpartial,
                question_state::$gradedright,
                question_state::$mangaveup,
                question_state::$mangrwrong,
                question_state::$mangrpartial,
                question_state::$mangrright));

        $records = get_records_sql("
SELECT
    qa.slot,
    AVG(COALESCE(qas.fraction, 0)) AS averagefraction,
    COUNT(1) AS numaveraged

FROM {$qubaids->from_question_attempts('qa')}
JOIN {$CFG->prefix}question_attempt_steps qas ON
        qas.id = {$this->latest_step_for_qa_subquery()}

WHERE
    {$qubaids->where()}
    $slotwhere
    AND qas.state $statetest

GROUP BY qa.slot

ORDER BY qa.slot
        ");

        return $records;
    }

    /**
     * Load a {@link question_attempt} from the database, including all its
     * steps.
     * @param integer $questionid the question to load all the attempts fors.
     * @param qubaid_condition $qubaids used to restrict which usages are included
     * in the query. See {@link qubaid_condition}.
     * @return array of question_attempts.
     */
    public function load_attempts_at_question($questionid, qubaid_condition $qubaids) {
        global $CFG;
        $records = get_records_sql("
SELECT
    COALESCE(qasd.id, -1 * qas.id) AS id,
    quba.preferredbehaviour,
    qa.id AS questionattemptid,
    qa.questionusageid,
    qa.slot,
    qa.behaviour,
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

FROM {$qubaids->from_question_attempts('qa')}
JOIN {$CFG->prefix}question_usages quba ON quba.id = qa.questionusageid
LEFT JOIN {$CFG->prefix}question_attempt_steps qas ON qas.questionattemptid = qa.id
LEFT JOIN {$CFG->prefix}question_attempt_step_data qasd ON qasd.attemptstepid = qas.id

WHERE
    {$qubaids->where()} AND
    qa.questionid = $questionid

ORDER BY
    quba.id,
    qa.id,
    qas.sequencenumber
        ");

        if (!$records) {
            return array();
        }

        $questionattempts = array();
        $record = current($records);
        while ($record) {
            $questionattempts[$record->questionattemptid] =
                    question_attempt::load_from_records($records,
                    $record->questionattemptid, new question_usage_null_observer(),
                    $record->preferredbehaviour);
            $record = current($records);
        }
        return $questionattempts;
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
        $record->component = addslashes($quba->get_owning_component());
        $record->preferredbehaviour = addslashes($quba->get_preferred_behaviour());

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
        $record->questionsummary = addslashes($qa->get_question_summary());
        $record->rightanswer = addslashes($qa->get_right_answer_summary());
        $record->responsesummary = addslashes($qa->get_response_summary());
        $record->timemodified = time();

        if (!update_record('question_attempts', $record)) {
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
                FROM {$CFG->prefix}question_attempts qa
                JOIN {$CFG->prefix}question_attempt_steps qas ON qas.questionattemptid = qa.id
                JOIN {$CFG->prefix}question_usages ON qa.questionusageid = {$CFG->prefix}question_usages.id
                WHERE $where)");
        delete_records_select('question_attempt_steps', "questionattemptid IN (
                SELECT qa.id
                FROM {$CFG->prefix}question_attempts qa
                JOIN {$CFG->prefix}question_usages ON qa.questionusageid = {$CFG->prefix}question_usages.id
                WHERE $where)");
        delete_records_select('question_attempts', "questionusageid IN (
                SELECT id
                FROM {$CFG->prefix}question_usages
                WHERE $where)");
        delete_records_select('question_usages', $where);
    }

    /**
     * Delete all the steps for a question attempt.
     * @param integer $qaids question_attempt id.
     */
    public function delete_steps_for_question_attempts($qaids) {
        global $CFG;
        if (empty($qaids)) {
            return;
        }
        list($test, $params) = get_in_or_equal($qaids);
        delete_records_select('question_attempt_step_data', "attemptstepid IN (
                SELECT qas.id
                FROM {$CFG->prefix}question_attempt_steps qas
                WHERE questionattemptid $test)");
        delete_records_select('question_attempt_steps', 'questionattemptid ' . $test);
    }

    /**
     * Delete all the previews for a given question.
     * @param integer $questionid question id.
     */
    public function delete_previews($questionid) {
        global $CFG;
        $previews = get_records_sql_menu("
                SELECT DISTINCT quba.id, 1
                FROM {$CFG->prefix}question_usages quba
                JOIN {$CFG->prefix}question_attempts qa ON qa.questionusageid = quba.id
                WHERE quba.component = 'core_question_preview' AND
                    qa.questionid = '$questionid'");
        if (empty($previews)) {
            return;
        }
        $this->delete_questions_usage_by_activities(
                "{$CFG->prefix}question_usages.id IN (" .
                implode(',', array_keys($previews)) . ')');
    }

    /**
     * Update the flagged state of a question in the database.
     * @param integer $qubaid the question usage id.
     * @param integer $questionid the question id.
     * @param integer $sessionid the question_attempt id.
     * @param boolean $newstate the new state of the flag. true = flagged.
     */
    public function update_question_attempt_flag($qubaid, $questionid, $qaid, $newstate) {
        if (!record_exists('question_attempts', 'id', $qaid, 
                'questionusageid', $qubaid, 'questionid', $questionid)) {
            throw new Exception('invalid ids');
        }

        if (!set_field('question_attempts', 'flagged', $newstate, 'id', $qaid)) {
            throw new Exception('flag update failed');
        }
    }

    /**
     * Get all the WHEN 'x' THEN 'y' terms needed to convert the question_attempt_steps.state
     * column to a summary state. Use this like
     * CASE qas.state {$this->full_states_to_summary_state_sql()} END AS summarystate,
     * @param string SQL fragment.
     */
    protected function full_states_to_summary_state_sql() {
        $sql = '';
        foreach (question_state::get_all() as $state) {
            $sql .= "WHEN '$state' THEN '{$state->get_summary_state()}'\n";
        }
        return $sql;
    }

    /**
     * Get the SQL needed to test that question_attempt_steps.state is in a
     * state corresponding to $summarystate.
     * @param string $summarystate one of
     * inprogress, needsgrading, manuallygraded or autograded
     * @param boolean $equal if false, do a NOT IN test. Default true.
     * @return string SQL fragment.
     */
    public function in_summary_state_test($summarystate, $equal = true) {
        $states = question_state::get_all_for_summary_state($summarystate);
        list($sql, $params) = get_in_or_equal($states, SQL_PARAMS_QM, 'param0000', $equal);
        return $sql;
    }

    /**
     * Change the maxmark for the question_attempt with number in usage $slot
     * for all the specified question_attempts.
     * @param qubaid_condition $qubaids Selects which usages are updated.
     * @param integer $slot the number is usage to affect.
     * @param number $newmaxmark the new max mark to set.
     */
    public function set_max_mark_in_attempts(qubaid_condition $qubaids, $slot, $newmaxmark) {
        set_field_select('question_attempts', 'maxmark', $newmaxmark,
                "questionusageid {$qubaids->usage_id_in()} AND slot = $slot");
    }

    /**
     * Return a subquery that computes the sum of the marks for all the questions
     * in a usage. Which useage to compute the sum for is controlled bu the $qubaid
     * parameter.
     *
     * See {@link quiz_update_all_attempt_sumgrades()} for an example of the usage of
     * this method.
     *
     * @param string $qubaid SQL fragment that controls which usage is summed.
     * This might be the name of a column in the outer query.
     * @return string SQL code for the subquery.
     */
    public function sum_usage_marks_subquery($qubaid) {
        global $CFG;
        return "SELECT SUM(qa.maxmark * qas.fraction)
            FROM {$CFG->prefix}question_attempts qa
            JOIN (
                SELECT summarks_qa.id AS questionattemptid, MAX(summarks_qas.id) AS latestid
                FROM {$CFG->prefix}question_attempt_steps summarks_qas
                JOIN {$CFG->prefix}question_attempts summarks_qa ON summarks_qa.id = summarks_qas.questionattemptid
                WHERE summarks_qa.questionusageid = $qubaid
                GROUP BY summarks_qa.id
            ) lateststepid ON lateststepid.questionattemptid = qa.id
            JOIN {$CFG->prefix}question_attempt_steps qas ON qas.id = lateststepid.latestid
            WHERE qa.questionusageid = $qubaid
            HAVING COUNT(CASE WHEN qas.state = 'needsgrading' THEN 1 ELSE NULL END) = 0";
    }

    public function question_attempt_latest_state_view($alias) {
        global $CFG;
        return "(
                SELECT
                    {$alias}qa.id AS questionattemptid,
                    {$alias}qa.questionusageid,
                    {$alias}qa.slot,
                    {$alias}qa.behaviour,
                    {$alias}qa.questionid,
                    {$alias}qa.maxmark,
                    {$alias}qa.minfraction,
                    {$alias}qa.flagged,
                    {$alias}qa.questionsummary,
                    {$alias}qa.rightanswer,
                    {$alias}qa.responsesummary,
                    {$alias}qa.timemodified,
                    {$alias}qas.id AS attemptstepid,
                    {$alias}qas.sequencenumber,
                    {$alias}qas.state,
                    {$alias}qas.fraction,
                    {$alias}qas.timecreated,
                    {$alias}qas.userid

                FROM {$CFG->prefix}question_attempts {$alias}qa
                JOIN {$CFG->prefix}question_attempt_steps {$alias}qas ON
                        {$alias}qas.id = {$this->latest_step_for_qa_subquery($alias . 'qa.id')}
            ) $alias";
    }

    protected function latest_step_for_qa_subquery($questionattemptid = 'qa.id') {
        global $CFG;
        return "(
                SELECT MAX(id)
                FROM {$CFG->prefix}question_attempt_steps
                WHERE questionattemptid = $questionattemptid
            )";
    }

    /**
     * @param array $questionids of question ids.
     * @return boolean whether any of these questions are being used by the question engine.
     */
    public static function questions_in_use(array $questionids) {
        return record_exists_select('question_attempts', 'questionid IN (' .
                implode(',', $questionids) . ')');
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
     * @var array list of question attempt ids to delete the steps for, before
     * inserting new steps.
     */
    protected $attemptstodeletestepsfor = array();

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
        $no = $qa->get_slot();
        if (!array_key_exists($no, $this->attemptsadded)) {
            $this->attemptsmodified[$no] = $qa;
        }
    }

    public function notify_attempt_added(question_attempt $qa) {
        $this->attemptsadded[$qa->get_slot()] = $qa;
    }

    public function notify_delete_attempt_steps(question_attempt $qa) {

        if (array_key_exists($qa->get_slot(), $this->attemptsadded)) {
            return;
        }

        $qaid = $qa->get_database_id();
        foreach ($this->stepsadded as $key => $stepinfo) {
            if ($stepinfo[1] == $qaid) {
                unset($this->stepsadded[$key]);
            }
        }

        $this->attemptstodeletestepsfor[$qaid] = 1;
    }

    public function notify_step_added(question_attempt_step $step, question_attempt $qa, $seq) {
        if (array_key_exists($qa->get_slot(), $this->attemptsadded)) {
            return;
        }
        $this->stepsadded[] = array($step, $qa->get_database_id(), $seq);
    }

    /**
     * Write all the changes we have recorded to the database.
     * @param question_engine_data_mapper $dm the mapper to use to update the database.
     */
    public function save(question_engine_data_mapper $dm) {
        $dm->delete_steps_for_question_attempts(array_keys($this->attemptstodeletestepsfor));
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


/**
 * This class represents a restriction on the set of question_usage ids to include
 * in a larger database query. Depending of the how you are going to restrict the
 * list of usages, construct an appropriate subclass.
 *
 * If $qubaids is an instance of this class, example usage might be
 *
 * SELECT qa.id, qa.maxmark
 * FROM $qubaids->from_question_attempts('qa')
 * WHERE $qubaids->where() AND qa.slot = 1
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qubaid_condition {

    /**
     * @return string the SQL that needs to go in the FROM clause when trying
     * to select records from the 'question_attempts' table based on the
     * qubaid_condition.
     */
    public abstract function from_question_attempts($alias);

    /** @return string the SQL that needs to go in the where clause. */
    public abstract function where();

    /**
     * @return string SQL that can use used in a WHERE qubaid IN (...) query.
     * This method returns the "IN (...)" part.
     */
    public abstract function usage_id_in();

}


/**
 * This class represents a restriction on the set of question_usage ids to include
 * in a larger database query based on an explicit list of ids.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qubaid_list extends qubaid_condition {
    /** @var array of ids. */
    protected $qubaids;
    protected $columntotest = null;

    /**
     * Constructor.
     * @param array $qubaids of question usage ids.
     */
    public function __construct(array $qubaids) {
        $this->qubaids = $qubaids;
    }

    public function from_question_attempts($alias) {
        global $CFG;
        $this->columntotest = $alias . '.questionusageid';
        return "{$CFG->prefix}question_attempts $alias";
    }

    public function where() {
        if (is_null($this->columntotest)) {
            throw new coding_exception('Must call another method that before where().');
        }
        if (empty($this->qubaids)) {
            return '1 = 0';
        }
        list($where, $params) = get_in_or_equal($this->qubaids, SQL_PARAMS_NAMED, 'qubaid0000');
        return "{$this->columntotest} {$this->usage_id_in()}";
    }

    public function usage_id_in() {
        if (empty($this->qubaids)) {
            return '= 0';
        }
        list($where, $params) = get_in_or_equal($this->qubaids, SQL_PARAMS_NAMED, 'qubaid0000');
        return $where;
    }
}


/**
 * This class represents a restriction on the set of question_usage ids to include
 * in a larger database query based on JOINing to some other tables.
 *
 * The general form of the query is something like
 *
 * SELECT qa.id, qa.maxmark
 * FROM $from
 * JOIN {$CFG->prefix}question_attempts qa ON qa.questionusageid = $usageidcolumn
 * WHERE $where AND qa.slot = 1
 *
 * where $from, $usageidcolumn and $where are the arguments to the constructor.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qubaid_join extends qubaid_condition {
    /** @var array of ids. */
    public $from;
    public $usageidcolumn;
    public $where;

    /**
     * Constructor. The meaning of the arguments is explained in the class comment.
     * @param string $from SQL fragemnt to go in the FROM clause.
     * @param string $usageidcolumn the column in $from that should be
     * made equal to the usageid column in the JOIN clause.
     * @param string $where SQL fragment to go in the where clause.
     */
    public function __construct($from, $usageidcolumn, $where = '') {
        $this->from = $from;
        $this->usageidcolumn = $usageidcolumn;
        if (empty($where)) {
            $where = '1 = 1';
        }
        $this->where = $where;
    }

    public function from_question_attempts($alias) {
        global $CFG;
        return "$this->from
                JOIN {$CFG->prefix}question_attempts {$alias} ON " .
                        "{$alias}.questionusageid = $this->usageidcolumn";
    }

    public function where() {
        return $this->where;
    }

    public function usage_id_in() {
        return "IN (SELECT $this->usageidcolumn FROM $this->from WHERE $this->where)";
    }
}
