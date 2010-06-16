<?php
/**
 * This page lets admins configure remote Opaque engines.
 *
 * @copyright &copy; 2006 The Open University
 * @author T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package opaquequestiontype
 *//* **/

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

// Check the user is logged in.
require_login();
if (!has_capability('moodle/question:config', get_context_instance(CONTEXT_SYSTEM, SITEID))) {
    print_error('restricteduser');
}

$strtest = get_string('testconnection', 'qtype_opaque');
$stredit = get_string('edit');
$strdelete = get_string('delete');

// See if any action was requested.
$delete = optional_param('delete', 0, PARAM_INT);
if ($delete) {
    $engine = get_record('question_opaque_engines', 'id', $delete);
    if (is_string($engine)) {
        print_error('unknownengine', 'qtype_opaque', 'engines.php', $engine);
    }
    if (optional_param('confirm', false, PARAM_BOOL) && confirm_sesskey()) {
        if (delete_engine_def($delete)) {
            redirect('engines.php');
        } else {
            print_error('deletefailed', 'qtype_opaque', 'engines.php');
        }
    } else {
        notice_yesno(get_string('deleteconfigareyousure', 'qtype_opaque', $engine->name), 'engines.php', 'engines.php',
                array('delete' => $delete, 'confirm' => 'yes', 'sesskey' => sesskey()), array(), 'post', 'get');
        exit;
    }
}

// Get the list of configured engines.
$engines = get_records('question_opaque_engines', '', '', 'id ASC');

// Header.
$strtitle = get_string('configuredquestionengines', 'qtype_opaque');
print_header_simple($strtitle, '', build_navigation($strtitle));
print_simple_box_start();
print_heading_with_help($strtitle, 'configuredengines', 'qtype_opaque');

// List of configured engines.
if ($engines) {
    foreach ($engines as $engine) {
        ?>
<p><?php p($engine->name) ?> 
<a title="<?php p($strtest) ?>" href="testengine.php?engineid=<?php echo $engine->id ?>"><img
        src="<?php p($CFG->pixpath . '/t/preview.gif') ?>" border="0" alt="<?php p($strtest) ?>" /></a>
<a title="<?php p($stredit) ?>" href="editengine.php?engineid=<?php echo $engine->id ?>"><img
        src="<?php p($CFG->pixpath . '/t/edit.gif') ?>" border="0" alt="<?php p($stredit) ?>" /></a>
<a title="<?php p($strdelete) ?>" href="engines.php?delete=<?php echo $engine->id ?>"><img
        src="<?php p($CFG->pixpath . '/t/delete.gif') ?>" border="0" alt="<?php p($strdelete) ?>" /></a>
</p>
        <?php
    }
} else {
    echo '<p>', get_string('noengines', 'qtype_opaque'), '</p>';
}

// Add new engine link.
echo '<p class="mdl-align"><a href="editengine.php">', get_string('addengine', 'qtype_opaque'), '</a></p>';

// Footer.
print_simple_box_end();
print_footer();

?>
