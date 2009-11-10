<?php // $Id$
/**
 * This page displays a preview of a question
 *
 * The preview uses the option settings from the activity within which the question
 * is previewed or the default settings if no activity is specified. The question session
 * information is stored in the session as an array of subsequent states rather
 * than in the database.
 *
 * TODO: make this work with activities other than quiz
 *
 * @author Alex Smith as part of the Serving Mathematics project
 *         {@link http://maths.york.ac.uk/serving_maths}
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questionbank
 */

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/formslib.php');

// Define settings form class.
class preview_options_form extends moodleform {
    function definition() {

    }
}

// Get and validate quetsion id.
$id = required_param('id', PARAM_INT); // Question id
$question = question_engine::load_question($id);
question_require_capability_on($question, 'use');
if (!$category = get_record("question_categories", "id", $question->category)) {
    print_error('invalidquestionid', 'quiz');
}

// Get and validate display options.
$displayoptions = new question_display_options();
$displayoptions->set_review_options($CFG->quiz_review); // Quiz-specific, but a sensible source of defaults.
$displayoptions->markdp = optional_param('markdp', $CFG->quiz_decimalpoints, PARAM_INT);
// TODO various review options.
$displayoptions->flags = question_display_options::HIDDEN;
$displayoptions->manualcomment = question_display_options::HIDDEN;

// Get and validate exitsing preview, or start a new one.
$previewid = optional_param('previewid', 0, PARAM_INT);
if ($previewid) {
    if (!isset($SESSION->question_previews[$previewid])) {
        print_error('notyourpreview', 'question');
    }
    $quba = question_engine::load_questions_usage_by_activity($previewid);
    $qnumber = $quba->get_first_qnumber();

} else {
    $model = optional_param('model', 'deferredfeedback', PARAM_FORMAT);
    $maxmark = optional_param('maxmark', $question->defaultmark, PARAM_NUMBER);

    $quba = question_engine::make_questions_usage_by_activity('core_question_preview');
    $quba->set_preferred_interaction_model($model);
    $question->maxmark = $maxmark;
    $qnumber = $quba->add_question($question);
    $quba->start_all_questions();
    // TODO question_engine::save_questions_usage_by_activity($quba);
}


switch (optional_param('action', null, PARAM_ALPHA)) {
    case 'restart':

        break;

    case 'fill':

        break;

    case 'finish':

        break;
}

$mform = new preview_options_form();
$actionurl = $CFG->wwwroot . '/question/preview.php?id=' . $question->id . '&amp;previewid=' . $quba->get_id();

// Output
$title = get_string('previewquestion', 'question', format_string($question->name));
$headtags = implode("\n", $quba->render_question_head_html($qnumber));
print_header($title, '', '', '', $headtags);
print_heading($title);

echo '<form method="post" action="' . $actionurl .
        '" enctype="multipart/form-data" id="responseform">', "\n";

echo $quba->render_question($qnumber, $displayoptions, '1');

echo '<div class="controls">';
// TODO restart, finish, etc. buttons.
echo '</div>';
// TODO step navigation.
// TODO close preview button via JS.
echo '</form>';

$mform->display();

print_footer('empty');
