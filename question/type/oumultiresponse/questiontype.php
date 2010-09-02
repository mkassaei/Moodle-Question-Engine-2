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
        $question->shuffleanswers = $questiondata->options->shuffleanswers;
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

    protected function get_num_correct_choices($questiondata) {
        $numright = 0;
        foreach ($questiondata->options->answers as $answer) {
            if (!question_state::graded_state_for_fraction($answer->fraction)->is_incorrect()) {
                $numright += 1;
            }
        }
        return $numright;
    }

    public function get_random_guess_score($questiondata) {
        // We compute the randome guess score here on the assumption we are using
        // the deferred feedback behaviour, and the question text tells the
        // student how many of the responses are correct.
        // Amazingly, the forumla for this works out to be
        // # correct choices / total # choices in all cases.
        return $this->get_num_correct_choices($questiondata) /
                count($questiondata->options->answers);
    }

    public function get_possible_responses($questiondata) {
        $numright = $this->get_num_correct_choices($questiondata);
        $parts = array();

        foreach ($questiondata->options->answers as $aid => $answer) {
            $parts[$aid] = array($aid =>
                    new question_possible_response($answer->answer, $answer->fraction / $numright));
        }

        return $parts;
    }

    public function import_from_xml($data, $question, $format, $extra=null) {
        if (!isset($data['@']['type']) || $data['@']['type'] != 'oumultiresponse') {
            return false;
        }

        $question = $format->import_headers($data);
        $question->qtype = 'oumultiresponse';

        $question->shuffleanswers = $format->trans_single(
                $format->getpath($data, array('#', 'shuffleanswers', 0, '#'), 1));
        $question->answernumbering = $format->getpath($data,
                array('#', 'answernumbering', 0, '#'), 'abc');

        $format->import_combined_feedback($question, $data, true);

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
        if (!empty($question->hintoptions)) {
            foreach ($question->hintoptions as $key => $options) {
                $question->hintshowchoicefeedback[$key] = !empty($options);
            }
        }

        return $question;
    }

    public function export_to_xml($question, $format, $extra = null) {
        $output = '';

        $output .= "    <shuffleanswers>" . $format->get_single($question->options->shuffleanswers) . "</shuffleanswers>\n";
        $output .= "    <answernumbering>{$question->options->answernumbering}</answernumbering>\n";

        $output .= $format->write_combined_feedback($question->options);
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
    public function restore($old_question_id,$new_question_id,$info,$restore) {
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

    public function restore_recode_answer($state, $restore) {
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
    public function decode_content_links_caller($questionids, $restore, &$i) {
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

    public function adjust_display_options(question_display_options $options) {
        parent::adjust_display_options($options);
        $options->suppresschoicefeedback = !$this->showchoicefeedback;
    }
}
