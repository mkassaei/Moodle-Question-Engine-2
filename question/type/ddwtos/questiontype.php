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
 * Question type class for the drag-and-drop words into sentences question type.
 *
 * @package qtype_ddwtos
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');


/**
 * The drag-and-drop words into sentences question type class.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddwtos extends question_type {

    protected function serialize_draggroup_infinite($draggroup, $infinite){
        $output = new stdClass;
        $output->draggroup = $draggroup;
        $output->infinite = $infinite;
        return serialize($output);
    }

    public function save_question_options($question) {
        $result = new stdClass;

        if (!$oldanswers = get_records('question_answers', 'question', $question->id, 'id ASC')) {
            $oldanswers = array();
        }

        // Insert all the new answers
        foreach ($question->choices as $key => $choice) {

            if (trim($choice['answer']) == '') {
                continue;
            }

            $feedback = addslashes($this->serialize_draggroup_infinite(
                    $choice['draggroup'], !empty($choice['infinite'])));

            if ($answer = array_shift($oldanswers)) {  // Existing answer, so reuse it
                $answer->answer = $choice['answer'];
                $answer->fraction = 0;
                $answer->feedback = $feedback;
                if (!update_record('question_answers', $answer)) {
                    $result->error = "Could not update drag and drop question answer! (id=$answer->id)";
                    return $result;
                }
            } else {
                $answer = new stdClass;
                $answer->answer = $choice['answer'];
                $answer->question = $question->id;
                $answer->fraction = 0;
                $answer->feedback = $feedback;
                if (!$answer->id = insert_record('question_answers', $answer)) {
                    $result->error = 'Could not insert drag and drop question answer!';
                    return $result;
                }
            }
        }

        // Delete old answer records
        if (!empty($oldanswers)) {
            foreach($oldanswers as $oa) {
                delete_records('question_answers', 'id', $oa->id);
            }
        }

        $update = true;
        $options = get_record('question_ddwtos', 'questionid', $question->id);
        if (!$options) {
            $update = false;
            $options = new stdClass;
            $options->questionid = $question->id;
        }

        $options->shuffleanswers = !empty($question->shuffleanswers);
        $options->correctfeedback = trim($question->correctfeedback);
        $options->partiallycorrectfeedback = trim($question->partiallycorrectfeedback);
        $options->shownumcorrect = !empty($question->shownumcorrect);
        $options->incorrectfeedback = trim($question->incorrectfeedback);

        if ($update) {
            if (!update_record('question_ddwtos', $options)) {
                $result->error = "Could not update question drag and drop wordsintosentences options! (id=$options->id)";
                return $result;
            }

        } else {
            if (!insert_record('question_ddwtos', $options)) {
                $result->error = 'Could not insert question drag and drop wordsintosentences options!';
                return $result;
            }
        }

        $this->save_hints($question, true);

        return true;
    }

    public function get_question_options($question) {
        // Get additional information from database and attach it to the question object
        if (!$question->options = get_record('question_ddwtos', 'questionid', $question->id)) {
            notify('Error: Missing question options for ou drag and drop wordsintosentences question'.$question->id.'!');
            return false;
        }

        parent::get_question_options($question);
        return true;
    }

    public function delete_question($questionid) {
        delete_records('question_ddwtos', 'questionid', $questionid);
        return parent::delete_question($questionid);
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);

        $question->shufflechoices = $questiondata->options->shuffleanswers;

        $question->correctfeedback = $questiondata->options->correctfeedback;
        $question->partiallycorrectfeedback = $questiondata->options->partiallycorrectfeedback;
        $question->incorrectfeedback = $questiondata->options->incorrectfeedback;
        $question->shownumcorrect = $questiondata->options->shownumcorrect;

        $question->choices = array();
        $choiceindexmap= array();

        // Store the choices in arrays by group.
        $i = 1;
        foreach ($questiondata->options->answers as $choicedata) {
            $options = unserialize($choicedata->feedback);
            $choice = new qtype_ddwtos_choice($choicedata->answer,
                    $options->draggroup, $options->infinite);

            if (array_key_exists($options->draggroup, $question->choices)) {
                $question->choices[$options->draggroup][] = $choice;
            } else {
                $question->choices[$options->draggroup][1] = $choice;
            }

            end($question->choices[$options->draggroup]);
            $choiceindexmap[$i] = array($options->draggroup,
                    key($question->choices[$options->draggroup]));
            $i += 1;
        }

        $question->places = array();
        $question->textfragments = array();
        $question->rightchoices = array();
        // Break up the question text, and store the fragments, places and right answers.

        $bits = preg_split('/\[\[(\d+)]]/', $question->questiontext, null, PREG_SPLIT_DELIM_CAPTURE);
        $question->textfragments[0] = array_shift($bits);
        $i = 1;

        while (!empty($bits)) {
            $choice = array_shift($bits);

            list($group, $choiceindex) = $choiceindexmap[$choice];
            $question->places[$i] = $group;
            $question->rightchoices[$i] = $choiceindex;

            $question->textfragments[$i] = array_shift($bits);
            $i += 1;
        }
    }

    protected function make_hint($hint) {
        return question_hint_with_parts::load_from_record($hint);
    }

    public function get_random_guess_score($questiondata) {
        $question = $this->make_question($questiondata);
        return $question->get_random_guess_score();
    }

    /* This method gets the choices (answers)
     * in a 2 dimentional array.
     *
     * @param object $question
     * @return array of groups
     */
    protected function get_array_of_choices($question) {
        $subquestions = $question->options->answers;
        $count = 0;
        foreach ($subquestions as $key=>$subquestion){
            $answers[$count]['id'] = $subquestion->id;
            $answers[$count]['answer'] = $subquestion->answer;
            $answers[$count]['fraction'] = $subquestion->fraction;
            $feedback = unserialize( $subquestion->feedback);
            $answers[$count]['draggroup'] = $feedback->draggroup;
            $answers[$count]['infinite'] = $feedback->infinite;
            $answers[$count]['choice'] = $count+1;
            ++$count;
        }
        return $answers;
    }

    /* This method gets the choices (answers) and sort them by groups
     * in a 2 dimentional array.
     *
     * @param object $question
     * @return array of groups
     */
    protected function get_array_of_groups($question, $state) {
        $answers = $this->get_array_of_choices($question);
        $arr = array();
        for($group=1;$group<count($answers);$group++){
            $players = $this->get_group_of_players ($question, $state, $answers, $group);
            if($players){
                $arr [$group]= $players;
            }
        }
        return $arr;
    }

    /* This method gets the correct answers in a 2 dimentional array.
     *
     * @param object $question
     * @return array of groups
     */
    protected function get_correct_answers($question){
        $arrayofchoices = $this->get_array_of_choices($question);
        $arrayofplaceholdeers = $this->get_array_of_placeholders($question);

        $correctplayeers = array();
        foreach($arrayofplaceholdeers as $ph){
            foreach($arrayofchoices as $key=>$choice){
                if(($key+1) == $ph){
                    $correctplayeers[]= $choice;
                }
            }
        }
        return $correctplayeers;
    }

    protected function get_array_of_placeholders($question) {
        $qtext = $question->questiontext;
        $error = '<b> ERROR</b>: Please check the form for this question. ';
        if(!$qtext){
            echo $error . 'The question text is empty!';
            return false;
        }

        //get the slots
        $slots = $this->getEmbeddedTextArray($question);

        if(!$slots){
            echo $error . 'The question text is not in the correct format!';
            return false;
        }

        $output = array();
        foreach ($slots as $slot){
            $output[]=substr($slot, 2, (strlen($slot)-4));//2 is for'[[' and 4 is for '[[]]'
        }
        return $output;
     }

    protected function get_group_of_players ($question, $state, $subquestions, $group){
        $goupofanswers=array();
        foreach($subquestions as $key=>$subquestion) {
            if($subquestion['draggroup'] == $group){
                $goupofanswers[] =  $subquestion;
            }
        }

        //shuffle answers within this group
        if ($question->options->shuffleanswers == 1) {
            srand($state->attempt);
            shuffle($goupofanswers);
        }
        return $goupofanswers;
    }

    public function get_possible_responses($questiondata) {
        $question = $this->make_question($questiondata);

        $parts = array();
        foreach ($question->places as $place => $group) {
            $choices = array();

            foreach ($question->choices[$group] as $i => $choice) {
                $choices[$i] = new question_possible_response(
                        html_to_text($question->format_text($choice->text), 0, false),
                        $question->rightchoices[$place] == $i);
            }
            $choices[null] = question_possible_response::no_response();

            $parts[$place] = $choices;
        }

        return $parts;
    }

    function import_from_xml($data, $question, $format, $extra=null) {
        if (!isset($data['@']['type']) || $data['@']['type'] != 'ddwtos') {
            return false;
        }

        $question = $format->import_headers($data);
        $question->qtype = 'ddwtos';

        $question->shuffleanswers = $format->trans_single(
                $format->getpath($data, array('#', 'shuffleanswers', 0, '#'), 1));

        if (!empty($data['#']['dragbox'])) {
            // Modern XML format.
            $dragboxes = $data['#']['dragbox'];
            $question->answer = array();
            $question->draggroup = array();
            $question->infinite = array();

            foreach ($data['#']['dragbox'] as $dragboxxml) {
                $question->choices[] = array(
                    'answer' => $format->getpath($dragboxxml, array('#', 'text', 0, '#'), '', true),
                    'draggroup' => $format->getpath($dragboxxml, array('#', 'group', 0, '#'), 1),
                    'infinite' => array_key_exists('infinite', $dragboxxml['#']),
                );
            }

        } else {
            // Legacy format containing PHP serialisation.
            foreach ($data['#']['answer'] as $answerxml) {
                $ans = $format->import_answer($answerxml);
                $options = unserialize(stripslashes($ans->feedback));
                $question->choices[] = array(
                    'answer' => $ans->answer,
                    'draggroup' => $options->draggroup,
                    'infinite' => $options->infinite,
                );
            }
        }

        $format->import_combined_feedback($question, $data, true);
        $format->import_hints($question, $data, true);

        return $question;
    }

    function export_to_xml($question, $format, $extra = null) {
        $output = '';

        $output .= '    <shuffleanswers>' . $question->options->shuffleanswers . "</shuffleanswers>\n";

        $output .= $format->write_combined_feedback($question->options);

        foreach ($question->options->answers as $answer) {
            $options = unserialize($answer->feedback);

            $output .= "    <dragbox>\n";
            $output .= $format->writetext($answer->answer, 3);
            $output .= "      <group>{$options->draggroup}</group>\n";
            if ($options->infinite) {
                $output .= "      <infinite/>\n";
            }
            $output .= "    </dragbox>\n";
        }

        return $output;
    }

    /*
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    public function backup($bf, $preferences, $question, $level = 6) {
        $status = true;
        $ddwtos = get_records("question_ddwtos", "questionid", $question, "id");

        //If there are ddwtos
        if ($ddwtos) {
            //Iterate over each ddwtos
            foreach ($ddwtos as $ddws) {
                $status = fwrite ($bf,start_tag("DDWORDSSENTENCES",$level,true));
                //Print oumultiresponse contents
                fwrite ($bf,full_tag("SHUFFLEANSWERS",$level+1,false,$ddws->shuffleanswers));
                fwrite ($bf,full_tag("CORRECTFEEDBACK",$level+1,false,$ddws->correctfeedback));
                fwrite ($bf,full_tag("PARTIALLYCORRECTFEEDBACK",$level+1,false,$ddws->partiallycorrectfeedback));
                fwrite ($bf,full_tag("INCORRECTFEEDBACK",$level+1,false,$ddws->incorrectfeedback));
                fwrite ($bf,full_tag("CORRECTRESPONSESFEEDBACK",$level+1,false,$ddws->correctresponsesfeedback));
                $status = fwrite ($bf,end_tag("DDWORDSSENTENCES",$level,true));
            }

            //Now print question_answers
            $status = question_backup_answers($bf,$preferences,$question);
        }
        return $status;
    }

    /**
     * Restores the data in the question (This is used in question/restorelib.php)
     *
     */
    public function restore($old_question_id,$new_question_id,$info,$restore) {
        $status = true;

        //Get the ddwtos array
        $ddwtos = $info['#']['DDWORDSSENTENCES'];

        //Iterate over oumultiresponses
        for($i = 0; $i < sizeof($ddwtos); $i++) {
            $mul_info = $ddwtos[$i];

            //Now, build the question_ddwtos record structure
            $ddwtos = new stdClass;
            $ddwtos->questionid = $new_question_id;
            $ddwtos->shuffleanswers = isset($mul_info['#']['SHUFFLEANSWERS']['0']['#'])?backup_todb($mul_info['#']['SHUFFLEANSWERS']['0']['#']):'';
            if (array_key_exists("CORRECTFEEDBACK", $mul_info['#'])) {
                $ddwtos->correctfeedback = backup_todb($mul_info['#']['CORRECTFEEDBACK']['0']['#']);
            } else {
                $ddwtos->correctfeedback = '';
            }
            if (array_key_exists("PARTIALLYCORRECTFEEDBACK", $mul_info['#'])) {
                $ddwtos->partiallycorrectfeedback = backup_todb($mul_info['#']['PARTIALLYCORRECTFEEDBACK']['0']['#']);
            } else {
                $ddwtos->partiallycorrectfeedback = '';
            }
            if (array_key_exists("INCORRECTFEEDBACK", $mul_info['#'])) {
                $ddwtos->incorrectfeedback = backup_todb($mul_info['#']['INCORRECTFEEDBACK']['0']['#']);
            } else {
                $ddwtos->incorrectfeedback = '';
            }
            $ddwtos->correctresponsesfeedback = isset($mul_info['#']['CORRECTRESPONSESFEEDBACK']['0']['#'])?backup_todb($mul_info['#']['CORRECTRESPONSESFEEDBACK']['0']['#']):'';

            $newid = insert_record ("question_ddwtos",$ddwtos);

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

            if (!$newid) {
                $status = false;
            }
        }
        return $status;
    }

}
