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
 * Question type class for the numerical question type.
 *
 * @package qtype_numerical
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/numerical/question.php');

/**
 * The numerical question type class.
 *
 * This class contains some special features in order to make the
 * question type embeddable within a multianswer (cloze) question
 *
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_numerical extends question_type {
    public function get_question_options($question) {
        global $CFG;

        // Get the question answers and their respective tolerances
        // Note: question_numerical is an extension of the answer table rather than
        //       the question table as is usually the case for qtype
        //       specific tables.
        if (!$question->options->answers = get_records_sql(
                                "SELECT a.*, n.tolerance " .
                                "FROM {$CFG->prefix}question_answers a, " .
                                "     {$CFG->prefix}question_numerical n " .
                                "WHERE a.question = $question->id " .
                                "    AND   a.id = n.answer " .
                                "ORDER BY a.id ASC")) {
            notify('Error: Missing question answer for numerical question ' . $question->id . '!');
            return false;
        }

        $question->hints = get_records('question_hints', 'questionid', $question->id, 'id ASC');

        $this->get_numerical_units($question);

        // If units are defined we strip off the default unit from the answer, if
        // it is present. (Required for compatibility with the old code and DB).
        if ($defaultunit = $this->get_default_numerical_unit($question)) {
            foreach($question->options->answers as $key => $val) {
                $answer = trim($val->answer);
                $length = strlen($defaultunit->unit);
                if ($length && substr($answer, -$length) == $defaultunit->unit) {
                    $question->options->answers[$key]->answer =
                            substr($answer, 0, strlen($answer)-$length);
                }
            }
        }
        return true;
    }

    public function get_numerical_units(&$question) {
        if ($units = get_records('question_numerical_units',
                                         'question', $question->id, 'id ASC')) {
            $units  = array_values($units);
        } else {
            $units = array();
        }
        foreach ($units as $key => $unit) {
            $units[$key]->multiplier = clean_param($unit->multiplier, PARAM_NUMBER);
        }
        $question->options->units = $units;
        return true;
    }

    public function get_default_numerical_unit(&$question) {
        if (isset($question->options->units[0])) {
            foreach ($question->options->units as $unit) {
                if (abs($unit->multiplier - 1.0) < '1.0e-' . ini_get('precision')) {
                    return $unit;
                }
            }
        }
        return false;
    }

    /**
     * Save the units and the answers associated with this question.
     */
    public function save_question_options($question) {
        // Get old versions of the objects
        if (!$oldanswers = get_records('question_answers', 'question', $question->id, 'id ASC')) {
            $oldanswers = array();
        }

        if (!$oldoptions = get_records('question_numerical', 'question', $question->id, 'answer ASC')) {
            $oldoptions = array();
        }

        // Save the units.
        $result = $this->save_numerical_units($question);
        if (isset($result->error)) {
            return $result;
        }

        $ap = new qtype_numerical_answer_processor($result->units);

        // Insert all the new answers
        foreach ($question->answer as $key => $dataanswer) {
            // Check for, and ingore, completely blank answer from the form.
            if (trim($dataanswer) == '' && $question->fraction[$key] == 0 &&
                    html_is_blank($question->feedback[$key])) {
                continue;
            }

            $answer = new stdClass;
            $answer->question = $question->id;
            if (trim($dataanswer) === '*') {
                $answer->answer = '*';
            } else {
                list($answer->answer) = $ap->apply_units($dataanswer);
                if (is_null($answer->answer)) {
                    $result->notice = get_string('invalidnumericanswer', 'qtype_numerical');
                }
            }
            $answer->fraction = $question->fraction[$key];
            $answer->feedback = trim($question->feedback[$key]);

            if ($oldanswer = array_shift($oldanswers)) {  // Existing answer, so reuse it
                $answer->id = $oldanswer->id;
                if (! update_record("question_answers", $answer)) {
                    $result->error = "Could not update quiz answer! (id=$answer->id)";
                    return $result;
                }
            } else { // This is a completely new answer
                if (! $answer->id = insert_record("question_answers", $answer)) {
                    $result->error = "Could not insert quiz answer!";
                    return $result;
                }
            }

            // Set up the options object
            if (!$options = array_shift($oldoptions)) {
                $options = new stdClass;
            }
            $options->question  = $question->id;
            $options->answer    = $answer->id;
            if (trim($question->tolerance[$key]) == '') {
                $options->tolerance = '';
            } else {
                list($options->tolerance) = $ap->apply_units($question->tolerance[$key]);
                if (is_null($options->tolerance)) {
                    $result->notice = get_string('invalidnumerictolerance', 'qtype_numerical');
                }
            }

            // Save options
            if (isset($options->id)) { // reusing existing record
                if (! update_record('question_numerical', $options)) {
                    $result->error = "Could not update quiz numerical options! (id=$options->id)";
                    return $result;
                }
            } else { // new options
                if (! insert_record('question_numerical', $options)) {
                    $result->error = "Could not insert quiz numerical options!";
                    return $result;
                }
            }
        }
        // delete old answer records
        if (!empty($oldanswers)) {
            foreach($oldanswers as $oa) {
                delete_records('question_answers', 'id', $oa->id);
            }
        }

        // delete old answer records
        if (!empty($oldoptions)) {
            foreach($oldoptions as $oo) {
                delete_records('question_numerical', 'id', $oo->id);
            }
        }

        $this->save_hints($question);

        // Report any problems.
        if (!empty($result->notice)) {
            return $result;
        }

        return true;
    }

    public function save_numerical_units($question) {
        $result = new stdClass;

        // Delete the units previously saved for this question.
        delete_records('question_numerical_units', 'question', $question->id);

        // Nothing to do.
        if (!isset($question->multiplier)) {
            $result->units = array();
            return $result;
        }

        // Save the new units.
        $units = array();
        foreach ($question->multiplier as $i => $multiplier) {
            // Discard any unit which doesn't specify the unit or the multiplier
            if (!empty($multiplier) && !empty($question->unit[$i])) {
                $unitrec = new stdClass;
                $unitrec->question = $question->id;
                $unitrec->multiplier = $question->multiplier[$i];
                $unitrec->unit = $question->unit[$i];
                if (!insert_record('question_numerical_units', $unitrec)) {
                    $result->error = 'Unable to save unit ' . $unitrec->unit . ' to the Databse';
                    return $result;
                }
                $units[$question->unit[$i]] = $multiplier;
            }
        }
        unset($question->multiplier, $question->unit);

        $result->units = &$units;
        return $result;
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $this->initialise_numerical_answers($question, $questiondata);
        $this->initialise_numerical_units($question, $questiondata);
    }

    protected function initialise_numerical_answers(question_definition $question, $questiondata) {
        $question->answers = array();
        if (empty($questiondata->options->answers)) {
            return;
        }
        foreach ($questiondata->options->answers as $a) {
            $question->answers[$a->id] = new qtype_numerical_answer($a->answer,
                    $a->fraction, $a->feedback, $a->tolerance);
        }
    }

    protected function initialise_numerical_units(question_definition $question, $questiondata) {
        if (empty($questiondata->options->units)) {
            $question->ap = new qtype_numerical_answer_processor(array());
            return;
        }
        $units = array();
        foreach ($questiondata->options->units as $unit) {
            $units[$unit->unit] = $unit->multiplier;
        }
        $question->ap = new qtype_numerical_answer_processor($units);
    }

    /**
     * Deletes question from the question-type specific tables
     *
     * @return boolean Success/Failure
     * @param object $question  The question being deleted
     */
    public function delete_question($questionid) {
        delete_records("question_numerical", "question", $questionid);
        delete_records("question_numerical_units", "question", $questionid);
        return parent::delete_question($questionid);
    }

    public function get_correct_responses(&$question, &$state) {
        $correct = parent::get_correct_responses($question, $state);
        $unit = $this->get_default_numerical_unit($question);
        if (isset($correct['']) && $correct[''] != '*' && $unit) {
            $correct[''] .= ' '.$unit->unit;
        }
        return $correct;
    }

    function get_random_guess_score($questiondata) {
        foreach ($questiondata->options->answers as $aid => $answer) {
            if ('*' == trim($answer->answer)) {
                return $answer->fraction;
            }
        }
        return 0;
    }

    function get_possible_responses($questiondata) {
        $responses = array();

        $unit = $this->get_default_numerical_unit($questiondata);

        foreach ($questiondata->options->answers as $aid => $answer) {
            $r = new stdClass;
            $r->responseclass = $answer->answer;
            $r->fraction = $answer->fraction;

            if ($answer->answer != '*') {
                if ($unit) {
                    $r->responseclass .= ' ' . $unit->unit;
                }

                $ans = new qtype_numerical_answer($answer->answer, $answer->fraction, $answer->feedback, $answer->tolerance);
                list($min, $max) = $ans->get_tolerance_interval();
                $r->responseclass .= " ($min..$max)";
            }

            $responses[$aid] = $r;
        }

        return array($questiondata->id => $responses);
    }

/// BACKUP FUNCTIONS ////////////////////////////

    /**
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    public function backup($bf,$preferences,$question,$level=6) {

        $status = true;

        $numericals = get_records('question_numerical', 'question', $question, 'id ASC');
        //If there are numericals
        if ($numericals) {
            //Iterate over each numerical
            foreach ($numericals as $numerical) {
                $status = fwrite ($bf,start_tag("NUMERICAL",$level,true));
                //Print numerical contents
                fwrite ($bf,full_tag("ANSWER",$level+1,false,$numerical->answer));
                fwrite ($bf,full_tag("TOLERANCE",$level+1,false,$numerical->tolerance));
                //Now backup numerical_units
                $status = question_backup_numerical_units($bf,$preferences,$question,7);
                $status = fwrite ($bf,end_tag("NUMERICAL",$level,true));
            }
            //Now print question_answers
            $status = question_backup_answers($bf,$preferences,$question);
        }
        return $status;
    }

    /// RESTORE FUNCTIONS /////////////////

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    public function restore($old_question_id,$new_question_id,$info,$restore) {

        $status = true;

        //Get the numerical array
        if (isset($info['#']['NUMERICAL'])) {
            $numericals = $info['#']['NUMERICAL'];
        } else {
            $numericals = array();
        }

        //Iterate over numericals
        for($i = 0; $i < sizeof($numericals); $i++) {
            $num_info = $numericals[$i];

            //Now, build the question_numerical record structure
            $numerical = new stdClass;
            $numerical->question = $new_question_id;
            $numerical->answer = backup_todb($num_info['#']['ANSWER']['0']['#']);
            $numerical->tolerance = backup_todb($num_info['#']['TOLERANCE']['0']['#']);

            //We have to recode the answer field
            $answer = backup_getid($restore->backup_unique_code,"question_answers",$numerical->answer);
            if ($answer) {
                $numerical->answer = $answer->new_id;
            }

            //The structure is equal to the db, so insert the question_numerical
            $newid = insert_record ("question_numerical", $numerical);

            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if (($i+1) % 1000 == 0) {
                        echo "<br />";
                    }
                }
                backup_flush(300);
            }

            //Now restore numerical_units
            $status = question_restore_numerical_units ($old_question_id,$new_question_id,$num_info,$restore);

            if (!$newid) {
                $status = false;
            }
        }

        return $status;
    }

    /**
     * Runs all the code required to set up and save an essay question for testing purposes.
     * Alternate DB table prefix may be used to facilitate data deletion.
     */
    public function generate_test($name, $courseid = null) {
        list($form, $question) = question_type::generate_test($name, $courseid);
        $question->category = $form->category;

        $form->questiontext = "What is 674 * 36?";
        $form->generalfeedback = "Thank you";
        $form->penalty = 0.3333333;
        $form->defaultgrade = 1;
        $form->noanswers = 3;
        $form->answer = array('24264', '24264', '1');
        $form->tolerance = array(10, 100, 0);
        $form->fraction = array(1, 0.5, 0);
        $form->nounits = 2;
        $form->unit = array(0 => null, 1 => null);
        $form->multiplier = array(1, 0);
        $form->feedback = array('Very good', 'Close, but not quite there', 'Well at least you tried....');

        if ($courseid) {
            $course = get_record('course', 'id', $courseid);
        }

        return $this->save_question($question, $form, $course);
    }

}


/**
 * This class processes numbers with units.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_numerical_answer_processor {
    /** @var array unit name => multiplier. */
    protected $units;
    /** @var string character used as decimal point. */
    protected $decsep;
    /** @var string character used as thousands separator. */
    protected $thousandssep;

    protected $regex = null;

    public function __construct($units, $decsep = null, $thousandssep = null) {
        if (is_null($decsep)) {
            $decsep = get_string('decsep', 'langconfig');
        }
        $this->decsep = $decsep;

        if (is_null($thousandssep)) {
            $thousandssep = get_string('thousandssep', 'langconfig');
        }
        $this->thousandssep = $thousandssep;

        $this->units = $units;
    }

    /**
     * Set the decimal point and thousands separator character that should be used.
     * @param string $decsep
     * @param string $thousandssep
     */
    public function set_characters($decsep, $thousandssep) {
        $this->decsep = $decsep;
        $this->thousandssep = $thousandssep;
        $this->regex = null;
    }

    /** @return string the decimal point character used. */
    public function get_point() {
        return $this->decsep;
    }

    /** @return string the thousands separator character used. */
    public function get_separator() {
        return $this->thousandssep;
    }

    /**
     * Create the regular expression that {@link parse_response()} requires.
     * @return string
     */
    protected function build_regex() {
        if (!is_null($this->regex)) {
            return $this->regex;
        }

        $beforepointre = '([+-]?[' . preg_quote($this->thousandssep, '/') . '\d]*)';
        $decimalsre = preg_quote($this->decsep, '/') . '(\d*)';
        $exponentre = '(?:e|E|(?:x|\*|×)10(?:\^|\*\*))([+-]?\d+)';

        $escapedunits = array();
        foreach ($this->units as $unit => $notused) {
            $escapedunits[] = preg_quote($unit, '/');
        }
        $unitre = '(' . implode('|', $escapedunits) . ')';

        $this->regex = "/^$beforepointre(?:$decimalsre)?(?:$exponentre)?\s*(?:$unitre)?$/U";
        return $this->regex;
    }

    /**
     * Take a string which is a number with or without a decimal point and exponent,
     * and possibly followed by one of the units, and split it into bits.
     * @param string $response a value, optionally with a unit.
     * @return array four strings (some of which may be blank) the digits before
     * and after the decimal point, the exponent, and the unit. All four will be
     * null if the response cannot be parsed.
     */
    protected function parse_response($response) {
        if (!preg_match($this->build_regex(), $response, $matches)) {
            return array(null, null, null, null);
        }

        $matches += array('', '', '', '', ''); // Fill in any missing matches.
        list($notused, $beforepoint, $decimals, $exponent, $unit) = $matches;

        // Strip out thousands separators.
        $beforepoint = str_replace($this->thousandssep, '', $beforepoint);

        // Must be either something before, or something after the decimal point.
        // (The only way to do this in the regex would make it much more complicated.)
        if ($beforepoint === '' && $decimals === '') {
            return array(null, null, null, null);
        }

        return array($beforepoint, $decimals, $exponent, $unit);
    }

    /**
     * Takes a number in localised form, that is, using the decsep and thousandssep
     * defined in the lanuage pack, and possibly with a unit after it. It separates
     * off the unit, if present, and converts to the default unit, by using the
     * given unit multiplier.
     *
     * @param string $response a value, optionally with a unit.
     * @return array(numeric, sting) the value with the unit stripped, and normalised
     *      by the unit multiplier, if any, and the unit string, for reference.
     */
    public function apply_units($response) {
        list($beforepoint, $decimals, $exponent, $unit) = $this->parse_response($response);

        if (is_null($beforepoint)) {
            return array(null, null);
        }

        $numberstring = $beforepoint . '.' . $decimals;
        if ($exponent) {
            $numberstring .= 'e' . $exponent;
        }

        if ($unit) {
            $value = $numberstring * $this->units[$unit];
        } else {
            $value = $numberstring * 1;
        }

        return array($value, $unit);
    }
}
