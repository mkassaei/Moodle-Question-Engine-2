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
 * Quiz statistics report class.
 *
 * @package quiz_statistics
 * @copyright 2008 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/mod/quiz/report/statistics/statistics_form.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/statistics_table.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/statistics_question_table.php');
require_once($CFG->dirroot . '/mod/quiz/report/statistics/qstats.php');

/**
 * The quiz statistics report provides summary information about each question in
 * a quiz, compared to the whole quiz. It also provides a drill-down to more
 * detailed information about each question.
 *
 * @copyright 2008 Jamie Pratt
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_statistics_report extends quiz_default_report {
    /** @var integer Time after which statistics are automatically recomputed. */
    const TIME_TO_CACHE_STATS = 900; // 15 minutes

    /** @var object instance of table class used for main questions stats table. */
    protected $table;

    /**
     * Display the report.
     */
    public function display($quiz, $cm, $course) {
        global $CFG, $QTYPES;

        $context = get_context_instance(CONTEXT_MODULE, $cm->id);

        // Work out hte display options.
        $download = optional_param('download', '', PARAM_ALPHA);
        $everything = optional_param('everything', 0, PARAM_BOOL);
        $recalculate = optional_param('recalculate', 0, PARAM_BOOL);
        // A qid paramter indicates we should display the detailed analysis of a question.
        $qid = optional_param('qid', 0, PARAM_INT);

        $pageoptions = array();
        $pageoptions['id'] = $cm->id;
        $pageoptions['mode'] = 'statistics';

        $reporturl = new moodle_url($CFG->wwwroot . '/mod/quiz/report.php', $pageoptions);

        $mform = new mod_quiz_report_statistics($reporturl);
        if ($fromform = $mform->get_data()) {
            $useallattempts = $fromform->useallattempts;
            if ($fromform->useallattempts) {
                set_user_preference('quiz_report_statistics_useallattempts', $fromform->useallattempts);
            } else {
                unset_user_preference('quiz_report_statistics_useallattempts');
            }

        } else {
            $useallattempts = get_user_preferences('quiz_report_statistics_useallattempts', 0);
        }

        // Find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        $nostudentsingroup = false; // True if a group is selected and there is no one in it.
        if (empty($currentgroup)) {
            $currentgroup = 0;
            $groupstudents = array();

        } else {
            // All users who can attempt quizzes and who are in the currently selected group
            $groupstudents = get_users_by_capability($context,
                    array('mod/quiz:reviewmyattempts', 'mod/quiz:attempt'),
                    '', '', '', '', $currentgroup, '', false);
            if (!$groupstudents) {
                $nostudentsingroup = true;
            }
        }

        // If recalculate was requeted, handle that.
        if ($recalculate && confirm_sesskey()) {
            $this->clear_cached_data($quiz->id, $currentgroup, $useallattempts);
            redirect($reporturl->out());
        }

        // Set up the main table.
        $this->table = new quiz_report_statistics_table();
        $filename = $course->shortname . '-' . format_string($quiz->name, true);
        $this->table->is_downloading($download, $filename, get_string('quizstructureanalysis', 'quiz_statistics'));

        // Print the page header stuff (if not downloading.
        if (!$this->table->is_downloading()) {
            $this->print_header_and_tabs($cm, $course, $quiz, 'statistics');

            if ($groupmode) {
                groups_print_activity_menu($cm, $reporturl->out());
                if ($currentgroup && !$groupstudents) {
                    notify(get_string('nostudentsingroup', 'quiz_statistics'));
                }
            }

            // Print display options form.
            $mform->set_data(array('useallattempts' => $useallattempts));
            $mform->display();
        }

        // Load the questions.
        $questions = quiz_report_get_significant_questions($quiz);
        $questionids = array();
        foreach ($questions as $question) {
            $questionids[] = $question->id;
        }
        $fullquestions = question_load_questions($questionids);
        foreach ($questions as $qnumber => $question) {
            $q = $fullquestions[$question->id];
            $q->maxmark = $question->maxmark;
            $q->qnumber = $qnumber;
            $q->number = $question->number;
            $questions[$qnumber] = $q;
        }

        // Get the data to be displayed.
        list($quizstats, $questions, $subquestions, $s)
                = $this->get_quiz_and_questions_stats($quiz, $currentgroup,
                        $nostudentsingroup, $useallattempts, $groupstudents, $questions);
        $quizinfo = $this->get_formatted_quiz_info_data($course, $cm, $quiz, $quizstats);

        if (!$this->table->is_downloading() && $s == 0) {
            print_heading(get_string('noattempts','quiz'));
        }

        // Set up the table, if there is data.
        if ($s) {
            $this->table->setup($quiz, $cm->id, $reporturl, $s);
        }

        if ($everything) { // Implies is downloading.
            // Overall report, then the analysis of each question.
            $this->download_quiz_info_table($quizinfo);

            $this->output_quiz_structure_analysis_table($s, $questions, $subquestions);

            if ($this->table->is_downloading() == 'xhtml') {
                $this->output_statistics_graph($quizstats->id, $s);
            }

            foreach ($questions as $question) {
                if ($question->qtype != 'random' && $QTYPES[$question->qtype]->show_analysis_of_responses()) {
                    $this->output_individual_question_data(
                            $quiz, $question, $reporturl, $quizstats);

                } else if (!empty($question->_stats->subquestions)) {
                    $subitemstodisplay = explode(',', $question->_stats->subquestions);
                    foreach ($subitemstodisplay as $subitemid) {
                        $this->output_individual_question_data(
                                $quiz, $subquestions[$subitemid], $reporturl, $quizstats);
                    }
                }
            }
            $this->table->export_class_instance()->finish_document();

        } else if ($qid) {
            // Report on an individual question. Implies not downloading.
            if (isset($questions[$qid])) {
                $thisquestion = $questions[$qid];
            } else if (isset($subquestions[$qid])) {
                $thisquestion = $subquestions[$qid];
            } else {
                print_error('questiondoesnotexist', 'question');
            }

            $this->output_individual_question_data(
                    $quiz, $thisquestion, $reporturl, $quizstats);

            // Back to overview link.
            print_box('<a href="' . $reporturl->out() . '">' .
                    get_string('backtoquizreport', 'quiz_statistics') . '</a>',
                    'boxaligncenter generalbox boxwidthnormal mdl-align');

        } else if ($this->table->is_downloading()) {
            // Downloading overview report.
            $this->download_quiz_info_table($quizinfo);
            $this->output_quiz_structure_analysis_table($s, $questions, $subquestions);
            $this->table->finish_output();

        } else {
            // On-screen display of overview report.
            print_heading(get_string('quizinformation', 'quiz_statistics'));
            echo $this->output_caching_info($quizstats, $quiz->id, $currentgroup, $groupstudents, $useallattempts, $reporturl);
            echo $this->everything_download_options();
            $this->output_quiz_info_table($quizinfo);
            print_heading(get_string('quizstructureanalysis', 'quiz_statistics'));
            $this->output_quiz_structure_analysis_table($s, $questions, $subquestions);
            $this->output_statistics_graph($quizstats->id, $s);
        }

        return true;
    }

    /**
     * Display the report on a single question.
     * @param object $quiz the quiz settings.
     * @param object $question the question to report on.
     * @param moodle_url $reporturl the URL to resisplay this report.
     * @param object $quizstats Holds the quiz statistics.
     */
    protected function output_individual_question_data($quiz, $question, $reporturl, $quizstats) {
        global $QTYPES;

        $qtable = new quiz_report_statistics_question_table($question->id);
        $downloadtype = $this->table->is_downloading();

        if (!$this->table->is_downloading()) {
            // On-screen display. Show a summary of the question's place in the quiz,
            // and the question statistics.
            $datumfromtable = $this->table->format_row($question);

            // Set up the question info table.
            $questioninfotable = new stdClass;
            $questioninfotable->align = array('center', 'center');
            $questioninfotable->width = '60%';
            $questioninfotable->class = 'generaltable titlesleft';

            $questioninfotable->data = array();
            $questioninfotable->data[] = array(get_string('modulename', 'quiz'), $quiz->name);
            $questioninfotable->data[] = array(get_string('questionname', 'quiz_statistics'), $question->name.'&nbsp;'.$datumfromtable['actions']);
            $questioninfotable->data[] = array(get_string('questiontype', 'quiz_statistics'), $datumfromtable['icon'].'&nbsp;'.get_string($question->qtype,'quiz').'&nbsp;'.$datumfromtable['icon']);
            $questioninfotable->data[] = array(get_string('positions', 'quiz_statistics'), $question->_stats->positions);

            // Set up the question statistics table.
            $questionstatstable = new stdClass;
            $questionstatstable->align = array('center', 'center');
            $questionstatstable->width = '60%';
            $questionstatstable->class = 'generaltable titlesleft';

            unset($datumfromtable['number']);
            unset($datumfromtable['icon']);
            $actions = $datumfromtable['actions'];
            unset($datumfromtable['actions']);
            unset($datumfromtable['name']);
            $labels = array('s' => get_string('attempts', 'quiz_statistics'),
                            'facility' => get_string('facility', 'quiz_statistics'),
                            'sd' => get_string('standarddeviationq', 'quiz_statistics'),
                            'random_guess_score' => get_string('random_guess_score', 'quiz_statistics'),
                            'intended_weight'=> get_string('intended_weight', 'quiz_statistics'),
                            'effective_weight'=> get_string('effective_weight', 'quiz_statistics'),
                            'discrimination_index'=> get_string('discrimination_index', 'quiz_statistics'),
                            'discriminative_efficiency'=> get_string('discriminative_efficiency', 'quiz_statistics'));
            foreach ($datumfromtable as $item => $value) {
                $questionstatstable->data[] = array($labels[$item], $value);
            }

            // Display the various bits.
            print_heading(get_string('questioninformation', 'quiz_statistics'));
            print_table($questioninfotable);

            print_box(format_text($question->questiontext, $question->questiontextformat).$actions, 'boxaligncenter generalbox boxwidthnormal mdl-align');

            print_heading(get_string('questionstatistics', 'quiz_statistics'));
            print_table($questionstatstable);

        } else {
            // Downloading.

            // Work out an appropriate title.
            $questiontabletitle = '"' . $question->name . '"';
            if (!empty($question->number)) {
                $questiontabletitle = '(' . $question->number . ') ' . $questiontabletitle;
            }
            $questiontabletitle = '<em>' . $questiontabletitle . '</em>';
            if ($downloadtype == 'xhtml') {
                $questiontabletitle = get_string('analysisofresponsesfor', 'quiz_statistics', $questiontabletitle);
            }

            // Set up the table.
            $qtable->export_class_instance($this->table->export_class_instance());
            $exportclass = $this->table->export_class_instance();
            $exportclass->start_table($questiontabletitle);
        }

        // Show the analysis of responses, if we can.
        if ($QTYPES[$question->qtype]->can_analyse_responses()) {
            if (!$this->table->is_downloading()) {
                print_heading(get_string('analysisofresponses', 'quiz_statistics'));
            }
            $teacherresponses = $QTYPES[$question->qtype]->get_possible_responses($question);
            $qtable->setup($reporturl, $question, count($teacherresponses) > 1);
            if ($this->table->is_downloading()) {
                $exportclass->output_headers($qtable->headers);
            }

            $responses = get_records_select('quiz_question_response_stats',
                    "quizstatisticsid = $quizstats->id AND questionid = $question->id",
                    'credit DESC, subqid ASC, aid ASC, rcount DESC');
            $responses = quiz_report_index_by_keys($responses, array('subqid', 'aid'), false);

            foreach ($responses as $subqid => $response) {
                foreach (array_keys($responses[$subqid]) as $aid) {
                    uasort($responses[$subqid][$aid], array('quiz_statistics_report', 'sort_answers'));
                }
                if (isset($responses[$subqid]['0'])) {
                    $wildcardresponse = new stdClass;
                    $wildcardresponse->answer = '*';
                    $wildcardresponse->credit = 0;
                    $teacherresponses[$subqid][0] = $wildcardresponse;
                }
            }
            $first = true;
            $subq = 0;
            foreach ($teacherresponses as $subqid => $tresponsesforsubq) {
                $subq++;
                $qhaswildcards = $QTYPES[$question->qtype]->has_wildcards_in_responses($question, $subqid);
                if (!$first) {
                    $qtable->add_separator();
                }
                uasort($tresponsesforsubq, array('quiz_statistics_report', 'sort_response_details'));
                foreach ($tresponsesforsubq as $aid => $teacherresponse) {
                    $teacherresponserow = new stdClass;
                    $teacherresponserow->response = $teacherresponse->answer;
                    $teacherresponserow->rcount = 0;
                    $teacherresponserow->subq = $subq;
                    $teacherresponserow->credit = $teacherresponse->credit;
                    if (isset($responses[$subqid][$aid])) {
                        $singleanswer = count($responses[$subqid][$aid])==1 &&
                                        ($responses[$subqid][$aid][0]->response == $teacherresponserow->response);
                        if (!$singleanswer && $qhaswildcards) {
                            $qtable->add_separator();
                        }
                        foreach ($responses[$subqid][$aid] as $response) {
                            $teacherresponserow->rcount += $response->rcount;
                        }
                        if ($aid!=0 || $qhaswildcards) {
                            $qtable->add_data_keyed($qtable->format_row($teacherresponserow));
                        }
                        if (!$singleanswer) {
                            foreach ($responses[$subqid][$aid] as $response) {
                                if (!$downloadtype || $downloadtype=='xhtml') {
                                    $indent = '&nbsp;&nbsp;&nbsp;&nbsp;';
                                } else {
                                    $indent = '    ';
                                }
                                $response->response = ($qhaswildcards?$indent:'').$response->response;
                                $response->subq = $subq;
                                if ((count($responses[$subqid][$aid])<2) || ($response->rcount > ($teacherresponserow->rcount / 10))) {
                                    $qtable->add_data_keyed($qtable->format_row($response));
                                }
                            }
                        }
                    } else {
                        $qtable->add_data_keyed($qtable->format_row($teacherresponserow));
                    }
                }
                $first = false;
            }
            $qtable->finish_output(!$this->table->is_downloading());
        }
    }

    /**
     * Output the table that lists all the questions in the quiz with their statistics.
     * @param integer $s number of attempts.
     * @param array $questions the questions in the quiz.
     * @param array $subquestions the subquestions of any random questions.
     */
    protected function output_quiz_structure_analysis_table($s, $questions, $subquestions) {
        if (!$s) {
            return;
        }

        foreach ($questions as $question) {
            // Output the data for this questions.
            $this->table->add_data_keyed($this->table->format_row($question));

            if (empty($question->_stats->subquestions)) {
                continue;
            }

            // And its subquestions, if it has any.
            $subitemstodisplay = explode(',', $question->_stats->subquestions);
            foreach ($subitemstodisplay as $subitemid) {
                $subquestions[$subitemid]->maxmark = $question->maxmark;
                $this->table->add_data_keyed($this->table->format_row($subquestions[$subitemid]));
            }
        }

        $this->table->finish_output(!$this->table->is_downloading());
    }

    protected function get_formatted_quiz_info_data($course, $cm, $quiz, $quizstats) {

        // You can edit this array to control which statistics are displayed.
        $todisplay = array('firstattemptscount' => '',
                    'allattemptscount' => '',
                    'firstattemptsavg' => 'summarks_as_percentage',
                    'allattemptsavg' => 'summarks_as_percentage',
                    'median' => 'summarks_as_percentage',
                    'standarddeviation' => 'summarks_as_percentage',
                    'skewness' => '',
                    'kurtosis' => '',
                    'cic' => 'number_format',
                    'errorratio' => 'number_format',
                    'standarderror' => 'summarks_as_percentage');

        // General information about the quiz.
        $quizinfo = array();
        $quizinfo[get_string('quizname', 'quiz_statistics')] = format_string($quiz->name);
        $quizinfo[get_string('coursename', 'quiz_statistics')] = format_string($course->fullname);
        if ($cm->idnumber) {
            $quizinfo[get_string('idnumbermod')] = $cm->idnumber;
        }
        if ($quiz->timeopen) {
            $quizinfo[get_string('quizopen', 'quiz')] = userdate($quiz->timeopen);
        }
        if ($quiz->timeclose) {
            $quizinfo[get_string('quizclose', 'quiz')] = userdate($quiz->timeclose);
        }
        if ($quiz->timeopen && $quiz->timeclose) {
            $quizinfo[get_string('duration', 'quiz_statistics')] = format_time($quiz->timeclose - $quiz->timeopen);
        }

        // The statistics.
        foreach ($todisplay as $property => $format) {
            if (!isset($quizstats->$property) || !isset($format[$property])) {
                continue;
            }
            $value = $quizstats->$property;

            switch ($format[$property]) {
                case 'summarks_as_percentage':
                    $formattedvalue = quiz_report_scale_summarks_as_percentage($value, $quiz);
                    break;
                case 'number_format':
                    $formattedvalue = quiz_format_grade($quiz, $value) . '%';
                    break;
                default:
                    $formattedvalue = $value;
            }

            $quizinfo[get_string($property, 'quiz_statistics',
                    $this->using_attempts_string($quizstats->allattempts))] = $formattedvalue;
        }

        return $quizinfo;
    }

    /**
     * Output the table of overall quiz statistics.
     * @param array $quizinfo as returned by {@link get_formatted_quiz_info_data()}.
     */
    protected function output_quiz_info_table($quizinfo) {

        $quizinfotable = new stdClass;
        $quizinfotable->align = array('center', 'center');
        $quizinfotable->width = '60%';
        $quizinfotable->class = 'generaltable titlesleft';
        $quizinfotable->data = array();

        foreach ($quizinfo as $heading => $value) {
             $quizinfotable->data[] = array($heading, $value);
        }

        print_table($quizinfotable);
    }

    /**
     * Download the table of overall quiz statistics.
     * @param array $quizinfo as returned by {@link get_formatted_quiz_info_data()}.
     */
    protected function download_quiz_info_table($quizinfo) {
        // XHTML download is a special case.
        if ($this->table->is_downloading() == 'xhtml') {
            print_heading(get_string('quizinformation', 'quiz_statistics'));
            $this->output_quiz_info_table($quizinfo);
            return;
        }

        // Reformat the data ready for output.
        $headers = array();
        $row = array();
        foreach ($quizinfo as $heading => $value) {
            $headers[] = $heading;
            $row[] = $value;
        }

        // Do the output.
        $exportclass = $this->table->export_class_instance();
        $exportclass->start_table(get_string('quizinformation', 'quiz_statistics'));
        $exportclass->output_headers($headers);
        $exportclass->add_data($row);
        $exportclass->finish_table();
    }

    /**
     * Output the HTML needed to show the statistics graph.
     * @param integer $quizstatsid the id of the statistics to show in the graph.
     */
    protected function output_statistics_graph($quizstatsid, $s) {
        global $CFG;

        if ($s == 0) {
            return;
        }

        $imageurl = $CFG->wwwroot . '/mod/quiz/report/statistics/statistics_graph.php?id=' .
                $quizstatsid;
        print_heading(get_string('statisticsreportgraph', 'quiz_statistics'));
        echo '<div class="mdl-align"><img src="' . $imageurl .
                '" alt="'.get_string('statisticsreportgraph', 'quiz_statistics') .
                '" /></div>';
    }

    /**
     * Return the stats data for when there are no stats to show.
     *
     * @param array $questions question definitions.
     * @return array with three elements:
     *      - integer $s Number of attempts included in the stats (0).
     *      - array $quizstats The statistics for overall attempt scores.
     *      - array $qstats The statistics for each question.
     */
    protected function get_emtpy_stats($questions) {
        $quizstats = new stdClass;
        $quizstats->firstattemptscount = 0;
        $quizstats->allattemptscount = 0;

        $qstats = new stdClass;
        $qstats->questions = $questions;
        $qstats->subquestions = array();
        $qstats->responses = array();

        return array(0, $quizstats, false);
    }

    /**
     * Compute the quiz statistics.
     *
     * @param object $quizid the quiz id.
     * @param integer $currentgroup the current group. 0 for none.
     * @param boolean $nostudentsingroup true if there a no students.
     * @param boolean $useallattempts use all attempts, or just first attempts.
     * @param array $groupstudents students in this group.
     * @param array $questions question definitions.
     * @return array with three elements:
     *      - integer $s Number of attempts included in the stats.
     *      - array $quizstats The statistics for overall attempt scores.
     *      - array $qstats The statistics for each question.
     */
    protected function compute_stats($quizid, $currentgroup, $nostudentsingroup,
            $useallattempts, $groupstudents, $questions) {

        // Calculating MEAN of marks for all attempts by students
        // http://docs.moodle.org/en/Development:Quiz_item_analysis_calculations_in_practise#Calculating_MEAN_of_grades_for_all_attempts_by_students
        if ($nostudentsingroup) {
            return $this->get_emtpy_stats($questions);
        }

        list($fromqa, $whereqa, $qaparams) = quiz_report_attempts_sql($quizid, $currentgroup, $groupstudents);

        $attempttotals = get_records_sql("
                SELECT
                    CASE WHEN attempt = 1 THEN 1 ELSE 0 END AS isfirst,
                    COUNT(1) AS countrecs,
                    SUM(sumgrades) AS total
                FROM $fromqa
                WHERE $whereqa
                GROUP BY attempt = 1");

        if (!$attempttotals) {
            return $this->get_emtpy_stats($questions);
        }

        $firstattempts = $attempttotals[1];

        $allattempts = new stdClass;
        if (isset($attempttotals[0])) {
            $allattempts->countrecs = $firstattempts->countrecs + $attempttotals[0]->countrecs;
            $allattempts->total = $firstattempts->total + $attempttotals[0]->total;
        } else {
            $allattempts->countrecs = $firstattempts->countrecs;
            $allattempts->total = $firstattempts->total;
        }

        if ($useallattempts) {
            $usingattempts = $allattempts;
            $usingattempts->sql = '';
        } else {
            $usingattempts = $firstattempts;
            $usingattempts->sql = 'AND quiza.attempt = 1 ';
        }

        $s = $usingattempts->countrecs;
        $summarksavg = $usingattempts->total / $usingattempts->countrecs;

        if ($s == 0) {
            return $this->get_emtpy_stats($questions);
        }

        $quizstats = new stdClass;
        $quizstats->allattempts = $useallattempts;
        $quizstats->firstattemptscount = $firstattempts->countrecs;
        $quizstats->allattemptscount = $allattempts->countrecs;
        $quizstats->firstattemptsavg = $firstattempts->total / $firstattempts->countrecs;
        $quizstats->allattemptsavg = $allattempts->total / $allattempts->countrecs;

        // Recalculate sql again this time possibly including test for first attempt.
        list($fromqa, $whereqa, $qaparams) = quiz_report_attempts_sql($quizid, $currentgroup, $groupstudents, $useallattempts);

        // Median
        if ($s % 2 == 0) {
            //even number of attempts
            $limitoffset = $s/2 - 1;
            $limit = 2;
        } else {
            $limitoffset = floor($s/2);
            $limit = 1;
        }
        $sql = 'SELECT id, sumgrades ' .
            'FROM ' .$fromqa.
            'WHERE ' .$whereqa.
            'ORDER BY sumgrades';

        if (!$medianmarks = get_records_sql_menu($sql, $limitoffset, $limit)) {
            print_error('errormedian', 'quiz_statistics');
        }

        $quizstats->median = array_sum($medianmarks) / count($medianmarks);
        if ($s > 1) {
            //fetch sum of squared, cubed and power 4d
            //differences between marks and mean mark
            $mean = $usingattempts->total / $s;
            $sql = "SELECT " .
                "SUM(POWER((quiza.sumgrades - $mean),2)) AS power2, " .
                "SUM(POWER((quiza.sumgrades - $mean),3)) AS power3, ".
                "SUM(POWER((quiza.sumgrades - $mean),4)) AS power4 ".
                'FROM ' .$fromqa.
                'WHERE ' .$whereqa;
            $params = array('mean1' => $mean, 'mean2' => $mean, 'mean3' => $mean)+$qaparams;

            if (!$powers = get_record_sql($sql)) {
                print_error('errorpowers', 'quiz_statistics');
            }

            // Standard_Deviation
            //see http://docs.moodle.org/en/Development:Quiz_item_analysis_calculations_in_practise#Standard_Deviation

            $quizstats->standarddeviation = sqrt($powers->power2 / ($s - 1));

            // Skewness
            if ($s > 2) {
                //see http://docs.moodle.org/en/Development:Quiz_item_analysis_calculations_in_practise#Skewness_and_Kurtosis
                $m2= $powers->power2 / $s;
                $m3= $powers->power3 / $s;
                $m4= $powers->power4 / $s;

                $k2= $s*$m2/($s-1);
                $k3= $s*$s*$m3/(($s-1)*($s-2));
                if ($k2) {
                    $quizstats->skewness = $k3 / (pow($k2, 3/2));
                }
            }

            // Kurtosis
            if ($s > 3) {
                $k4= $s*$s*((($s+1)*$m4)-(3*($s-1)*$m2*$m2))/(($s-1)*($s-2)*($s-3));
                if ($k2) {
                    $quizstats->kurtosis = $k4 / ($k2*$k2);
                }
            }
        }

        $qstats = new quiz_statistics_question_stats($questions, $s, $summarksavg);
        $qstats->get_records($quizid, $currentgroup, $groupstudents, $useallattempts);
        $qstats->process_states();
        $qstats->process_responses();

        if ($s > 1) {
            $p = count($qstats->questions); // No of positions
            if ($p > 1 && isset($k2)) {
                $quizstats->cic = (100 * $p / ($p -1)) * (1 - ($qstats->get_sum_of_mark_variance())/$k2);
                $quizstats->errorratio = 100 * sqrt(1-($quizstats->cic/100));
                $quizstats->standarderror = ($quizstats->errorratio * $quizstats->standarddeviation / 100);
            }
        }

        return array($s, $quizstats, $qstats);
    }

    /**
     * Load the cached statistics from the database.
     *
     * @param object $quiz the quiz settings
     * @param integer $currentgroup the current group. 0 for none.
     * @param boolean $nostudentsingroup true if there a no students.
     * @param boolean $useallattempts use all attempts, or just first attempts.
     * @param array $groupstudents students in this group.
     * @param array $questions question definitions.
     * @return array with 4 elements:
     *     - $quizstats The statistics for overall attempt scores.
     *     - $questions The questions, with an additional _stats field.
     *     - $subquestions The subquestions, if any, with an additional _stats field.
     *     - $s Number of attempts included in the stats.
     * If there is no cached data in the database, returns an array of four nulls.
     */
    protected function try_loading_cached_stats($quiz, $currentgroup,
            $nostudentsingroup, $useallattempts, $groupstudents, $questions) {

        $timemodified = time() - self::TIME_TO_CACHE_STATS;
        $quizstats = get_record_select('quiz_statistics',
                "quizid = $quiz->id AND groupid = $currentgroup AND allattempts = $useallattempts AND timemodified > $timemodified");

        if (!$quizstats) {
            // No cached data found.
            return array(null, $questions, null, null);
        }

        if ($useallattempts) {
            $s = $quizstats->allattemptscount;
        } else {
            $s = $quizstats->firstattemptscount;
        }

        $subquestions = array();
        $questionstats = get_records('quiz_question_statistics',
                'quizstatisticsid', $quizstats->id);

        $subquestionstats = array();
        foreach ($questionstats as $stat) {
            if ($stat->qnumber) {
                $questions[$stat->qnumber]->_stats = $stat;
            } else {
                $subquestionstats[$stat->questionid] = $stat;
            }
        }

        if (!empty($subquestionstats)) {
            $subqstofetch = array_keys($subquestionstats);
            $subquestions = question_load_questions($subqstofetch);
            foreach (array_keys($subquestions) as $subqid) {
                $subquestions[$subqid]->_stats = $subquestionstats[$subqid];
            }
        }

        return array($quizstats, $questions, $subquestions, $s);
    }

    /**
     * Store the statistics in the cache tables in the database.
     *
     * @param object $quizid the quiz id.
     * @param integer $currentgroup the current group. 0 for none.
     * @param boolean $useallattempts use all attempts, or just first attempts.
     * @param object $quizstats The statistics for overall attempt scores.
     * @param array $questions The questions, with an additional _stats field.
     * @param array $subquestions The subquestions, if any, with an additional _stats field.
     */
    protected function cache_stats($quizid, $currentgroup,
            $quizstats, $questions, $subquestions, $responses) {

        $toinsert = clone($quizstats);
        $toinsert->quizid = $quizid;
        $toinsert->groupid = $currentgroup;
        $toinsert->timemodified = time();

        // Fix up some dodgy data.
        if (isset($toinsert->errorratio) && is_nan($toinsert->errorratio)) {
            $toinsert->errorratio = null;
        }
        if (isset($toinsert->standarderror) && is_nan($toinsert->standarderror)) {
            $toinsert->standarderror = null;
        }

        // Store the data.
        $quizstats->id = insert_record('quiz_statistics', $toinsert);

        foreach ($questions as $question) {
            $question->_stats->quizstatisticsid = $quizstats->id;
            insert_record('quiz_question_statistics', $question->_stats, false);
        }

        foreach ($subquestions as $subquestion) {
            $subquestion->_stats->quizstatisticsid = $quizstats->id;
            insert_record('quiz_question_statistics', $subquestion->_stats, false);
        }

        foreach ($responses as $response) {
            $response->quizstatisticsid = $quizstats->id;
            insert_record('quiz_question_response_stats', $response, false);
        }
    }

    /**
     * Get the quiz and question statistics, either by loading the cached results,
     * or by recomputing them.
     *
     * @param object $quiz the quiz settings.
     * @param integer $currentgroup the current group. 0 for none.
     * @param boolean $nostudentsingroup true if there a no students.
     * @param boolean $useallattempts use all attempts, or just first attempts.
     * @param array $groupstudents students in this group.
     * @param array $questions question definitions.
     * @return array with 4 elements:
     *     - $quizstats The statistics for overall attempt scores.
     *     - $questions The questions, with an additional _stats field.
     *     - $subquestions The subquestions, if any, with an additional _stats field.
     *     - $s Number of attempts included in the stats.
     */
    protected function get_quiz_and_questions_stats($quiz, $currentgroup,
            $nostudentsingroup, $useallattempts, $groupstudents, $questions) {

        list($quizstats, $questions, $subquestions, $s) =
                $this->try_loading_cached_stats($quiz, $currentgroup, $nostudentsingroup,
                        $useallattempts, $groupstudents, $questions);

        if (is_null($quizstats)) {
            list($s, $quizstats, $qstats) = $this->compute_stats($quiz->id,
                    $currentgroup, $nostudentsingroup, $useallattempts, $groupstudents, $questions);

            $questions = $qstats->questions;
            $subquestions = $qstats->subquestions;

            if ($s) {
                $this->cache_stats($quiz->id, $currentgroup,
                        $quizstats, $questions, $subquestions, $qstats->responses);
            }
        }

        return array($quizstats, $questions, $subquestions, $s);
    }

    /**
     * @return string HTML snipped for the Download full report as UI.
     */
    protected function everything_download_options() {
        $downloadoptions = $this->table->get_download_menu();

        $output = '<form action="'. $this->table->baseurl .'" method="post">';
        $output .= '<div class="mdl-align">';
        $output .= '<input type="hidden" name="everything" value="1"/>';
        $output .= '<input type="submit" value="'.get_string('downloadeverything', 'quiz_statistics').'"/>';
        $output .= choose_from_menu ($downloadoptions, 'download', $this->table->defaultdownloadformat, '', '', '', true);
        $output .= helpbutton('tableexportformats', get_string('tableexportformats', 'table'), 'moodle', true, false, '', true);
        $output .= '</div></form>';

        return $output;
    }

    /**
     * Generate the snipped of HTML that says when the stats were last caculated,
     * with a recalcuate now button.
     * @param object $quizstats the overall quiz statistics.
     * @param integer $quizid the quiz id.
     * @param integer $currentgroup the id of the currently selected group, or 0.
     * @param array $groupstudents ids of students in the group.
     * @param boolean $useallattempts whether to use all attempts, instead of just first attempts.
     * @return string a HTML snipped saying when the stats were last computed, or blank if that is not appropriate.
     */
    protected function output_caching_info($quizstats, $quizid, $currentgroup,
            $groupstudents, $useallattempts, $reporturl) {
        if (empty($quizstats->timemodified)) {
            return '';
        }

        // Find the number of attempts since the cached statistics were computed.
        list($fromqa, $whereqa, $qaparams) = quiz_report_attempts_sql($quizid, $currentgroup, $groupstudents, $useallattempts);
        $count = count_records_sql("
                SELECT COUNT(1)
                FROM $fromqa
                WHERE $whereqa
                    AND quiza.timefinish > {$quizstats->timemodified}");

        if (!$count) {
            $count = 0;
        }

        // Generate the output.
        $a = new stdClass;
        $a->lastcalculated = format_time(time() - $quizstats->timemodified);
        $a->count = $count;

        $output = '';
        $output .= print_box_start('boxaligncenter generalbox boxwidthnormal mdl-align', '', true);
        $output .= get_string('lastcalculated', 'quiz_statistics', $a);
        $output .= print_single_button($reporturl->out(true),
                $reporturl->params()+array('recalculate' => 1, 'sesskey' => sesskey()),
                get_string('recalculatenow', 'quiz_statistics'), 'post', '', true);
        $output .= print_box_end(true);

        return $output;
    }

    /**
     * Clear the cached data for a particular report configuration. This will
     * trigger a re-computation the next time the report is displayed.
     * @param integer $quizid the quiz id.
     * @param integer $currentgroup a group id, or 0.
     * @param boolean $useallattempts whether all attempts, or just first attempts are included.
     */
    protected function clear_cached_data($quizid, $currentgroup, $useallattempts) {
        $todelete = get_records_select_menu('quiz_statistics',
                "quizid = $quizid AND groupid = $currentgroup AND allattempts = $useallattempts");

        if (!$todelete) {
            return;
        }

        list($todeletesql, $todeleteparams) = get_in_or_equal(array_keys($todelete));

        if (!delete_records_select('quiz_statistics', "id $todeletesql")) {
            mtrace('Error deleting out of date quiz_statistics records.');
        }

        if (!delete_records_select('quiz_question_statistics', "quizstatisticsid $todeletesql")){
            mtrace('Error deleting out of date quiz_question_statistics records.');
        }

        if (!delete_records_select('quiz_question_response_stats', "quizstatisticsid $todeletesql")) {
            mtrace('Error deleting out of date quiz_question_response_stats records.');
        }
    }

    /**
     * Comparison callback for response details. Sorts by decreasing fraction, then answer.
     * @param object $detail1 object to compare
     * @param object $detail2 object to compare
     * @return number -1, 0 or 1, depending on whether $detail1 should come before or after $detail2.
     */
    protected function sort_response_details($detail1, $detail2) {
        if ($detail1->credit == $detail2->credit) {
            return strcmp($detail1->answer, $detail2->answer);
        }

        return ($detail1->credit > $detail2->credit) ? -1 : 1;
    }

    /**
     * Comparison callback for answers. Sorts by decreasing frequency, then response.
     * @param object $answer1 object to compare
     * @param object $answer2 object to compare
     * @return number -1, 0 or 1, depending on whether $answer1 should come before or after $answer2.
     */
    protected function sort_answers($answer1, $answer2) {
        if ($answer1->rcount == $answer2->rcount) {
            return strcmp($answer1->response, $answer2->response);
        }

        return ($answer1->rcount > $answer2->rcount) ? -1 : 1;
    }

    /**
     * @param boolean $useallattempts whether we are using all attempts.
     * @return the appropriate lang string to describe this option.
     */
    protected function using_attempts_string($useallattempts) {
        if ($useallattempts) {
            return get_string('allattempts', 'quiz_statistics');
        } else {
            return get_string('firstattempts', 'quiz_statistics');
        }
    }
}

function quiz_report_attempts_sql($quizid, $currentgroup, $groupstudents, $allattempts = true) {
    global $CFG;

    $fromqa = "{$CFG->prefix}quiz_attempts quiza ";

    $whereqa = "quiza.quiz = $quizid AND quiza.preview = 0 AND quiza.timefinish <> 0 ";
    $qaparams = array('quizid'=>$quizid);

    if (!empty($currentgroup) && $groupstudents) {
        list($grpsql, $grpparams) = get_in_or_equal(array_keys($groupstudents), SQL_PARAMS_NAMED, 'u0000');
        $whereqa .= "AND quiza.userid $grpsql ";
        $qaparams += $grpparams;
    }

    if (!$allattempts) {
        $whereqa .= 'AND quiza.attempt = 1 ';
    }

    return array($fromqa, $whereqa, $qaparams);
}
