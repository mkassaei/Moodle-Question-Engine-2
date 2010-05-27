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
 * OU multiple response question type class.
 *
 * @package qtype_oumultiresponse
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');


/**
 * This questions type is like the standard multiplechoice question type, but
 * with these differences:
 *
 * 1. The focus is just on the multiple response case.
 *
 * 2. The correct answer is just indicated on the editing form by a indicating
 * which choices are correct. There is no complex but flexible scoring system.
 *
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_oumultiresponse extends question_type {
    public function has_html_answers() {
        return true;
    }

    public function get_question_options(&$question) {
        if (!$question->options = get_record('question_oumultiresponse', 'questionid', $question->id)) {
            notify('Error: Missing question options for oumultiresponse question'.$question->id.'!');
            return false;
        }

        parent::get_question_options($question);

        return true;
    }

    public function save_question_options($question) {
        $result = new stdClass;
        if (!$oldanswers = get_records("question_answers", "question", $question->id, "id ASC")) {
            $oldanswers = array();
        }

        // following hack to check at least two answers exist
        $answercount = 0;
        foreach ($question->answer as $key=>$dataanswer) {
            if (trim($dataanswer) != "") {
                $answercount++;
            }
        }
        $answercount += count($oldanswers);
        if ($answercount < 2) { // check there are at lest 2 answers for multiple choice
            $result->notice = get_string("notenoughanswers", "qtype_oumultiresponse", "2");
            return $result;
        }

        // Insert all the new answers
        $answers = array();

        foreach ($question->answer as $key => $dataanswer) {
            if (trim($dataanswer) != "") {
                $correctanswer = empty($question->correctanswer[$key])?0:1;
                if ($answer = array_shift($oldanswers)) {  // Existing answer, so reuse it
                    $answer->answer     = $dataanswer;
                    $answer->fraction   = !empty($correctanswer);
                    $answer->feedback   = $question->feedback[$key];
                    if (!update_record("question_answers", $answer)) {
                        $result->error = "Could not update quiz answer! (id=$answer->id)";
                        return $result;
                    }
                } else {
                    unset($answer);
                    $answer->answer   = $dataanswer;
                    $answer->question = $question->id;
                    $answer->fraction = !empty($correctanswer);
                    $answer->feedback = $question->feedback[$key];
                    if (!$answer->id = insert_record("question_answers", $answer)) {
                        $result->error = "Could not insert quiz answer! ";
                        return $result;
                    }
                }
                $answers[] = $answer->id;
            }
        }

        // delete old answer records
        if (!empty($oldanswers)) {
            foreach($oldanswers as $oa) {
                delete_records('question_answers', 'id', $oa->id);
            }
        }

        $update = true;
        $options = get_record('question_oumultiresponse', 'questionid', $question->id);
        if (!$options) {
            $update = false;
            $options = new stdClass;
            $options->questionid = $question->id;
        }

        $options->answernumbering = $question->answernumbering;
        $options->shuffleanswers = $question->shuffleanswers;

        $options->correctfeedback = trim($question->correctfeedback);
        $options->partiallycorrectfeedback = trim($question->partiallycorrectfeedback);
        $options->shownumcorrect = !empty($question->shownumcorrect);
        $options->incorrectfeedback = trim($question->incorrectfeedback);

        if ($update) {
            if (!update_record("question_oumultiresponse", $options)) {
                $result->error = "Could not update quiz oumultiresponse options! (id=$options->id)";
                return $result;
            }
        } else {
            if (!insert_record("question_oumultiresponse", $options)) {
                $result->error = "Could not insert quiz oumultiresponse options!";
                return $result;
            }
        }

        $this->save_hints($question, true);

        return true;
    }

    public function save_hints($formdata, $withparts = false) {
        delete_records('question_hints', 'questionid', $formdata->id);

        if (!empty($formdata->hint)) {
            $numhints = max(array_keys($formdata->hint)) + 1;
        } else {
            $numhints = 0;
        }

        if ($withparts) {
            if (!empty($formdata->hintclearwrong)) {
                $numclears = max(array_keys($formdata->hintclearwrong)) + 1;
            } else {
                $numclears = 0;
            }
            if (!empty($formdata->hintshownumcorrect)) {
                $numshows = max(array_keys($formdata->hintshownumcorrect)) + 1;
            } else {
                $numshows = 0;
            }
            $numhints = max($numhints, $numclears, $numshows);
        }

        if (!empty($formdata->hintshowchoicefeedback)) {
            $numshowfeedbacks = max(array_keys($formdata->hintshowchoicefeedback)) + 1;
        } else {
            $numshowfeedbacks = 0;
        }
        $numhints = max($numhints, $numshowfeedbacks);

        for ($i = 0; $i < $numhints; $i += 1) {
            $hint = new stdClass;
            $hint->hint = $formdata->hint[$i];
            $hint->questionid = $formdata->id;

            if (html_is_blank($hint->hint)) {
                $hint->hint = '';
            }

            if ($withparts) {
                $hint->clearwrong = !empty($formdata->hintclearwrong[$i]);
                $hint->shownumcorrect = !empty($formdata->hintshownumcorrect[$i]);
            }

            $hint->options = !empty($formdata->hintshowchoicefeedback[$i]);

            if (empty($hint->hint) && empty($hint->clearwrong) &&
                    empty($hint->shownumcorrect) && empty($hint->showchoicefeedback)) {
                continue;
            }

            insert_record('question_hints', $hint);
        }
    }

    protected function make_hint($hint) {
        return qtype_oumultiresponse_hint::load_from_record($hint);
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->answernumbering = $questiondata->options->answernumbering;

        $question->correctfeedback = $questiondata->options->correctfeedback;
        $question->partiallycorrectfeedback = $questiondata->options->partiallycorrectfeedback;
        $question->incorrectfeedback = $questiondata->options->incorrectfeedback;
        $question->shownumcorrect = $questiondata->options->shownumcorrect;

        $this->initialise_question_answers($question, $questiondata);
    }

    /**
     * Deletes question from the question-type specific tables
     *
     * @param object $question  The question being deleted
     * @return boolean Success/Failure
     */
    public function delete_question($questionid) {
        delete_records('question_oumultiresponse', 'questionid', $questionid);
        return parent::delete_question($questionid);
    }

    /**
     * This method returns an arry of all incorrect answes from the question_answers table
     * @param $question object
     * @param $correctanswer int
     * @return array (an arry of answers the question_answers table)
     */
    private function show_feedback_to_responses($question, $state, $showfeedbacktoresponses = 0){
        $correctanswers = $this->get_correct_answers($question);
        $numberofcorrectanswers = count($correctanswers);

        $checkedresponses = $state->responses;
        $numberofcheckedresponses = count($checkedresponses);

        $correctresponses = $this->get_student_correct_responses($question, $state);
        $numberofcorrectresponses = count($correctresponses);

        $state->_showfeedback = false;
        if ($showfeedbacktoresponses == 1) {
            if ($numberofcheckedresponses <= $numberofcorrectanswers) {
                $state->_showfeedback = true;
                return null;
            } else {
                return get_string('statetoomanyoptions', 'qtype_oumultiresponse');
            }
        }
        return null;
    }

    function replace_char_at(&$string, $pos, $newchar) {
        return substr($string, 0, $pos) . $newchar . substr($string, $pos + 1);
    }

    /**
     * Implement the scoring rules.
     *
     * @param array $response_history an array $answerid -> string of 1s and 0s. The 1s and 0s are
     * the history of which tries this answer was selected on, so 011 means not selected on the
     * first try, then selected on the second and third tries. All the strings must be the same length.
     * @param array $answers $question->options->answers, that is an array $answerid => $answer,
     * where $answer->fraction is 0 or 1. The key fields are
     * @return float the score.
     */
    public function grade_computation($response_history, $answers, $penalty, $questionnumtries) {
        // First we reverse the strings to get the most recent responses to the start, then
        // distinguish right and wrong by replacing 1 with 2 for right answers.
        $workspace = array();
        $numright = 0;
        foreach ($response_history as $id => $string) {
            $workspace[$id] = strrev($string);
            if ($answers[$id]->fraction > 0.99) {
                $workspace[$id] = str_replace('1', '2', $workspace[$id]);
                $numright++;
            }
        }

        // Now we sort which should put answers more likely to help the candidate near the bottom of
        // workspace.
        sort($workspace);

        // Now, for each try we check to see if too many options were selected. If so, we
        // unselect correct answers in that, starting from the top of workspace - the ones that are
        // likely to turn out least favourable in the end.
        $actualnumtries = strlen(reset($workspace));
        for ($try = 0; $try < $actualnumtries; $try++) {
            $numselected = 0;
            foreach ($workspace as $string) {
                if (substr($string, $try, 1) != '0') {
                    $numselected++;
                }
            }
            if ($numselected > $numright) {
                $numtoclear = $numselected - $numright;
                $newworkspace = array();
                foreach ($workspace as $string) {
                    if (substr($string, $try, 1) == '2' && $numtoclear > 0) {
                        $string = $this->replace_char_at($string, $try, '0');
                        $numtoclear--;
                    }
                    $newworkspace[] = $string;
                }
                $workspace = $newworkspace;
            }
        }

        // Now convert each string into a score. The score depends on the number of 2s at the start
        // of the string. Add extra 2s if the student got it right in fewer than the maximum
        // permitted number of tries.
        $triesnotused = $questionnumtries - $actualnumtries;
        foreach ($workspace as $string) {
            $string = str_replace('1', '0', $string); // Turn any remaining 1s to 0s for convinience.
            $num2s = strpos($string . '0', '0');
            if ($num2s > 0) {
                $num2s += $triesnotused;
                $scores[] = 1 / $numright * (1 - $penalty * ($questionnumtries - $num2s));
            } else {
                $scores[] = 0;
            }
        }

        // Finally, sum the scores
        return array_sum($scores);
    }

    private function update_response_histories($question, $state) {
        foreach ($question->options->answers as $id => $notused){
            if (in_array($id, $state->responses)) {
                $newstate = '1';
            } else {
                $newstate = '0';
            }
            if (!isset($state->responsehistories[$id])) {
                $state->responsehistories[$id] = $newstate;
            } else {
                $oldhistory = $state->responsehistories[$id];
                $state->responsehistories[$id] =
                        $this->replace_char_at($oldhistory, strlen($oldhistory) - 1, $newstate);
            }
        }
    }

    // Add another state at the end of the response history, repeating the current state.
    private function extend_response_histories($question, $state) {
        foreach ($question->options->answers as $id => $notused){
            $state->responsehistories[$id] .= substr($state->responsehistories[$id], -1);
        }
    }

    public function print_question_formulation_and_controls(&$question, &$state, $cmoptions, $options) {
        global $CFG;

        $adaptive = ($cmoptions->optionflags & QUESTION_ADAPTIVE);
        //get all answers
        $answers = $this->get_all_answers($question);
        //$totalnumberofanswers = count($answers);

        //get correct answers
        $correctanswers = $this->get_correct_answers($question);
        $numberofcorrectanswers = count($correctanswers);

        //get correct responses
        $correctresponses = $this->get_student_correct_responses($question, $state);
        $numberofcorrectresponses = count($correctresponses);

        //initialise variables
        $statenumberofcorrectresponses = 0;
        $showfeedbacktoresponses = 0;
        $clearincorrectresponses = 0;

        //get the hint and the rest
        if (!is_null($hint = get_hint($question, $state, $cmoptions, true))) {
            $statenumberofcorrectresponses = $hint[1];
            $showfeedbacktoresponses = $hint[2];
            $clearincorrectresponses = $hint[3];
        }

        $readonly = empty($options->readonly) ? '' : 'disabled="disabled"';

        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->para = false;

        // Print formulation
        $questiontext = format_text($question->questiontext, $question->questiontextformat, $formatoptions, $cmoptions->course);
        $image = get_question_image($question);
        //$answerprompt = ($numberofcorrectanswers == 1) ? get_string('singleanswer', 'quiz') : get_string('multipleanswers', 'quiz');
        $answerprompt = get_string('multipleanswers', 'quiz');

        // Print each answer in a separate row
        foreach ($state->options->order as $key => $aid) {
            $answer = &$answers[$aid];
            $att = $this->get_attributes($question, $state, $aid, $numberofcorrectanswers);
            $name = $att['name'];
            $type = $att['type'];
            $checked = $att['checked'];
            $chosen = $att['chosen'];

            if ($adaptive){
                //state number of correct responses
                $state->_statenumberofcorrectresponses = $this->state_number_of_correct_responses($question, $state, $statenumberofcorrectresponses);

                //show feedback to correct responses
                $state->_showfeedbacktoresponses = $this->show_feedback_to_responses($question, $state, $showfeedbacktoresponses);

                //clear incorrect responses
                $state->_clearincorrectresponses = $this->clear_incorrect_responses($question, $state, $aid, $checked, $clearincorrectresponses);
            }
            $a = new stdClass;
            $a->id   = $question->name_prefix . $aid;
            $a->class = '';
            $a->feedbackimg = '';

            // Print the control
            $a->control = "<input $readonly id=\"$a->id\" $name $checked $type value=\"$aid\" />";

            if ($options->correct_responses && $answer->fraction == 1) {
                $a->class = question_get_feedback_class(1);
            }
            if (($options->feedback && $chosen) || $options->correct_responses) {
                $a->feedbackimg = question_get_feedback_image($answer->fraction, $chosen && $options->feedback);
            }

            // Print the answer text
            $a->text = $this->number_in_style($key, $question->options->answernumbering) .
                    format_text($answer->answer, FORMAT_MOODLE, $formatoptions, $cmoptions->course);

            // Print feedback if feedback is on
            if (($options->feedback || $options->correct_responses) && ($checked)) {
                $a->feedback = $this->get_feedback($question, $state, $answer->feedback, $formatoptions, $cmoptions);
            } else {
                $a->feedback = '';
            }
            $anss[] = clone($a);
        }
        $feedback = $this->get_overall_feedback($question, $state, $cmoptions, $options, $formatoptions);
        $this->display_html($adaptive, $questiontext, $image, $answerprompt, $anss, $readonly, $feedback, $question, $state, $cmoptions, $options);
    }

    /**
     * @param object $question
     * @param object $state
     * @param object $cmoption
     *
     */
    public function grade_responses(&$question, &$state, $cmoptions) {
        $adaptive = $cmoptions->optionflags;
        $this->quiz = $cmoptions;
        if($state->event == QUESTION_EVENTOPEN){
            return true;
        }

        $this->update_response_histories($question, $state);
        $iscorrect = $this->is_correct($question, $state, $cmoptions, true);
        $state->sumpenalty = 0;

        // mark the state as graded
        $state->event = ($state->event == QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;

        if ($state->event == QUESTION_EVENTGRADE){
            if($iscorrect || $state->triesleft == 1){
                $state->event = QUESTION_EVENTCLOSEANDGRADE;
            } else {
                if ($adaptive) {
                    $this->extend_response_histories($question, $state);
                }
            }
        }

        //set grade
        if(!$adaptive || $state->event == QUESTION_EVENTCLOSEANDGRADE) {
            $grade = $this->grade_computation($state->responsehistories, $question->options->answers,
                    $question->penalty, $this->get_num_tries($question, $state, $cmoptions));
            $grade = min(max($grade, 0.0), 1.0) * $question->maxgrade ; // Restrict grade to between 0 and 1 in case of rounding errors.
            if ($iscorrect) {
                $state->raw_grade = $question->maxgrade;
                $state->sumpenalty = $question->maxgrade - $grade;
            } else {
                $state->raw_grade = $grade;
                $state->sumpenalty = 0.0;
            }
        }
        return true;
    }

    /**
     * For random question type return empty string which means won't calculate.
     * @param object $question
     * @return mixed either a integer score out of 1 that the average random
     * guess by a student might give or an empty string which means will not
     * calculate.
     */
    function get_random_guess_score($question) {
        // TODO
        // corrected formula for this.
        return count($this->get_correct_answers($question))/count($this->get_all_answers($question));
    }

    function import_from_xml($data, $question, $format, $extra=null) {
        if (!isset($data['@']['type']) || $data['@']['type'] != 'oumultiresponse') {
            return false;
        }

        $question = $format->import_headers($data);
        $question->qtype = 'oumultiresponse';

        $question->shuffleanswers = $format->trans_single(
                $format->getpath($data, array('#', 'shuffleanswers', 0, '#'), 1));
        $question->answernumbering = $format->getpath($data,
                array('#', 'answernumbering', 0, '#'), 'abc');

        $format->import_overall_feedback($question, $data, true);

        // Run through the answers
        $answers = $data['#']['answer'];
        foreach ($answers as $answer) {
            $ans = $format->import_answer($answer);
            $question->answer[] = $ans->answer;
            $question->correctanswer[] = !empty($ans->fraction);
            $question->feedback[] = $ans->feedback;

            // Backwards compatibility.
            if (array_key_exists('correctanswer', $answer['#'])) {
                $key = end(array_keys($question->correctanswer));
                $question->correctanswer[$key] = $format->getpath($answer,
                        array('#', 'correctanswer', 0, '#'), 0);
            }
        }

        $format->import_hints($question, $data, true, true);

        // Get extra choicefeedback setting from each hint.
        foreach ($question->hintoptions as $key => $options) {
            $question->hintshowchoicefeedback[$key] = !empty($options);
        }

        return $question;
    }

    function export_to_xml($question, $format, $extra = null) {
        $output = '';

        $output .= "    <shuffleanswers>" . $format->get_single($question->options->shuffleanswers) . "</shuffleanswers>\n";
        $output .= "    <answernumbering>{$question->options->answernumbering}</answernumbering>\n";

        $output .= $format->write_overall_feedback($question->options);
        $output .= $format->write_answers($question->options->answers);

        return $output;
    }

    /**
     * Backup the datAppendIteratordeleteDatagetNodePathDOMElementgetElementsByTagNameNSDOMImplementationSourcea in the question (This is used in question/backuplib.php)
     *
     */
    public function backup($bf,$preferences,$question,$level=6) {
        $status = true;
        $oumultiresponses = get_records("question_oumultiresponse","questionid",$question,"id");

        //If there are oumultiresponses
        if ($oumultiresponses) {
            //Iterate over each oumultiresponse
            foreach ($oumultiresponses as $oumultiresponse) {
                $status = fwrite ($bf,start_tag("OUMULTIRESPONSE",$level,true));
                //Print oumultiresponse contents
                fwrite ($bf,full_tag("ANSWERNUMBERING",$level+1,false,$oumultiresponse->answernumbering));
                fwrite ($bf,full_tag("SHUFFLEANSWERS",$level+1,false,$oumultiresponse->shuffleanswers));
                fwrite ($bf,full_tag("CORRECTFEEDBACK",$level+1,false,$oumultiresponse->correctfeedback));
                fwrite ($bf,full_tag("PARTIALLYCORRECTFEEDBACK",$level+1,false,$oumultiresponse->partiallycorrectfeedback));
                fwrite ($bf,full_tag("INCORRECTFEEDBACK",$level+1,false,$oumultiresponse->incorrectfeedback));
                fwrite ($bf,full_tag("SHOWNUMCORRECT",$level+1,false,$oumultiresponse->shownumcorrect));
                $status = fwrite ($bf,end_tag("OUMULTIRESPONSE",$level,true));
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
    function restore($old_question_id,$new_question_id,$info,$restore) {
        $status = true;

        //Get the oumultiresponses array
        $oumultiresponses = $info['#']['OUMULTIRESPONSE'];

        //Iterate over oumultiresponses
        for($i = 0; $i < sizeof($oumultiresponses); $i++) {
            $mul_info = $oumultiresponses[$i];

            //Now, build the question_oumultiresponse record structure
            $oumultiresponse = new stdClass;
            $oumultiresponse->questionid = $new_question_id;
            $oumultiresponse->answernumbering = isset($mul_info['#']['ANSWERNUMBERING']['0']['#'])?backup_todb($mul_info['#']['ANSWERNUMBERING']['0']['#']):'';
            $oumultiresponse->shuffleanswers = isset($mul_info['#']['SHUFFLEANSWERS']['0']['#'])?backup_todb($mul_info['#']['SHUFFLEANSWERS']['0']['#']):'';
            if (array_key_exists("CORRECTFEEDBACK", $mul_info['#'])) {
                $oumultiresponse->correctfeedback = backup_todb($mul_info['#']['CORRECTFEEDBACK']['0']['#']);
            } else {
                $oumultiresponse->correctfeedback = '';
            }
            if (array_key_exists("PARTIALLYCORRECTFEEDBACK", $mul_info['#'])) {
                $oumultiresponse->partiallycorrectfeedback = backup_todb($mul_info['#']['PARTIALLYCORRECTFEEDBACK']['0']['#']);
            } else {
                $oumultiresponse->partiallycorrectfeedback = '';
            }
            if (array_key_exists("INCORRECTFEEDBACK", $mul_info['#'])) {
                $oumultiresponse->incorrectfeedback = backup_todb($mul_info['#']['INCORRECTFEEDBACK']['0']['#']);
            } else {
                $oumultiresponse->incorrectfeedback = '';
            }
            $oumultiresponse->shownumcorrect = isset($mat_opt['#']['SHOWNUMCORRECT']['0']['#'])?backup_todb($mat_opt['#']['SHOWNUMCORRECT']['0']['#']):1;

            $status = insert_record('question_oumultiresponse', $oumultiresponse);
        }

        return $status;
    }

    function restore_recode_answer($state, $restore) {
        $pos = strpos($state->answer, ':');
        $order = array();
        $responses = array();
        if (false === $pos) { // No order of answers is given, so use the default
            if ($state->answer) {
                $responses = explode(',', $state->answer);
            }
        } else {
            $order = explode(',', substr($state->answer, 0, $pos));
            if ($responsestring = substr($state->answer, $pos + 1)) {
                $responses = explode(',', $responsestring);
            }
        }
        if ($order) {
            foreach ($order as $key => $oldansid) {
                $oldansid = $this->getAnswerIdFromStateAnswer($oldansid); // bug #6749
                $answer = backup_getid($restore->backup_unique_code,"question_answers",$oldansid);
                if ($answer) {
                    $order[$key] = $answer->new_id;
                } else {
                    echo 'Could not recode oumultiresponse answer id '.$oldansid.' for state '.$state->oldid.'<br />';
                }
            }
        }

        if ($responses) {
            foreach ($responses as $key => $oldansid) {
                $oldansid = $this->getAnswerIdFromStateAnswer($oldansid);// bug #6749
                $answer = backup_getid($restore->backup_unique_code,"question_answers",$oldansid);
                if ($answer) {
                    $responses[$key] = $answer->new_id;
                } else {
                    echo 'Could not recode oumultiresponse response answer id '.$oldansid.' for state '.$state->oldid.'<br />';
                }
            }
        }
        return implode(',', $order).':'.implode(',', $responses);
    }

    /**
     * Decode links in question type specific tables.
     * @return bool success or failure.
     */
    function decode_content_links_caller($questionids, $restore, &$i) {
        $status = true;
        // Decode links in the question_oumultiresponse table.
        if ($oumultiresponses = get_records_list('question_oumultiresponse', 'questionid',
                implode(',',  $questionids), '', 'id, correctfeedback, partiallycorrectfeedback, incorrectfeedback')) {

            foreach ($oumultiresponses as $oumultiresponse) {
                $correctfeedback = restore_decode_content_links_worker($oumultiresponse->correctfeedback, $restore);
                $partiallycorrectfeedback = restore_decode_content_links_worker($oumultiresponse->partiallycorrectfeedback, $restore);
                $incorrectfeedback = restore_decode_content_links_worker($oumultiresponse->incorrectfeedback, $restore);
                if ($correctfeedback != $oumultiresponse->correctfeedback ||
                        $partiallycorrectfeedback != $oumultiresponse->partiallycorrectfeedback ||
                        $incorrectfeedback != $oumultiresponse->incorrectfeedback) {
                    $subquestion->correctfeedback = addslashes($correctfeedback);
                    $subquestion->partiallycorrectfeedback = addslashes($partiallycorrectfeedback);
                    $subquestion->incorrectfeedback = addslashes($incorrectfeedback);
                    if (!update_record('question_oumultiresponse', $oumultiresponse)) {
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
        $urls = array();
        // find links in the answers table.
        $urls +=  question_find_file_links_from_html($question->options->correctfeedback, $courseid);
        $urls +=  question_find_file_links_from_html($question->options->partiallycorrectfeedback, $courseid);
        $urls +=  question_find_file_links_from_html($question->options->incorrectfeedback, $courseid);
        foreach ($question->options->answers as $answer) {
            $urls += question_find_file_links_from_html($answer->answer, $courseid);
        }
        //set all the values of the array to the question id
        if ($urls){
            $urls = array_combine(array_keys($urls), array_fill(0, count($urls), array($question->id)));
        }
        $urls = array_merge_recursive($urls, parent::find_file_links($question, $courseid));
        return $urls;
    }

    public function replace_file_links($question, $fromcourseid, $tocourseid, $url, $destination){
        parent::replace_file_links($question, $fromcourseid, $tocourseid, $url, $destination);
        // replace links in the question_match_sub table.
        $optionschanged = false;
        $question->options->correctfeedback = question_replace_file_links_in_html($question->options->correctfeedback, $fromcourseid, $tocourseid, $url, $destination, $optionschanged);
        $question->options->partiallycorrectfeedback  = question_replace_file_links_in_html($question->options->partiallycorrectfeedback, $fromcourseid, $tocourseid, $url, $destination, $optionschanged);
        $question->options->incorrectfeedback = question_replace_file_links_in_html($question->options->incorrectfeedback, $fromcourseid, $tocourseid, $url, $destination, $optionschanged);
        if ($optionschanged){
            if (!update_record('question_oumultiresponse', addslashes_recursive($question->options))) {
                error('Couldn\'t update \'question_oumultiresponse\' record '.$question->options->id);
            }
        }
        $answerchanged = false;
        foreach ($question->options->answers as $answer) {
            $answer->answer = question_replace_file_links_in_html($answer->answer, $fromcourseid, $tocourseid, $url, $destination, $answerchanged);
            if ($answerchanged){
                if (!update_record('question_answers', addslashes_recursive($answer))){
                    error('Couldn\'t update \'question_answers\' record '.$answer->id);
                }
            }
        }
    }
}


/**
 * An extension of {@link question_hint_with_parts} for oumultirespone questions
 * with an extra option for whether to show the feedback for each choice.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_oumultiresponse_hint extends question_hint_with_parts {
    /** @var boolean whether to show the feedback for each choice. */
    public $showchoicefeedback;

    /**
     * Constructor.
     * @param string $hint The hint text
     * @param boolean $shownumcorrect whether the number of right parts should be shown
     * @param boolean $clearwrong whether the wrong parts should be reset.
     * @param boolean $showchoicefeedback whether to show the feedback for each choice.
     */
    public function __construct($hint, $shownumcorrect, $clearwrong, $showchoicefeedback) {
        parent::__construct($hint, $shownumcorrect, $clearwrong);
        $this->showchoicefeedback = $showchoicefeedback;
    }

    /**
     * Create a basic hint from a row loaded from the question_hints table in the database.
     * @param object $row with $row->hint, ->shownumcorrect and ->clearwrong set.
     * @return question_hint_with_parts
     */
    public static function load_from_record($row) {
        return new question_hint_with_parts($row->hint, $row->shownumcorrect,
                $row->clearwrong, !empty($row->options));
    }
}
