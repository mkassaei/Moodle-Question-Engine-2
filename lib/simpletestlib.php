<?php // $Id$
/**
 * Utility functions to make unit testing easier.
 * 
 * These functions, particularly the the database ones, are quick and
 * dirty methods for getting things done in test cases. None of these 
 * methods should be used outside test code.
 *
 * @copyright &copy; 2006 The Open University
 * @author T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @version $Id$
 * @package SimpleTestEx
 */

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/simpletestlib/simpletest.php');
require_once($CFG->libdir . '/simpletestlib/unit_tester.php');
require_once($CFG->libdir . '/simpletestlib/expectation.php');
require_once($CFG->libdir . '/simpletestlib/reporter.php');
require_once($CFG->libdir . '/simpletestlib/web_tester.php');
require_once($CFG->libdir . '/simpletestlib/mock_objects.php');

/**
 * Recursively visit all the files in the source tree. Calls the callback
 * function with the pathname of each file found. 
 * 
 * @param $path the folder to start searching from. 
 * @param $callback the function to call with the name of each file found.
 * @param $fileregexp a regexp used to filter the search (optional).
 * @param $exclude If true, pathnames that match the regexp will be ingored. If false, 
 *     only files that match the regexp will be included. (default false).
 * @param array $ignorefolders will not go into any of these folders (optional).
 */ 
function recurseFolders($path, $callback, $fileregexp = '/.*/', $exclude = false, $ignorefolders = array()) {
    $files = scandir($path);

    foreach ($files as $file) {
        $filepath = $path .'/'. $file;
        if ($file == '.' || $file == '..') {
            continue;
        } else if (is_dir($filepath)) {
            if (!in_array($filepath, $ignorefolders)) {
                recurseFolders($filepath, $callback, $fileregexp, $exclude, $ignorefolders);
            }
        } else if ($exclude xor preg_match($fileregexp, $filepath)) {
            call_user_func($callback, $filepath);
        }
    }
}

/**
 * An expectation for comparing strings ignoring whitespace.
 */
class IgnoreWhitespaceExpectation extends SimpleExpectation {
    var $expect;

    function IgnoreWhitespaceExpectation($content, $message = '%s') {
        $this->SimpleExpectation($message);
        $this->expect=$this->normalise($content);
    }

    function test($ip) {
        return $this->normalise($ip)==$this->expect;
    }

    function normalise($text) {
        return preg_replace('/\s+/m',' ',trim($text));
    }

    function testMessage($ip) {
        return "Input string [$ip] doesn't match the required value.";
    }
}

/**
 * An Expectation that two arrays contain the same list of values.
 */
class ArraysHaveSameValuesExpectation extends SimpleExpectation {
    var $expect;

    function ArraysHaveSameValuesExpectation($expected, $message = '%s') {
        $this->SimpleExpectation($message);
        if (!is_array($expected)) {
            trigger_error('Attempt to create an ArraysHaveSameValuesExpectation ' .
                    'with an expected value that is not an array.');
        }
        $this->expect = $this->normalise($expected);
    }

    function test($actual) {
        return $this->normalise($actual) == $this->expect;
    }

    function normalise($array) {
        sort($array);
        return $array;
    }

    function testMessage($actual) {
        return 'Array [' . implode(', ', $actual) .
                '] does not contain the expected list of values [' . implode(', ', $this->expect) . '].';
    }
}

/**
 * An Expectation that compares to objects, and ensures that for every field in the
 * expected object, there is a key of the same name in the actual object, with
 * the same value. (The actual object may have other fields to, but we ignore them.)
 */
class CheckSpecifiedFieldsExpectation extends SimpleExpectation {
    var $expect;

    function CheckSpecifiedFieldsExpectation($expected, $message = '%s') {
        $this->SimpleExpectation($message);
        if (!is_object($expected)) {
            trigger_error('Attempt to create a CheckSpecifiedFieldsExpectation ' .
                    'with an expected value that is not an object.');
        }
        $this->expect = $expected;
    }

    function test($actual) {
        foreach ($this->expect as $key => $value) {
            if (isset($value) && isset($actual->$key) && $actual->$key == $value) {
                // OK
            } else if (is_null($value) && is_null($actual->$key)) {
                // OK
            } else {
                return false;
            }
        }
        return true;
    }

    function testMessage($actual) {
        $mismatches = array();
        foreach ($this->expect as $key => $value) {
            if (isset($value) && isset($actual->$key) && $actual->$key == $value) {
                // OK
            } else if (is_null($value) && is_null($actual->$key)) {
                // OK
            } else {
                $mismatches[] = $key;
            }
        }
        return 'Actual object does not have all the same fields with the same values as the expected object (' .
                implode(', ', $mismatches) . ').';
    }
}
abstract class XMLStructureExpectation extends SimpleExpectation {
    /**
     * Parse a string as XML and return a DOMDocument;
     * @param $html
     * @return unknown_type
     */
    protected function load_xml($html) {
        $prevsetting = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $parser = new DOMDocument();
        if (!$parser->loadXML('<html>' . $html . '</html>')) {
            $parser = libxml_get_errors();
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prevsetting);
        return $parser;
    }

    function testMessage($html) {
        $parsererrors = $this->load_xml($html);
        if (is_array($parsererrors)) {
            foreach ($parsererrors as $key => $message) {
                $parsererrors[$key] = $message->message;
            }
            return 'Could not parse XML [' . $html . '] errors were [' . 
                    implode('], [', $parsererrors) . ']';
        }
        return $this->customMessage($html);
    }
}
/**
 * An Expectation that looks to see whether some HMTL contains a tag with a certain attribute.
 *
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ContainsTagWithAttribute extends XMLStructureExpectation {
    protected $tag;
    protected $attribute;
    protected $value;

    function __construct($tag, $attribute, $value, $message = '%s') {
        parent::__construct($message);
        $this->tag = $tag;
        $this->attribute = $attribute;
        $this->value = $value;
    }

    function test($html) {
        $parser = $this->load_xml($html);
        if (is_array($parser)) {
            return false;
        }
        $list = $parser->getElementsByTagName($this->tag);

        foreach ($list as $node) {
            if ($node->attributes->getNamedItem($this->attribute)->nodeValue === (string) $this->value) {
                return true;
            }
        }
        return false;
    }

    function customMessage($html) {
        return 'Content [' . $html . '] does not contain the tag [' .
                $this->tag . '] with attribute [' . $this->attribute . '="' . $this->value . '"].';
    }
}

/**
 * An Expectation that looks to see whether some HMTL contains a tag with an array of attributes.
 * All attributes must be present and their values must match the expected values.
 * A third parameter can be used to specify attribute=>value pairs which must not be present in a positive match.
 *
 * @copyright 2009 Nicolas Connault
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ContainsTagWithAttributes extends XMLStructureExpectation {
    /**
     * @var string $tag The name of the Tag to search
     */
    protected $tag;
    /**
     * @var array $expectedvalues An associative array of parameters, all of which must be matched
     */
    protected $expectedvalues = array();
    /**
     * @var array $forbiddenvalues An associative array of parameters, none of which must be matched
     */
    protected $forbiddenvalues = array();
    /**
     * @var string $failurereason The reason why the test failed: nomatch or forbiddenmatch
     */
    protected $failurereason = 'nomatch';

    function __construct($tag, $expectedvalues, $forbiddenvalues=array(), $message = '%s') {
        parent::__construct($message);
        $this->tag = $tag;
        $this->expectedvalues = $expectedvalues;
        $this->forbiddenvalues = $forbiddenvalues;
    }

    function test($html) {
        $parser = $this->load_xml($html);
        if (is_array($parser)) {
            return false;
        }

        $list = $parser->getElementsByTagName($this->tag);
        $foundamatch = false;

        // Iterating through inputs
        foreach ($list as $node) {
            if (empty($node->attributes) || !is_a($node->attributes, 'DOMNamedNodeMap')) {
                continue;
            }

            // For the current expected attribute under consideration, check that values match
            $allattributesmatch = true;

            foreach ($this->expectedvalues as $expectedattribute => $expectedvalue) {
                if ($node->getAttribute($expectedattribute) === '' && $expectedvalue !== '') {
                    $this->failurereason = 'nomatch';
                    continue 2; // Skip this tag, it doesn't have all the expected attributes
                }
                if ($node->getAttribute($expectedattribute) !== (string) $expectedvalue) {
                    $allattributesmatch = false;
                    $this->failurereason = 'nomatch';
                }
            }

            if ($allattributesmatch) {
                $foundamatch = true;

                // Now make sure this node doesn't have any of the forbidden attributes either
                $nodeattrlist = $node->attributes;

                foreach ($nodeattrlist as $domattrname => $domattr) {
                    if (array_key_exists($domattrname, $this->forbiddenvalues) && $node->getAttribute($domattrname) === (string) $this->forbiddenvalues[$domattrname]) {
                        $this->failurereason = "forbiddenmatch:$domattrname:" . $node->getAttribute($domattrname);
                        $foundamatch = false;
                    }
                }
            }
        }

        return $foundamatch;
    }

    function customMessage($html) {
        $output = 'Content [' . $html . '] ';

        if (preg_match('/forbiddenmatch:(.*):(.*)/', $this->failurereason, $matches)) {
            $output .= "contains the tag $this->tag with the forbidden attribute=>value pair: [$matches[1]=>$matches[2]]";
        } else if ($this->failurereason == 'nomatch') {
            $output .= 'does not contain the tag [' . $this->tag . '] with attributes [';
            foreach ($this->expectedvalues as $var => $val) {
                $output .= "$var=\"$val\" ";
            }
            $output = rtrim($output);
            $output .= '].';
        }

        return $output;
    }
}

/**
 * An Expectation that looks to see whether some HMTL contains a tag with an array of attributes.
 * All attributes must be present and their values must match the expected values.
 * A third parameter can be used to specify attribute=>value pairs which must not be present in a positive match.
 *
 * @copyright 2009 Nicolas Connault
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ContainsSelectExpectation extends XMLStructureExpectation {
    /**
     * @var string $tag The name of the Tag to search
     */
    protected $name;
    /**
     * @var array $expectedvalues An associative array of parameters, all of which must be matched
     */
    protected $choices;
    /**
     * @var array $forbiddenvalues An associative array of parameters, none of which must be matched
     */
    protected $selected;
    /**
     * @var string $failurereason The reason why the test failed: nomatch or forbiddenmatch
     */
    protected $enabled;

    function __construct($name, $choices, $selected = null, $enabled = null, $message = '%s') {
        parent::__construct($message);
        $this->name = $name;
        $this->choices = $choices;
        $this->selected = $selected;
        $this->enabled = $enabled;
    }

    function test($html) {
        $parser = $this->load_xml($html);
        if (is_array($parser)) {
            return false;
        }

        $list = $parser->getElementsByTagName('select');

        // Iterating through inputs
        foreach ($list as $node) {
            if (empty($node->attributes) || !is_a($node->attributes, 'DOMNamedNodeMap')) {
                continue;
            }

            if ($node->getAttribute('name') != $this->name) {
                continue;
            }

            if ($this->enabled === true && $node->getAttribute('disabled')) {
                continue;
            } else if ($this->enabled === false && $node->getAttribute('disabled') != 'disabled') {
                continue;
            }

            $options = $node->getElementsByTagName('option');
            reset($this->choices);
            foreach ($options as $option) {
                if ($option->getAttribute('value') != key($this->choices)) {
                    continue 2;
                }
                if ($option->firstChild->wholeText != current($this->choices)) {
                    continue 2;
                }
                if ($option->getAttribute('value') === $this->selected &&
                        !$option->hasAttribute('selected')) {
                    continue 2;
                }
                next($this->choices);
            }
            return true;
        }
        return false;
    }

    function customMessage($html) {
        if ($this->enabled === true) {
            $state = 'an enabled';
        } else if ($this->enabled === false) {
            $state = 'a disabled';
        } else {
            $state = 'a';
        }
        $output = 'Content [' . $html . '] does not contain ' . $state . 
                ' <select> with name ' . $this->name . ' and choices ' .
                implode(', ', $this->choices);
        if ($this->selected) {
            $output .= ' with ' . $this->selected . ' selected).';
        }

        return $output;
    }
}
/**
 * The opposite of {@link ContainsTagWithAttributes}. The test passes only if
 * the HTML does not contain a tag with the given attributes.
 *
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DoesNotContainTagWithAttributes extends ContainsTagWithAttributes {
    function __construct($tag, $expectedvalues, $message = '%s') {
        parent::__construct($tag, $expectedvalues, array(), $message);
    }
    function test($html) {
        return !parent::test($html);
    }
    function customMessage($html) {
        $output = 'Content [' . $html . '] ';

        $output .= 'contains the tag [' . $this->tag . '] with attributes [';
        foreach ($this->expectedvalues as $var => $val) {
            $output .= "$var=\"$val\" ";
        }
        $output = rtrim($output);
        $output .= '].';

        return $output;
    }
}

/**
 * Given a table name, a two-dimensional array of data, and a database connection,
 * creates a table in the database. The array of data should look something like this.
 *
 * $testdata = array(
 *      array('id', 'username', 'firstname', 'lastname', 'email'),
 *      array(1,    'u1',       'user',      'one',      'u1@example.com'),
 *      array(2,    'u2',       'user',      'two',      'u2@example.com'),
 *      array(3,    'u3',       'user',      'three',    'u3@example.com'),
 *      array(4,    'u4',       'user',      'four',     'u4@example.com'),
 *      array(5,    'u5',       'user',      'five',     'u5@example.com'),
 *  );
 *
 * The first 'row' of the test data gives the column names. The type of each column
 * is set to either INT or VARCHAR($strlen), guessed by inspecting the first row of
 * data. Unless the col name is 'id' in which case the col type will be SERIAL.
 * The remaining 'rows' of the data array are values loaded into the table. All columns
 * are created with a default of 0xdefa or 'Default' as appropriate.
 * 
 * This function should not be used in real code. Only for testing and debugging.
 *
 * @param string $tablename the name of the table to create. E.g. 'mdl_unittest_user'.
 * @param array $data a two-dimensional array of data, in the format described above.
 * @param object $db an AdoDB database connection.
 * @param int $strlen the width to use for string fields.
 */
function load_test_table($tablename, $data, $db = null, $strlen = 255) {
    global $CFG;
    $colnames = array_shift($data);
    $coldefs = array();
    foreach (array_combine($colnames, $data[0]) as $colname => $value) {
        if ($colname == 'id') {
            switch ($CFG->dbfamily) {
                case 'mssql':
                    $type = 'INTEGER IDENTITY(1,1)';
                    break;
                case 'oracle':
                    $type = 'INTEGER';
                    break;
                default:
                    $type = 'SERIAL';
            }
        } else if (is_int($value)) {
            $type = 'INTEGER DEFAULT 57082'; // 0xdefa
            if ($CFG->dbfamily == 'mssql') {
                $type = 'INTEGER NULL DEFAULT 57082';
            } 
        } else {
            $type = "VARCHAR($strlen) DEFAULT 'Default'";
            if ($CFG->dbfamily == 'mssql') {
                $type = "VARCHAR($strlen) NULL DEFAULT 'Default'";
            } else if ($CFG->dbfamily == 'oracle') {
                $type = "VARCHAR2($strlen) DEFAULT 'Default'";
            }
        }
        $coldefs[] = "$colname $type";
    }
    _private_execute_sql("CREATE TABLE $tablename (" . join(',', $coldefs) . ');', $db);

    if ($CFG->dbfamily == 'oracle') {
        $sql = "CREATE SEQUENCE {$tablename}_id_seq;";
        _private_execute_sql($sql, $db);
        $sql = "CREATE OR REPLACE TRIGGER {$tablename}_id_trg BEFORE INSERT ON $tablename FOR EACH ROW BEGIN IF :new.id IS NULL THEN SELECT {$tablename}_ID_SEQ.nextval INTO :new.id FROM dual; END IF; END; ";
        _private_execute_sql($sql, $db);
    }

    array_unshift($data, $colnames);
    load_test_data($tablename, $data, $db);
}

/**
 * Given a table name, a two-dimensional array of data, and a database connection,
 * adds data to the database table. The array should have the same format as for
 * load_test_table(), with the first 'row' giving column names.
 * 
 * This function should not be used in real code. Only for testing and debugging.
 *
 * @param string $tablename the name of the table to populate. E.g. 'mdl_unittest_user'.
 * @param array $data a two-dimensional array of data, in the format described.
 * @param object $localdb an AdoDB database connection.
 */
function load_test_data($tablename, $data, $localdb = null) {
    global $CFG;

    if (null == $localdb) {
        global $db;
        $localdb = $db;
    }
    $colnames = array_shift($data);
    $idcol = array_search('id', $colnames);
    $maxid = -1;
    foreach ($data as $row) {
        $savedcolnames = $colnames;
        $savedrow      = $row;
        unset($colnames[0]);
        unset($row[0]);
        _private_execute_sql($localdb->GetInsertSQL($tablename, array_combine($colnames, $row)), $localdb);
        $colnames = $savedcolnames;
        $row      = $savedrow;
        if ($idcol !== false && $row[$idcol] > $maxid) {
            $maxid = $row[$idcol];
        }
    }
    if ($CFG->dbfamily == 'postgres' && $idcol !== false) {
        $maxid += 1;
        _private_execute_sql("ALTER SEQUENCE {$tablename}_id_seq RESTART WITH $maxid;", $localdb);
    }
}

/**
 * Make multiple tables that are the same as a real table but empty.
 * 
 * This function should not be used in real code. Only for testing and debugging.
 *
 * @param mixed $tablename Array of strings containing the names of the table to populate (without prefix).
 * @param string $realprefix the prefix used for real tables. E.g. 'mdl_'.
 * @param string $testprefix the prefix used for test tables. E.g. 'mdl_unittest_'.
 * @param object $db an AdoDB database connection.
 */
function make_test_tables_like_real_one($tablenames, $realprefix, $testprefix, $db,$dropconstraints=false) {
    foreach($tablenames as $individual) {
        make_test_table_like_real_one($individual,$realprefix,$testprefix,$db,$dropconstraints);
    }
}

/**
 * Make a test table that has all the same columns as a real moodle table,
 * but which is empty.
 *
 * This function should not be used in real code. Only for testing and debugging.
 *
 * @param string $tablename Name of the table to populate. E.g. 'user'.
 * @param string $realprefix the prefix used for real tables. E.g. 'mdl_'.
 * @param string $testprefix the prefix used for test tables. E.g. 'mdl_unittest_'.
 * @param object $db an AdoDB database connection.
 */
function make_test_table_like_real_one($tablename, $realprefix, $testprefix, $db, $dropconstraints=false) {
    _private_execute_sql("CREATE TABLE $testprefix$tablename (LIKE $realprefix$tablename INCLUDING DEFAULTS);", $db);
    if (_private_has_id_column($testprefix . $tablename, $db)) {
        _private_execute_sql("CREATE SEQUENCE $testprefix{$tablename}_id_seq;", $db);
        _private_execute_sql("ALTER TABLE $testprefix$tablename ALTER COLUMN id SET DEFAULT nextval('{$testprefix}{$tablename}_id_seq'::regclass);", $db);
        _private_execute_sql("ALTER TABLE $testprefix$tablename ADD PRIMARY KEY (id);", $db);
    }
    if($dropconstraints) {
        $cols=$db->MetaColumnNames($testprefix.$tablename);
        foreach($cols as $col) {
            $rs=_private_execute_sql(
                "SELECT constraint_name FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE table_name='$testprefix$tablename'",$db);
            while(!$rs->EOF) {
                $constraintname=$rs->fields['constraint_name'];
                _private_execute_sql("ALTER TABLE $testprefix$tablename DROP CONSTRAINT $constraintname",$db);
                $rs->MoveNext();
            }

            _private_execute_sql("ALTER TABLE $testprefix$tablename ALTER COLUMN $col DROP NOT NULL",$db);
        }
    }
}

/**
 * Drops a table from the database pointed to by the database connection.
 * This undoes the create performed by load_test_table().
 *
 * This function should not be used in real code. Only for testing and debugging.
 *
 * @param string $tablename the name of the table to populate. E.g. 'mdl_unittest_user'.
 * @param object $db an AdoDB database connection.
 * @param bool $cascade If true, also drop tables that depend on this one, e.g. through
 *      foreign key constraints.
 */
function remove_test_table($tablename, $db, $cascade = false) {
    global $CFG;
    _private_execute_sql('DROP TABLE ' . $tablename . ($cascade ? ' CASCADE' : '') . ';', $db);

    if ($CFG->dbfamily == 'postgres') {
        $rs = $db->Execute("SELECT relname FROM pg_class WHERE relname = '{$tablename}_id_seq' AND relkind = 'S';");
        if ($rs && !rs_EOF($rs)) {
            _private_execute_sql("DROP SEQUENCE {$tablename}_id_seq;", $db);
        }
    }

    if ($CFG->dbfamily == 'oracle') {
       _private_execute_sql("DROP SEQUENCE {$tablename}_id_seq;", $db);
    }
}

/**
 * Drops all the tables with a particular prefix from the database pointed to by the database connection.
 * Useful for cleaning up after a unit test run has crashed leaving the DB full of junk.
 *
 * This function should not be used in real code. Only for testing and debugging.
 *
 * @param string $prefix the prfix of tables to drop 'mdl_unittest_'.
 * @param object $db an AdoDB database connection.
 */
function wipe_tables($prefix, $db) {
    if (strpos($prefix, 'test') === false) {
        notice('The wipe_tables function should only be used to wipe test tables.');
        return;
    }
    $tables = $db->Metatables('TABLES', false, "$prefix%");
    foreach ($tables as $table) {
        _private_execute_sql("DROP TABLE $table CASCADE", $db);
    }
}

/**
 * Drops all the sequences with a particular prefix from the database pointed to by the database connection.
 * Useful for cleaning up after a unit test run has crashed leaving the DB full of junk.
 *
 * This function should not be used in real code. Only for testing and debugging.
 *
 * @param string $prefix the prfix of sequences to drop 'mdl_unittest_'.
 * @param object $db an AdoDB database connection.
 */
function wipe_sequences($prefix, $db) {
    global $CFG;

    if ($CFG->dbfamily == 'postgres') {
        $sequences = $db->GetCol("SELECT relname FROM pg_class WHERE relname LIKE '$prefix%_id_seq' AND relkind = 'S';");
        if ($sequences) {
            foreach ($sequences as $sequence) {
                _private_execute_sql("DROP SEQUENCE $sequence CASCADE", $db);
            }
        }
    }
}

function _private_has_id_column($table, $db) {
    return in_array('id', $db->MetaColumnNames($table));
}

function _private_execute_sql($sql, $localdb = null) {

    global $CFG;

    if (null == $localdb) {
        global $db;
        $localdb = $db;
    }
    if ($CFG->dbfamily == 'oracle') {
        $sql = trim($sql, ';');
    }
    if (!$rs = $localdb->Execute($sql)) {
        echo '<p>SQL ERROR: ', $localdb->ErrorMsg(), ". STATEMENT: $sql</p>";
    }
    return $rs;
}

/**
 * Base class for testcases that want a different DB prefix.
 * 
 * That is, when you need to load test data into the database for
 * unit testing, instead of messing with the real mdl_course table,
 * we will temporarily change $CFG->prefix from (say) mdl_ to mdl_unittest_
 * and create a table called mdl_unittest_course to hold the test data.
 */
class prefix_changing_test_case extends UnitTestCase {
    var $old_prefix;
    
    function change_prefix() {
        global $CFG;
        $this->old_prefix = $CFG->prefix;
        $CFG->prefix = $CFG->prefix . 'unittest_';
    }

    function change_prefix_back() {
        global $CFG;
        $CFG->prefix = $this->old_prefix;
    }

    function setUp() {
        $this->change_prefix();
    }

    function tearDown() {
        $this->change_prefix_back();
    }
}
?>
