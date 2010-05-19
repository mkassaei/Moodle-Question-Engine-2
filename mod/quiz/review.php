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
 * This page prints a review of a particular quiz attempt
 *
 * It is used either by the student whose attempts this is, after the attempt,
 * or by a teacher reviewing another's attempt during or afterwards.
 *
 * @package mod_quiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/report/reportlib.php');

$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$showall = optional_param('showall', 0, PARAM_BOOL);

$attemptobj = quiz_attempt::create($attemptid);

// Check login.
require_login($attemptobj->get_courseid(), false, $attemptobj->get_cm());
$attemptobj->check_review_capability();

// Create an object to manage all the other (non-roles) access rules.
$accessmanager = $attemptobj->get_access_manager(time());
$options = $attemptobj->get_review_options();

// Permissions checks for normal users who do not have quiz:viewreports capability.
if (!$attemptobj->has_capability('mod/quiz:viewreports')) {
    // Can't review during the attempt - send them back to the attempt page.
    if (!$attemptobj->is_finished()) {
        redirect($attemptobj->attempt_url(0, $page));
    }
    // Can't review other users' attempts.
    if (!$attemptobj->is_own_attempt()) {
        throw new moodle_quiz_exception($attemptobj->get_quizobj(), 'notyourattempt');
    }
    // Can't review unless Students may review -> Responses option is turned on.
    if (!$options->responses) {
        $accessmanager->back_to_view_page($attemptobj->is_preview_user(),
                $accessmanager->cannot_review_message($options));
    }
}

// Load the questions and states needed by this page.
if ($showall) {
    $questionids = $attemptobj->get_question_numbers();
} else {
    $questionids = $attemptobj->get_question_numbers($page);
} 
//    $attemptobj->load_questions($questionids);
//    $attemptobj->load_question_states($questionids);

// Save the flag states, if they are being changed.
if ($options->flags == question_display_options::EDITABLE && optional_param('savingflags', false, PARAM_BOOL)) {
    confirm_sesskey();
    $attemptobj->save_question_flags();
    redirect($attemptobj->review_url(0, $page, $showall));
}

// Log this review.
add_to_log($attemptobj->get_courseid(), 'quiz', 'review', 'review.php?attempt=' .
        $attemptobj->get_attemptid(), $attemptobj->get_quizid(), $attemptobj->get_cmid());

// Work out appropriate title.
if ($attemptobj->is_preview_user() && $attemptobj->is_own_attempt()) {
    $strreviewtitle = get_string('reviewofpreview', 'quiz');
} else {
    $strreviewtitle = get_string('reviewofattempt', 'quiz', $attemptobj->get_attempt_number());
}

// Print the page header
require_js($CFG->httpswwwroot . '/mod/quiz/quiz.js');
$headtags = $attemptobj->get_html_head_contributions($page, $showall);
if ($accessmanager->securewindow_required($attemptobj->is_preview_user())) {
    $accessmanager->setup_secure_page($attemptobj->get_course()->shortname.': '.format_string($attemptobj->get_quiz_name()), $headtags);
} else {
    print_header_simple(format_string($attemptobj->get_quiz_name()), '', $attemptobj->navigation($strreviewtitle),
            '', $headtags, true, $attemptobj->update_module_button());
}
echo '<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>'; // for overlib

// Print tabs if they should be there.
if ($attemptobj->is_preview_user()) {
    if ($attemptobj->is_own_attempt()) {
        $currenttab = 'preview';
    } else {
        $currenttab = 'reports';
        $mode = '';
    }
    include('tabs.php');
}

// Print heading.
print_heading(format_string($attemptobj->get_quiz_name()));
if ($attemptobj->is_preview_user() && $attemptobj->is_own_attempt()) {
    $attemptobj->print_restart_preview_button();
}
print_heading($strreviewtitle);

// Print the navigation panel in a left column.
echo '<div id="left-column">';
print_container_start();
$attemptobj->print_navigation_panel('quiz_review_nav_panel', $page);
print_container_end();
echo '</div>';

// Start the main column.
echo '<div id="middle-column"><div id="middle-column-inner">';
print_container_start();
echo skip_main_destination();

// Summary table start ============================================================================

// Work out some time-related things.
$attempt = $attemptobj->get_attempt();
$quiz = $attemptobj->get_quiz();
$overtime = 0;

if ($attempt->timefinish) {
    if ($timetaken = ($attempt->timefinish - $attempt->timestart)) {
        if($quiz->timelimit && $timetaken > ($quiz->timelimit + 60)) {
            $overtime = $timetaken - $quiz->timelimit;
            $overtime = format_time($overtime);
        }
        $timetaken = format_time($timetaken);
    } else {
        $timetaken = "-";
    }
} else {
    $timetaken = get_string('unfinished', 'quiz');
}

// Print summary table about the whole attempt.
// First we assemble all the rows that are appopriate to the current situation in
// an array, then later we only output the table if there are any rows to show.
$rows = array();
if (empty($attemptobj->get_quiz()->showuserpicture) && $attemptobj->get_userid() <> $USER->id) {
    // If showuserpicture is true, the picture is shown elsewhere, so don't repeat it.
    $student = get_record('user', 'id', $attemptobj->get_userid());
    $picture = print_user_picture($student, $attemptobj->get_courseid(), $student->picture, false, true);
    $rows[] = '<tr><th scope="row" class="cell">' . $picture . '</th><td class="cell"><a href="' .
            $CFG->wwwroot . '/user/view.php?id=' . $student->id . '&amp;course=' . $attemptobj->get_courseid() . '">' .
            fullname($student, true) . '</a></td></tr>';
}
if ($attemptobj->has_capability('mod/quiz:viewreports')) {
    $attemptlist = $attemptobj->links_to_other_attempts($attemptobj->review_url(0, $page, $showall));
    if ($attemptlist) {
        $rows[] = '<tr><th scope="row" class="cell">' . get_string('attempts', 'quiz') .
                '</th><td class="cell">' . $attemptlist . '</td></tr>';
    }
}

// Timing information.
$rows[] = '<tr><th scope="row" class="cell">' . get_string('startedon', 'quiz') .
        '</th><td class="cell">' . userdate($attempt->timestart) . '</td></tr>';
if ($attempt->timefinish) {
    $rows[] = '<tr><th scope="row" class="cell">' . get_string('completedon', 'quiz') . '</th><td class="cell">' .
            userdate($attempt->timefinish) . '</td></tr>';
    $rows[] = '<tr><th scope="row" class="cell">' . get_string('timetaken', 'quiz') . '</th><td class="cell">' .
            $timetaken . '</td></tr>';
}
if (!empty($overtime)) {
    $rows[] = '<tr><th scope="row" class="cell">' . get_string('overdue', 'quiz') . '</th><td class="cell">' . $overtime . '</td></tr>';
}

// Show scores (if the user is allowed to see scores at the moment).
$grade = quiz_rescale_grade($attempt->sumgrades, $quiz, false);
if ($options->scores && quiz_has_grades($quiz)) {

    if (!$attempt->timefinish) {
        $rows[] = '<tr><th scope="row" class="cell">' . get_string('grade') . '</th><td class="cell">' .
                get_string('attemptstillinprogress', 'quiz') . '</td></tr>';

    } else if (is_null($grade)) {
        $rows[] = '<tr><th scope="row" class="cell">' . get_string('grade') . '</th><td class="cell">' .
                quiz_format_grade($quiz, $grade) . '</td></tr>';

    } else {
        // Show raw marks only if they are different from the grade (like on the view page).
        if ($quiz->grade != $quiz->sumgrades) {
            $a = new stdClass;
            $a->grade = quiz_format_grade($quiz, $attempt->sumgrades);
            $a->maxgrade = quiz_format_grade($quiz, $quiz->sumgrades);
            $rows[] = '<tr><th scope="row" class="cell">' . get_string('marks', 'quiz') . '</th><td class="cell">' .
                    get_string('outofshort', 'quiz', $a) . '</td></tr>';
        }

        // Now the scaled grade.
        $a = new stdClass;
        $a->grade = '<b>' . quiz_format_grade($quiz, $grade) . '</b>';
        $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
        if ($quiz->grade != 100) {
            $a->percent = '<b>' . round($attempt->sumgrades * 100 / $quiz->sumgrades, 0) . '</b>';
            $formattedgrade = get_string('outofpercent', 'quiz', $a);
        } else {
            $formattedgrade = get_string('outof', 'quiz', $a);
        }
        $rows[] = '<tr><th scope="row" class="cell">' . get_string('grade') . '</th><td class="cell">' .
                $formattedgrade . '</td></tr>';
    }
}

// Feedback if there is any, and the user is allowed to see it now.
$feedback = quiz_feedback_for_grade($grade, $attempt->quiz);
if ($options->overallfeedback && $feedback) {
    $rows[] = '<tr><th scope="row" class="cell">' . get_string('feedback', 'quiz') .
            '</th><td class="cell">' . $feedback . '</td></tr>';
}

// Now output the summary table, if there are any rows to be shown.
if (!empty($rows)) {
    echo '<table class="generaltable generalbox quizreviewsummary"><tbody>', "\n";
    echo implode("\n", $rows);
    echo "\n</tbody></table>\n";
}

// Summary table end ==============================================================================

// Form for saving flags if necessary.
if ($options->flags == question_display_options::EDITABLE) {
    echo '<form action="' . $attemptobj->review_url(0, $page, $showall) .
            '" method="post"><div>';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
}

// Print all the questions.
if ($showall) {
    $thispage = 'all';
    $lastpage = true;
} else {
    $thispage = $page;
    $lastpage = $attemptobj->is_last_page($page);
}
foreach ($attemptobj->get_question_numbers($thispage) as $qnumber) {
    echo $attemptobj->render_question($qnumber, true, $attemptobj->review_url($qnumber, $page, $showall));
}

// Close form if we opened it.
if ($options->flags == question_display_options::EDITABLE) {
    echo '<div class="submitbtns">' . "\n" .
            '<input type="submit" id="savingflagssubmit" name="savingflags" value="' .
            get_string('saveflags', 'question') . '" />' .
            "</div>\n" .
            "\n</div></form>\n";
    print_js_call('question_flag_changer.init_flag_save_form', array('savingflagssubmit'));
}

// Print a link to the next page.
echo '<div class="submitbtns">';
if ($lastpage) {
    $accessmanager->print_finish_review_link($attemptobj->is_preview_user());
} else {
    echo link_arrow_right(get_string('next'), $attemptobj->review_url(0, $page + 1));
}
// End middle column.
print_container_end();
echo '</div></div>';

// End middle column.
echo '</div>';

echo '<div class="clearer"></div>';

// Finish the page
if ($accessmanager->securewindow_required($attemptobj->is_preview_user())) {
    print_footer('empty');
} else {
    print_footer($attemptobj->get_course());
}
