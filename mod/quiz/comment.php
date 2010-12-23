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
 * This page allows the teacher to enter a manual grade for a particular question.
 * This page is expected to only be used in a popup window.
 *
 * @package mod_quiz
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('locallib.php');

$attemptid = required_param('attempt', PARAM_INT); // attempt id
$slot = required_param('slot', PARAM_INT); // question number in attempt

$attemptobj = quiz_attempt::create($attemptid);

// Can only grade finished attempts.
if (!$attemptobj->is_finished()) {
    print_error('attemptclosed', 'quiz');
}

// Check login and permissions.
require_login($attemptobj->get_courseid(), false, $attemptobj->get_cm());
$attemptobj->require_capability('mod/quiz:grade');

// Load the questions and states.
//$attemptobj->load_questions($slot);
//$attemptobj->load_question_states($slot);

// Log this action.
add_to_log($attemptobj->get_courseid(), 'quiz', 'manualgrade', 'comment.php?attempt=' .
        $attemptobj->get_attemptid() . '&slot=' . $slot,
        $attemptobj->get_quizid(), $attemptobj->get_cmid());

// Print the page header
print_header();
print_heading(format_string($attemptobj->get_question_name($slot)));

// Process any data that was submitted.
if ((data_submitted()) && confirm_sesskey()) {
    if (optional_param('submit', false, PARAM_BOOL)) {
        begin_sql();
        $attemptobj->process_all_actions(time());
        commit_sql();
        notify(get_string('changessaved'), 'notifysuccess');
        print_js_call('window.opener.location.reload', array());
        close_window(2);
        die;
    }
}

// Print the comment form.
echo '<form method="post" class="mform" id="manualgradingform" action="' . $CFG->wwwroot . '/mod/quiz/comment.php">';
echo $attemptobj->render_question_for_commenting($slot);
?>
<div>
    <input type="hidden" name="attempt" value="<?php echo $attemptobj->get_attemptid(); ?>" />
    <input type="hidden" name="slot" value="<?php echo $slot; ?>" />
    <input type="hidden" name="slots" value="<?php echo $slot; ?>" />
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>" />
</div>
<fieldset class="hidden">
    <div>
        <div class="fitem">
            <div class="fitemtitle">
                <div class="fgrouplabel"><label> </label></div>
            </div>
            <fieldset class="felement fgroup">
                <input id="id_submitbutton" type="submit" name="submit" value="<?php print_string('save', 'quiz'); ?>"/>
                <input id="id_cancel" type="button" value="<?php print_string('cancel'); ?>" onclick="self.close()"/>
            </fieldset>
        </div>
    </div>
</fieldset>
<?php
echo '</form>';

// End of the page.
use_html_editor();
print_footer('empty');
