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
 * Question type class for the matching question type.
 *
 * @package qtype_match
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

/**
 * The matching question type class.
 *
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_match extends question_type {

    public function get_question_options($question) {
        parent::get_question_options($question);
        $question->options = get_record('question_match', 'question', $question->id);
        $question->options->subquestions = get_records('question_match_sub', 'question', $question->id, 'id ASC');
        return true;
    }

    public function save_question_options($question) {
        $result = new stdClass;

        if (!$oldsubquestions = get_records("question_match_sub", "question", $question->id, "id ASC")) {
            $oldsubquestions = array();
        }

        // $subquestions will be an array with subquestion ids
        $subquestions = array();

        // Insert all the new question+answer pairs
        foreach ($question->subquestions as $key => $questiontext) {
            $questiontext = trim($questiontext);
            $answertext = trim($question->subanswers[$key]);
            if ($questiontext != '' || $answertext != '') {
                if ($subquestion = array_shift($oldsubquestions)) {  // Existing answer, so reuse it
                    $subquestion->questiontext = $questiontext;
                    $subquestion->answertext   = $answertext;
                    if (!update_record("question_match_sub", $subquestion)) {
                        $result->error = "Could not insert match subquestion! (id=$subquestion->id)";
                        return $result;
                    }
                } else {
                    $subquestion = new stdClass;
                    // Determine a unique random code
                    $subquestion->code = rand(1,999999999);
                    while (record_exists('question_match_sub', 'code', $subquestion->code, 'question', $question->id)) {
                        $subquestion->code = rand();
                    }
                    $subquestion->question = $question->id;
                    $subquestion->questiontext = $questiontext;
                    $subquestion->answertext   = $answertext;
                    if (!$subquestion->id = insert_record("question_match_sub", $subquestion)) {
                        $result->error = "Could not insert match subquestion!";
                        return $result;
                    }
                }
                $subquestions[] = $subquestion->id;
            }
            if ($questiontext != '' && $answertext == '') {
                $result->notice = get_string('nomatchinganswer', 'quiz', $questiontext);
            }
        }

        // delete old subquestions records
        if (!empty($oldsubquestions)) {
            foreach($oldsubquestions as $os) {
                delete_records('question_match_sub', 'id', $os->id);
            }
        }

        if ($options = get_record("question_match", "question", $question->id)) {
            $options->subquestions = implode(",",$subquestions);
            $options->shuffleanswers = $question->shuffleanswers;
            if (!update_record("question_match", $options)) {
                $result->error = "Could not update match options! (id=$options->id)";
                return $result;
            }
        } else {
            unset($options);
            $options->question = $question->id;
            $options->subquestions = implode(",",$subquestions);
            $options->shuffleanswers = $question->shuffleanswers;
            if (!insert_record("question_match", $options)) {
                $result->error = "Could not insert match options!";
                return $result;
            }
        }

        $this->save_hints($question, true);

        if (!empty($result->notice)) {
            return $result;
        }

        if (count($subquestions) < 3) {
            $result->notice = get_string('notenoughanswers', 'quiz', 3);
            return $result;
        }

        return true;
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $question->shufflestems = $questiondata->options->shuffleanswers;

        $question->stems = array();
        $question->choices = array();
        $question->right = array();

        foreach ($questiondata->options->subquestions as $matchsub) {
            $ans = $matchsub->answertext;
            $key = array_search($matchsub->answertext, $question->choices);
            if ($key === false) {
                $key = $matchsub->id;
                $question->choices[$key] = $matchsub->answertext;
            }

            if ($matchsub->questiontext !== '') {
                $question->stems[$matchsub->id] = $matchsub->questiontext;
                $question->right[$matchsub->id] = $key;
            }
        }
    }

    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    /**
     * Deletes question from the question-type specific tables
     *
     * @return boolean Success/Failure
     * @param integer $question->id
     */
    public function delete_question($questionid) {
        parent::delete_question($questionid);
        delete_records("question_match", "question", $questionid);
        delete_records("question_match_sub", "question", $questionid);
        return true;
    }

    // ULPGC ecastro for stats report
    public function get_all_responses($question, $state) {
        $answers = array();
        if (is_array($question->options->subquestions)) {
            foreach ($question->options->subquestions as $aid => $answer) {
                if ($answer->questiontext !== '' && !is_null($answer->questiontext)) {
                    $r = new stdClass;
                    $r->answer = $answer->questiontext . ": " . $answer->answertext;
                    $r->credit = 1;
                    $answers[$aid] = $r;
                }
            }
        }
        $result = new stdClass;
        $result->id = $question->id;
        $result->responses = $answers;
        return $result;
    }

    // ULPGC ecastro
    public function get_actual_response($question, $state) {
        $subquestions = &$state->options->subquestions;
        $responses    = &$state->responses;
        $results = array();
        foreach ($responses as $ind => $code) {
            foreach ($subquestions as $key => $sub) {
                if (isset($sub->options->answers[$code])) {
                    $results[$ind] =  $subquestions[$ind]->questiontext . ": " . $sub->options->answers[$code]->answer;
                }
            }
        }
        return $results;
   }

    public function response_summary($question, $state, $length=80) {
        // This should almost certainly be overridden
        return shorten_text(implode(', ', $this->get_actual_response($question, $state)), $length);
    }

/// BACKUP FUNCTIONS ////////////////////////////

    /*
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    public function backup($bf,$preferences,$question,$level=6) {
        $status = true;

        // Output the shuffleanswers setting.
        $matchoptions = get_record('question_match', 'question', $question);
        if ($matchoptions) {
            $status = fwrite ($bf,start_tag("MATCHOPTIONS",6,true));
            fwrite ($bf,full_tag("SHUFFLEANSWERS",7,false,$matchoptions->shuffleanswers));
            $status = fwrite ($bf,end_tag("MATCHOPTIONS",6,true));
        }

        $matchs = get_records('question_match_sub', 'question', $question, 'id ASC');
        //If there are matchs
        if ($matchs) {
            //Print match contents
            $status = fwrite ($bf,start_tag("MATCHS",6,true));
            //Iterate over each match
            foreach ($matchs as $match) {
                $status = fwrite ($bf,start_tag("MATCH",7,true));
                //Print match contents
                fwrite ($bf,full_tag("ID",8,false,$match->id));
                fwrite ($bf,full_tag("CODE",8,false,$match->code));
                fwrite ($bf,full_tag("QUESTIONTEXT",8,false,$match->questiontext));
                fwrite ($bf,full_tag("ANSWERTEXT",8,false,$match->answertext));
                $status = fwrite ($bf,end_tag("MATCH",7,true));
            }
            $status = fwrite ($bf,end_tag("MATCHS",6,true));
        }
        return $status;
    }

/// RESTORE FUNCTIONS /////////////////

    /*
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    public function restore($old_question_id,$new_question_id,$info,$restore) {
        $status = true;

        //Get the matchs array
        $matchs = $info['#']['MATCHS']['0']['#']['MATCH'];

        //We have to build the subquestions field (a list of match_sub id)
        $subquestions_field = "";
        $in_first = true;

        //Iterate over matchs
        for($i = 0; $i < sizeof($matchs); $i++) {
            $mat_info = $matchs[$i];

            //We'll need this later!!
            $oldid = backup_todb($mat_info['#']['ID']['0']['#']);

            //Now, build the question_match_SUB record structure
            $match_sub = new stdClass;
            $match_sub->question = $new_question_id;
            $match_sub->code = isset($mat_info['#']['CODE']['0']['#'])?backup_todb($mat_info['#']['CODE']['0']['#']):'';
            if (!$match_sub->code) {
                $match_sub->code = $oldid;
            }
            $match_sub->questiontext = backup_todb($mat_info['#']['QUESTIONTEXT']['0']['#']);
            $match_sub->answertext = backup_todb($mat_info['#']['ANSWERTEXT']['0']['#']);

            //The structure is equal to the db, so insert the question_match_sub
            $newid = insert_record ("question_match_sub",$match_sub);

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

            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,"question_match_sub",$oldid,
                             $newid);
                //We have a new match_sub, append it to subquestions_field
                if ($in_first) {
                    $subquestions_field .= $newid;
                    $in_first = false;
                } else {
                    $subquestions_field .= ",".$newid;
                }
            } else {
                $status = false;
            }
        }

        //We have created every match_sub, now create the match
        $match = new stdClass;
        $match->question = $new_question_id;
        $match->subquestions = $subquestions_field;

        // Get the shuffleanswers option, if it is there.
        if (!empty($info['#']['MATCHOPTIONS']['0']['#']['SHUFFLEANSWERS'])) {
            $match->shuffleanswers = backup_todb($info['#']['MATCHOPTIONS']['0']['#']['SHUFFLEANSWERS']['0']['#']);
        } else {
            $match->shuffleanswers = 1;
        }

        //The structure is equal to the db, so insert the question_match_sub
        $newid = insert_record ("question_match",$match);

        if (!$newid) {
            $status = false;
        }

        return $status;
    }

    public function restore_map($old_question_id,$new_question_id,$info,$restore) {

        $status = true;

        //Get the matchs array
        $matchs = $info['#']['MATCHS']['0']['#']['MATCH'];

        //We have to build the subquestions field (a list of match_sub id)
        $subquestions_field = "";
        $in_first = true;

        //Iterate over matchs
        for($i = 0; $i < sizeof($matchs); $i++) {
            $mat_info = $matchs[$i];

            //We'll need this later!!
            $oldid = backup_todb($mat_info['#']['ID']['0']['#']);

            //Now, build the question_match_SUB record structure
            $match_sub->question = $new_question_id;
            $match_sub->questiontext = backup_todb($mat_info['#']['QUESTIONTEXT']['0']['#']);
            $match_sub->answertext = backup_todb($mat_info['#']['ANSWERTEXT']['0']['#']);

            //If we are in this method is because the question exists in DB, so its
            //match_sub must exist too.
            //Now, we are going to look for that match_sub in DB and to create the
            //mappings in backup_ids to use them later where restoring states (user level).

            //Get the match_sub from DB (by question, questiontext and answertext)
            $db_match_sub = get_record ("question_match_sub","question",$new_question_id,
                                                      "questiontext",$match_sub->questiontext,
                                                      "answertext",$match_sub->answertext);
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

            //We have the database match_sub, so update backup_ids
            if ($db_match_sub) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code,"question_match_sub",$oldid,
                             $db_match_sub->id);
            } else {
                $status = false;
            }
        }

        return $status;
    }

    public function restore_recode_answer($state, $restore) {

        //The answer is a comma separated list of hypen separated math_subs (for question and answer)
        $answer_field = "";
        $in_first = true;
        $tok = strtok($state->answer,",");
        while ($tok) {
            //Extract the match_sub for the question and the answer
            $exploded = explode("-",$tok);
            $match_question_id = $exploded[0];
            $match_answer_id = $exploded[1];
            //Get the match_sub from backup_ids (for the question)
            if (!$match_que = backup_getid($restore->backup_unique_code,"question_match_sub",$match_question_id)) {
                echo 'Could not recode question in question_match_sub '.$match_question_id.'<br />';
            } else {
                if ($in_first) {
                    $in_first = false;
                } else {
                    $answer_field .= ',';
                }
                $answer_field .= $match_que->new_id.'-'.$match_answer_id;
            }
            //check for next
            $tok = strtok(",");
        }
        return $answer_field;
    }

    /**
     * Decode links in question type specific tables.
     * @return bool success or failure.
     */
    public function decode_content_links_caller($questionids, $restore, &$i) {
        $status = true;

        // Decode links in the question_match_sub table.
        if ($subquestions = get_records_list('question_match_sub', 'question',
                implode(',',  $questionids), '', 'id, questiontext')) {

            foreach ($subquestions as $subquestion) {
                $questiontext = restore_decode_content_links_worker($subquestion->questiontext, $restore);
                if ($questiontext != $subquestion->questiontext) {
                    $subquestion->questiontext = addslashes($questiontext);
                    if (!update_record('question_match_sub', $subquestion)) {
                        $status = false;
                    }
                }

                // Do some output.
                if (++$i % 5 == 0 && !defined('RESTORE_SILENTLY')) {
                    echo ".";
                    if ($i % 100 == 0) {
                        echo "<br />";
                    }
                    backup_flush(300);
                }
            }
        }

        return $status;
    }

    public function find_file_links($question, $courseid){
        // find links in the question_match_sub table.
        $urls = array();
        if (isset($question->options->subquestions)){
            foreach ($question->options->subquestions as $subquestion) {
                $urls += question_find_file_links_from_html($subquestion->questiontext, $courseid);
            }

            //set all the values of the array to the question object
            if ($urls){
                $urls = array_combine(array_keys($urls), array_fill(0, count($urls), array($question->id)));
            }
        }
        $urls = array_merge_recursive($urls, parent::find_file_links($question, $courseid));

        return $urls;
    }

    public function replace_file_links($question, $fromcourseid, $tocourseid, $url, $destination){
        parent::replace_file_links($question, $fromcourseid, $tocourseid, $url, $destination);
        // replace links in the question_match_sub table.
        if (isset($question->options->subquestions)){
            foreach ($question->options->subquestions as $subquestion) {
                $subquestionchanged = false;
                $subquestion->questiontext = question_replace_file_links_in_html($subquestion->questiontext, $fromcourseid, $tocourseid, $url, $destination, $subquestionchanged);
                if ($subquestionchanged){//need to update rec in db
                    if (!update_record('question_match_sub', addslashes_recursive($subquestion))) {
                        error('Couldn\'t update \'question_match_sub\' record '.$subquestion->id);
                    }

                }
            }
        }
    }

    /**
     * Runs all the code required to set up and save an essay question for testing purposes.
     * Alternate DB table prefix may be used to facilitate data deletion.
     */
    public function generate_test($name, $courseid = null) {
        list($form, $question) = parent::generate_test($name, $courseid);
        $form->shuffleanswers = 1;
        $form->noanswers = 3;
        $form->subquestions = array('cat', 'dog', 'cow');
        $form->subanswers = array('feline', 'canine', 'bovine');

        if ($courseid) {
            $course = get_record('course', 'id', $courseid);
        }

        return $this->save_question($question, $form, $course);
    }
}
question_register_questiontype(question_bank::get_qtype('match'));
