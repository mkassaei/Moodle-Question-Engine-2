<?php
/**
 * Unit tests for (some of) ../simpletestlib.php.
 *
 * @author T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package SimpleTestEx
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
require_once($CFG->dirroot . '/question/engine/compatibility.php');

/**
 * Unit tests for the ContainsTagWithAttribute class.
 *
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ContainsTagWithAttribute_test extends UnitTestCase {
    function test_simple() {
        $expectation = new ContainsTagWithAttribute('span', 'class', 'error');
        $this->assertTrue($expectation->test('<span class="error">message</span>'));
    }

    function test_other_attrs() {
        $expectation = new ContainsTagWithAttribute('span', 'class', 'error');
        $this->assertTrue($expectation->test('<span     oneattr="thingy"   class  =  "error"  otherattr="thingy">message</span>'));
    }

    function test_fails() {
        $expectation = new ContainsTagWithAttribute('span', 'class', 'error');
        $this->assertFalse($expectation->test('<span class="mismatch">message</span>'));
    }

    function test_link() {
        $html = '<a href="http://www.test.com">Click Here</a>';
        $expectation = new ContainsTagWithAttribute('a', 'href', 'http://www.test.com');
        $this->assertTrue($expectation->test($html));
    }

    function test_garbage() {
        $expectation = new ContainsTagWithAttribute('a', 'href', '!#@*%@_-)(*#0-735\\fdf//fdfg235-0970}$@}{#:~');
        $this->assertTrue($expectation->test('<a href="!#@*%@_-)(*#0-735\\fdf//fdfg235-0970}$@}{#:~">Click Here</a>'));
    }

    function test_inline_js() {
        $html = '<a title="Popup window" href="http://otheraddress.com" class="link" onclick="this.target=\'my_popup\';">Click here</a>';
        $this->assert(new ContainsTagWithAttribute('a', 'href', 'http://otheraddress.com'), $html);
    }

    function test_real_regression1() {
        $expectation = new ContainsTagWithAttribute('label', 'for', 'html_select4ac387224bf9d');
        $html = '<label for="html_select4ac387224bf9d">Cool menu</label><select name="mymenu" id="html_select4ac387224bf9d" class="menumymenu select"> <option value="0">Choose...</option><option value="10">ten</option><option value="c2">two</option></select>';
        $this->assert($expectation, $html);
    }

    function test_zero_attr() {
        $expectation = new ContainsTagWithAttribute('span', 'class', 0);
        $this->assertTrue($expectation->test('<span class="0">message</span>'));
    }

    function test_zero_attr_does_not_match_blank() {
        $expectation = new ContainsTagWithAttribute('span', 'class', 0);
        $this->assertFalse($expectation->test('<span class="">message</span>'));
    }

    function test_blank_attr() {
        $expectation = new ContainsTagWithAttribute('span', 'class', '');
        $this->assertTrue($expectation->test('<span class="">message</span>'));
    }

    function test_blank_attr_does_not_match_zero() {
        $expectation = new ContainsTagWithAttribute('span', 'class', '');
        $this->assertFalse($expectation->test('<span class="0">message</span>'));
    }
}


/**
 * Unit tests for the ContainsTagWithAttribute class.
 *
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ContainsTagWithAttributes_test extends UnitTestCase {
    function test_simple() {
        $content = <<<END
<input id="qIhr6wWLTt3,1_omact_gen_14" name="qIhr6wWLTt3,1_omact_gen_14" onclick="if(this.hasSubmitted) { return false; } this.hasSubmitted=true; preSubmit(this.form); return true;" type="submit" value="Check" />
END;
        $expectation = new ContainsTagWithAttributes('input',
                array('type' => 'submit', 'name' => 'qIhr6wWLTt3,1_omact_gen_14', 'value' => 'Check'));
        $this->assert($expectation, $content);
    }

    function test_zero_attr() {
        $expectation = new ContainsTagWithAttributes('span', array('class' => 0));
        $this->assertTrue($expectation->test('<span class="0">message</span>'));
    }

    function test_zero_attr_does_not_match_blank() {
        $expectation = new ContainsTagWithAttributes('span', array('class' => 0));
        $this->assertFalse($expectation->test('<span class="">message</span>'));
    }

    function test_blank_attr() {
        $expectation = new ContainsTagWithAttributes('span', array('class' => ''));
        $this->assertTrue($expectation->test('<span class="">message</span>'));
    }

    function test_blank_attr_does_not_match_zero() {
        $expectation = new ContainsTagWithAttributes('span', array('class' => ''));
        $this->assertFalse($expectation->test('<span class="0">message</span>'));
    }
}
