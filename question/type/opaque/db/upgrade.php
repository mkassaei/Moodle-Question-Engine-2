<?php
/**
 * Opaque question type database upgrade script.
 *
 * @copyright &copy; 2006 The Open University
 * @author T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package opaque_question_type
 */

function xmldb_qtype_opaque_upgrade($oldversion) {
    global $CFG;

    $result = true;

    if ($result && $oldversion < 2006120800) {

    /// Define table question_opaque to be created
        $table = new XMLDBTable('question_opaque');

    /// Adding fields to table question_opaque
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('engineid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('remoteid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, null, null);
        $table->addFieldInfo('remoteversion', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, null, null, null);

    /// Adding keys to table question_opaque
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->addKeyInfo('quesopaq_eng_fk', XMLDB_KEY_FOREIGN, array('engineid'), 'question_opaque_engines', array('id'));
        $table->addKeyInfo('quesopaq_que_fk', XMLDB_KEY_FOREIGN, array('questionid'), 'question', array('id'));

    /// Launch create table for question_opaque
        $result = $result && create_table($table);
    }

    if ($result && $oldversion < 2008011100) {

    /// Define field passkey to be added to question_opaque_engines
        $table = new XMLDBTable('question_opaque_engines');
        $field = new XMLDBField('passkey');
        $field->setAttributes(XMLDB_TYPE_CHAR, '8', XMLDB_UNSIGNED, null, null, null, null, null, 'name');

    /// Launch add field passkey
        // The stack people were naughty, and released a version of the Opaque question type
        // that had this extra database field, but did not change the version number, hence we
        // need this update to be conditional.
        if (!field_exists($table, $field)) {
            $result = $result && add_field($table, $field);
        }
    }

    if ($result && $oldversion < 2008031000) {
        $table = new XMLDBTable('question');
        $field = new XMLDBField('unlimited');
        if (field_exists($table, $field)) {
            set_field('question', 'unlimited', 0, 'qtype', 'opaque');
        }
    }

    if ($result && $oldversion < 2008062500) {
    /// Since we changed the layout of the Opaque file cache, delete any existing cached files.
        $result = $result && fulldelete($CFG->dataroot . '/opaqueresources');
    }

    return $result;
}

?>
