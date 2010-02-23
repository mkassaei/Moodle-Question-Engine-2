<?php  // $Id$

class quiz_report_overview_table extends table_sql {
    public $useridfield = 'userid';

    protected $candelete;
    protected $reporturl;
    protected $displayoptions;
    protected $regradedqs = array();

    public function __construct($quiz , $qmsubselect, $groupstudents,
            $students, $detailedmarks, $questions, $candelete,
            $reporturl, $displayoptions, $context) {
        parent::table_sql('mod-quiz-report-overview-report');
        $this->quiz = $quiz;
        $this->qmsubselect = $qmsubselect;
        $this->groupstudents = $groupstudents;
        $this->students = $students;
        $this->detailedmarks = $detailedmarks;
        $this->questions = $questions;
        $this->candelete = $candelete;
        $this->reporturl = $reporturl;
        $this->displayoptions = $displayoptions;
        $this->context = $context;
    }

    public function build_table() {
        global $CFG;
        if ($this->rawdata) {
            // Define some things we need later to process raw data from db.
            $this->strtimeformat = str_replace(',', '', get_string('strftimedatetime'));
            parent::build_table();
            //end of adding data from attempts data to table / download
            //now add averages at bottom of table :
            $averagesql = "SELECT AVG(qg.grade) AS grade
                    FROM {$CFG->prefix}quiz_grades qg
                    WHERE quiz = {$this->quiz->id}";

            $this->add_separator();
            if ($this->is_downloading()) {
                $namekey = 'lastname';
            } else {
                $namekey = 'fullname';
            }
            if ($this->groupstudents) {
                $g_usql = ' IN (' . implode(',', $this->groupstudents) . ')';

                $groupaveragesql = $averagesql." AND qg.userid $g_usql";
                $groupaverage = get_record_sql($groupaveragesql);
                $groupaveragerow = array($namekey => get_string('groupavg', 'grades'),
                        'sumgrades' => quiz_format_grade($this->quiz, $groupaverage->grade),
                        'feedbacktext'=> strip_tags(quiz_report_feedback_for_grade($groupaverage->grade, $this->quiz->id)));
                if($this->detailedmarks && $this->qmsubselect) {
                    $avggradebyq = quiz_get_average_grade_for_questions($this->quiz, $this->groupstudents, array_keys($this->questions));
                    $groupaveragerow += $this->format_average_grade_for_questions($avggradebyq);
                }
                $this->add_data_keyed($groupaveragerow);
            }

            $s_usql = ' IN (' . implode(',', $this->students) . ')';
            $overallaverage = get_record_sql($averagesql." AND qg.userid $s_usql");
            $overallaveragerow = array($namekey => get_string('overallaverage', 'grades'),
                        'sumgrades' => quiz_format_grade($this->quiz, $overallaverage->grade),
                        'feedbacktext'=> strip_tags(quiz_report_feedback_for_grade($overallaverage->grade, $this->quiz->id)));
            if($this->detailedmarks && $this->qmsubselect) {
                $avggradebyq = quiz_get_average_grade_for_questions($this->quiz, $this->students, array_keys($this->questions));
                $overallaveragerow += $this->format_average_grade_for_questions($avggradebyq);
            }
            $this->add_data_keyed($overallaveragerow);
        }
    }

    protected function format_average_grade_for_questions($gradeaverages) {
        $row = array();
        if (!$gradeaverages) {
            $gradeaverages = array();
        }
        foreach ($this->questions as $question) {
            if (isset($gradeaverages[$question->qnumber])) {
                $record = $gradeaverages[$question->qnumber];
                $grade = quiz_rescale_grade($record->averagefraction * $question->maxmark, $this->quiz, 'question');
                if (!$this->download) {
                    $grade = '<span class="avgcell"><span class="average">' . $grade . '</span> <span class="count">(' .
                            $record->numaveraged . ')</span></span>';
                }
            } else {
                $grade = '-';
            }
            $row['qsgrade' . $question->qnumber] = $grade;
        }
        return $row;
    }

    public function wrap_html_start() {
        if ($this->is_downloading() || !$this->candelete) {
            return;
        }

        // Start form
        echo '<div id="tablecontainer">';
        echo '<form id="attemptsform" method="post" action="' . $this->reporturl->out(true) .'">';
        echo '<div style="display: none;">';
        echo $this->reporturl->hidden_params_out(array(), 0, $this->displayoptions);
        echo '</div>';
        echo '<div>';
    }

    public function wrap_html_finish() {
        if ($this->is_downloading() || !$this->candelete) {
            return;
        }

        $strreallydel  = addslashes_js(get_string('deleteattemptcheck','quiz'));
        echo '<div id="commands">';
        echo '<a href="javascript:select_all_in(\'DIV\',null,\'tablecontainer\');">'.
                get_string('selectall', 'quiz').'</a> / ';
        echo '<a href="javascript:deselect_all_in(\'DIV\',null,\'tablecontainer\');">'.
                get_string('selectnone', 'quiz').'</a> ';
        echo '&nbsp;&nbsp;';
        if (has_capability('mod/quiz:regrade', $this->context)) {
            echo '<input type="submit" name="regrade" value="'.get_string('regradeselected', 'quiz_overview').'"/>';
        }
        echo '<input type="submit" onclick="return confirm(\''.$strreallydel.'\');" name="delete" value="'.get_string('deleteselected', 'quiz_overview').'"/>';
        echo '</div>';
        // Close form
        echo '</div>';
        echo '</form></div>';
    }

    public function col_checkbox($attempt) {
        if ($attempt->attempt) {
            return '<input type="checkbox" name="attemptid[]" value="'.$attempt->attempt.'" />';
        } else {
            return '';
        }
    }

    public function col_picture($attempt) {
        global $COURSE;
        $user = new object();
        $user->id = $attempt->userid;
        $user->lastname = $attempt->lastname;
        $user->firstname = $attempt->firstname;
        $user->imagealt = $attempt->imagealt;
        $user->picture = $attempt->picture;
        return print_user_picture($user, $COURSE->id, $attempt->picture, false, true);
    }


    public function col_timestart($attempt) {
        if (!$attempt->attempt) {
            return  '-';
        }

        $startdate = userdate($attempt->timestart, $this->strtimeformat);
        if (!$this->is_downloading()) {
            return  '<a href="review.php?q='.$this->quiz->id.'&amp;attempt='.$attempt->attempt.'">'.$startdate.'</a>';
        } else {
            return  $startdate;
        }
    }

    public function col_timefinish($attempt) {
        if (!$attempt->attempt) {
            return  '-';
        }
        if (!$attempt->timefinish) {
            return  '-';
        }

        $timefinish = userdate($attempt->timefinish, $this->strtimeformat);
        if (!$this->is_downloading()) {
            return '<a href="review.php?q='.$this->quiz->id.'&amp;attempt='.$attempt->attempt.'">'.$timefinish.'</a>';
        } else {
            return $timefinish;
        }
    }

    public function col_duration($attempt) {
        if ($attempt->timefinish) {
            return format_time($attempt->duration);
        } elseif ($attempt->timestart) {
            return get_string('unfinished', 'quiz');
        } else {
            return '-';
        }
    }

    public function col_sumgrades($attempt) {
        if (!$attempt->timefinish) {
            return '-';
        }

        $grade = quiz_rescale_grade($attempt->sumgrades, $this->quiz);
        if ($this->is_downloading()) {
            return $grade;
        }

        if (isset($this->regradedqs[$attempt->usageid])) {
            $newsumgrade = 0;
            $oldsumgrade = 0;
            foreach ($this->questions as $question) {
                if (isset($this->regradedqs[$attempt->usageid][$question->id])) {
                    $newsumgrade += $this->regradedqs[$attempt->usageid][$question->id]->newgrade;
                    $oldsumgrade += $this->regradedqs[$attempt->usageid][$question->id]->oldgrade;
                } else {
                    $newsumgrade += $this->lateststeps[$attempt->usageid][$question->qnumber]->fraction * $question->maxmark;
                    $oldsumgrade += $this->lateststeps[$attempt->usageid][$question->qnumber]->fraction * $question->maxmark;
                }
            }
            $newsumgrade = quiz_rescale_grade($newsumgrade, $this->quiz);
            $oldsumgrade = quiz_rescale_grade($oldsumgrade, $this->quiz);
            $grade = "<del>$oldsumgrade</del><br />$newsumgrade";
        }
        $gradehtml = '<a href="review.php?q='.$this->quiz->id.'&amp;attempt='.$attempt->attempt.'">'.$grade.'</a>';
        if ($this->qmsubselect && $attempt->gradedattempt) {
            $gradehtml = '<div class="highlight">'.$gradehtml.'</div>';
        }
        return $gradehtml;
    }

    /**
     * @param string $colname the name of the column.
     * @param object $attempt the row of data - see the SQL in display() in
     * mod/quiz/report/overview/report.php to see what fields are present,
     * and what they are called.
     * @return string the contents of the cell.
     */
    public function other_cols($colname, $attempt) {
        if (!preg_match('/^qsgrade([0-9]+)$/', $colname, $matches)) {
            return NULL;
        }
        $qnumber = $matches[1];
        $question = $this->questions[$qnumber];
        if (!isset($this->lateststeps[$attempt->usageid][$qnumber])) {
            return '-';
        }

        $stepdata = $this->lateststeps[$attempt->usageid][$qnumber];
        if (is_null($stepdata->fraction)) {
            return '-';
        }

        $grade = quiz_rescale_grade($stepdata->fraction * $question->maxmark, $this->quiz);
        if ($this->is_downloading()) {
            return $grade;
        }

        if (isset($this->regradedqs[$attempt->usageid][$qnumber])) {
            $gradefromdb = $grade;
            $newgrade = quiz_rescale_grade($this->regradedqs[$attempt->usageid][$qnumber]->newgrade, $this->quiz, 'question');
            $oldgrade = quiz_rescale_grade($this->regradedqs[$attempt->usageid][$qnumber]->oldgrade, $this->quiz, 'question');

            $grade = '<del>'.$oldgrade.'</del><br />' . $newgrade;
        }

        $linktopopup = link_to_popup_window('/mod/quiz/reviewquestion.php?attempt=' .
                $attempt->attempt . '&amp;qnumber=' . $qnumber,
                'reviewquestion', $grade, 450, 650, get_string('reviewresponse', 'quiz'),
                'none', true);

        $qclass = question_get_feedback_class($stepdata->fraction);
        $feedbackimg = question_get_feedback_image($stepdata->fraction);
        return '<span class="que"><span class="' . $qclass . '">' .
                $linktopopup . " $feedbackimg</span></span>";
    }

    public function col_feedbacktext($attempt) {
        if (!$attempt->timefinish) {
            return '-';
        }

        if (!$this->is_downloading()) {
            return quiz_report_feedback_for_grade(quiz_rescale_grade($attempt->sumgrades, $this->quiz, false), $this->quiz->id);
        } else {
            return strip_tags(quiz_report_feedback_for_grade(quiz_rescale_grade($attempt->sumgrades, $this->quiz, false), $this->quiz->id));
        }
    }

    public function col_regraded($attempt) {
        if ($attempt->regraded == '') {
            return '';
        } else if ($attempt->regraded == 0) {
            return get_string('needed', 'quiz_overview');
        } else if ($attempt->regraded == 1) {
            return get_string('done', 'quiz_overview');
        }
    }

    public function query_db($pagesize, $useinitialsbar=true) {
        // Add table joins so we can sort by question grade
        // unfortunately can't join all tables necessary to fetch all grades
        // to get the state for one question per attempt row we must join two tables
        // and there is a limit to how many joins you can have in one query. In MySQL it
        // is 61. This means that when having more than 29 questions the query will fail.
        // So we join just the tables needed to sort the attempts.
        if ($sort = $this->get_sql_sort()) {
            if ($this->detailedmarks) {
                $this->sql->from .= ' ';
                $sortparts    = explode(',', $sort);
                $matches = array();
                foreach ($sortparts as $sortpart) {
                    $sortpart = trim($sortpart);
                    if (preg_match('/^qsgrade([0-9]+)/', $sortpart, $matches)) {
                        $qid = intval($matches[1]);
                        // TODO
                        $this->sql->fields .=  ", qs$qid.grade AS qsgrade$qid, qs$qid.event AS qsevent$qid, qs$qid.id AS qsid$qid";
                        $this->sql->from .= "LEFT JOIN {question_sessions} qns$qid ON qns$qid.attemptid = qa.uniqueid AND qns$qid.questionid = :qid$qid ";
                        $this->sql->from .=  "LEFT JOIN  {question_states} qs$qid ON qs$qid.id = qns$qid.newgraded ";
                        $this->sql->params['qid'.$qid] = $qid;
                    }
                }
            } else {
                // Unset any sort columns that sort on question grade as the
                // grades are not being fetched as fields.
                foreach ($this->sess->sortby as $column => $order) {
                    if (preg_match('/^qsgrade([0-9]+)/', trim($column))) {
                        unset($this->sess->sortby[$column]);
                    }
                }
            }
        }

        parent::query_db($pagesize, $useinitialsbar);
        if (!$this->detailedmarks) {
            return;
        }

        // Get all the attempt ids we want to display on this page
        // or to export for download.
        if (!$this->is_downloading()) {
            $attemptids = array();
            foreach ($this->rawdata as $attempt) {
                if ($attempt->usageid > 0) {
                    $attemptids[] = $attempt->usageid;
                }
            }
            $this->lateststeps = quiz_report_get_latest_step($attemptids, array_keys($this->questions));
            if (has_capability('mod/quiz:regrade', $this->context)) {
                $this->regradedqs = quiz_get_regraded_qs($attemptids);
            }

        } else {
            $this->lateststeps = quiz_report_get_latest_step($this->sql, array_keys($this->questions));
            if (has_capability('mod/quiz:regrade', $this->context)) {
                $this->regradedqs = quiz_get_regraded_qs($this->sql);
            }
        }
    }
}
?>
