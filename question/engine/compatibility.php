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
 * These are things required to make Moodle 2.0 style code work in Moodle 1.9.
 *
 * For example cut-down renderer base classes, and so on.
 *
 * @package moodlecore
 * @subpackage questionengine
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Base Moodle Exception class
 */
class moodle_exception extends Exception {
    public $errorcode;
    public $module;
    public $a;
    public $link;
    public $debuginfo;

    /**
     * Constructor
     * @param string $errorcode The name of the string from error.php to print
     * @param string $module name of module
     * @param string $link The url where the user will be prompted to continue.
     *      If no url is provided the user will be directed to the site index page.
     * @param object $a Extra words and phrases that might be required in the error string
     * @param string $debuginfo optional debugging information
     */
    function __construct($errorcode, $module='', $link='', $a=NULL, $debuginfo=null) {
        if (empty($module) || $module == 'moodle' || $module == 'core') {
            $module = 'error';
        }

        $this->errorcode = $errorcode;
        $this->module    = $module;
        $this->link      = $link;
        $this->a         = $a;
        $this->debuginfo = $debuginfo;

        $message = get_string($errorcode, $module, $a);

        parent::__construct($message, 0);
    }
}


/**
 * Exception indicating programming error, must be fixed by a programer. For example
 * a core API might throw this type of exception if a plugin calls it incorrectly.
 *
 * @package   moodlecore
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coding_exception extends moodle_exception {
    /**
     * Constructor
     * @param string $hint short description of problem
     * @param string $debuginfo detailed information how to fix problem
     */
    function __construct($hint, $debuginfo=null) {
        parent::__construct('codingerror', 'debug', '', $hint, $debuginfo);
    }
}


/**
 * This constant is used for html attributes which need to have an empty
 * value and still be output by the renderers (e.g. alt="");
 *
 * @constant @EMPTY@
 */
define('HTML_ATTR_EMPTY', '@EMPTY@');


/**
 * This is the default renderer factory for Moodle. It simply returns an instance
 * of the appropriate standard renderer class.
 *
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class renderer_factory {
    /**
     * Implement the subclass method
     * @param string $module name such as 'core', 'mod_forum' or 'qtype_multichoice'.
     * @param moodle_page $page the page the renderer is outputting content for.
     * @param string $subtype optional subtype such as 'news' resulting to 'mod_forum_news'
     * @return object an object implementing the requested renderer interface.
     */
    public static function get_renderer($module, $subtype=null) {
        $class = self::standard_renderer_class_for_module($module, $subtype);
        return new $class(null);
    }

    /**
     * For a given module name, return the name of the standard renderer class
     * that defines the renderer interface for that module.
     *
     * Also, if it exists, include the renderer.php file for that module, so
     * the class definition of the default renderer has been loaded.
     *
     * @param string $component name such as 'core', 'mod_forum' or 'qtype_multichoice'.
     * @param string $subtype optional subtype such as 'news' resulting to 'mod_forum_news'
     * @return string the name of the standard renderer class for that module.
     */
    protected static function standard_renderer_class_for_module($component, $subtype=null) {
        global $CFG;
        $pluginrenderer = '';
        if (strpos($component, 'qtype_') === 0) {
            $pluginrenderer = $CFG->dirroot . '/question/type/' .
                    substr($component, 6) . '/renderer.php';
        } else if (strpos($component, 'qbehaviour_') === 0) {
            $pluginrenderer = $CFG->dirroot . '/question/behaviour/' .
                    substr($component, 11) . '/renderer.php';
        }
        if ($pluginrenderer && file_exists($pluginrenderer)) {
            include_once($pluginrenderer);
        }
        if (is_null($subtype)) {
            $class = $component . '_renderer';
        } else {
            $class = $component . '_' . $subtype . '_renderer';
        }
        if (!class_exists($class)) {
            throw new Exception('Request for an unknown renderer class ' . $class);
        }
        return $class;
    }
}


/**
 * Simple base class for Moodle renderers, ripped out of Moodle 2.0.
 *
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class moodle_renderer_base {
    /** @var xhtml_container_stack the xhtml_container_stack to use. */
    protected $opencontainers;
    /** @var moodle_page the page we are rendering for. */
    protected $page;

    /**
     * Constructor
     * @param moodle_page $page the page we are doing output for.
     */
    public function __construct($page) {
        $this->opencontainers = new null_continer_stack();
        $this->page = $page;
    }

    /**
     * Have we started output yet?
     * @return boolean true if the header has been printed.
     */
    public function has_started() {
        return $this->page->state >= moodle_page::STATE_IN_BODY;
    }

    /**
     * Outputs a tag with attributes and contents
     * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     * @param string $contents What goes between the opening and closing tags
     * @return string HTML fragment
     */
    protected function output_tag($tagname, $attributes, $contents) {
        return $this->output_start_tag($tagname, $attributes) . $contents .
                $this->output_end_tag($tagname);
    }

    /**
     * Outputs a tag if the contents are not empty.
     * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     * @param string $contents What goes between the opening and closing tags
     * @return string HTML fragment
     */
    protected function output_nonempty_tag($tagname, $attributes, $contents) {
        if (empty($contents)) {
            return '';
        }
        return $this->output_tag($tagname, $attributes, $contents);
    }

    /**
     * Outputs an opening tag with attributes
     * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     * @return string HTML fragment
     */
    protected function output_start_tag($tagname, $attributes = array()) {
        return '<' . $tagname . $this->output_attributes($attributes) . '>';
    }

    /**
     * Outputs a closing tag
     * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
     * @return string HTML fragment
     */
    protected function output_end_tag($tagname) {
        return '</' . $tagname . '>';
    }

    /**
     * Outputs an empty tag with attributes
     * @param string $tagname The name of tag ('input', 'img', 'br' etc.)
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     * @return string HTML fragment
     */
    protected function output_empty_tag($tagname, $attributes = array()) {
        return '<' . $tagname . $this->output_attributes($attributes) . ' />';
    }

    /**
     * Outputs a HTML attribute and value
     * @param string $name The name of the attribute ('src', 'href', 'class' etc.)
     * @param string $value The value of the attribute. The value will be escaped with {@link s()}
     * @return string HTML fragment
     */
    protected function output_attribute($name, $value) {
        if (is_array($value)) {
            debugging("Passed an array for the HTML attribute $name", DEBUG_DEVELOPER);
        }

        $value = trim($value);
        if ($value == HTML_ATTR_EMPTY) {
            return ' ' . $name . '=""';
        } else if ($value || is_numeric($value)) { // We want 0 to be output.
            return ' ' . $name . '="' . s($value) . '"';
        }
    }

    /**
     * Outputs a list of HTML attributes and values
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     *       The values will be escaped with {@link s()}
     * @return string HTML fragment
     */
    protected function output_attributes($attributes) {
        if (empty($attributes)) {
            $attributes = array();
        }
        $output = '';
        foreach ($attributes as $name => $value) {
            $output .= $this->output_attribute($name, $value);
        }
        return $output;
    }

    /**
     * Given an array or space-separated list of classes, prepares and returns the HTML class attribute value
     * @param mixed $classes Space-separated string or array of classes
     * @return string HTML class attribute value
     */
    public static function prepare_classes($classes) {
        if (is_array($classes)) {
            return implode(' ', array_unique($classes));
        }
        return $classes;
    }

    /**
     * Return the URL for an icon identified as in pre-Moodle 2.0 code.
     *
     * Suppose you have old code like $url = "$CFG->pixpath/i/course.gif";
     * then old_icon_url('i/course'); will return the equivalent URL that is correct now.
     *
     * @param string $iconname the name of the icon.
     * @return string the URL for that icon.
     */
    public function old_icon_url($iconname) {
        global $CFG;
        return $CFG->pixpath . '/' . $iconname . '.gif';
    }

    /**
     * Return the URL for an icon identified as in pre-Moodle 2.0 code.
     *
     * Suppose you have old code like $url = "$CFG->modpixpath/$mod/icon.gif";
     * then mod_icon_url('icon', $mod); will return the equivalent URL that is correct now.
     *
     * @param string $iconname the name of the icon.
     * @param string $module the module the icon belongs to.
     * @return string the URL for that icon.
     */
    public function mod_icon_url($iconname, $module) {
        global $CFG;
        return $CFG->modpixpath . '/' . $module . '/' . $iconname . '.gif';
    }
}


/**
 * Dummy implementation of the xhtml_container_stack API from Moodle 2.0.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class null_continer_stack {
    /**
     * Push the close HTML for a recently opened container onto the stack.
     * @param string $type The type of container. This is checked when {@link pop()}
     *      is called and must match, otherwise a developer debug warning is output.
     * @param string $closehtml The HTML required to close the container.
     * @return void
     */
    public function push($type, $closehtml) {
    }

    /**
     * Pop the HTML for the next closing container from the stack. The $type
     * must match the type passed when the container was opened, otherwise a
     * warning will be output.
     * @param string $type The type of container.
     * @return string the HTML required to close the container.
     */
    public function pop($type) {
        return '';
    }

    /**
     * Close all but the last open container. This is useful in places like error
     * handling, where you want to close all the open containers (apart from <body>)
     * before outputting the error message.
     * @param bool $shouldbenone assert that the stack should be empty now - causes a
     *      developer debug warning if it isn't.
     * @return string the HTML required to close any open containers inside <body>.
     */
    public function pop_all_but_last($shouldbenone = false) {
        return '';
    }

    /**
     * You can call this function if you want to throw away an instance of this
     * class without properly emptying the stack (for example, in a unit test).
     * Calling this method stops the destruct method from outputting a developer
     * debug warning. After calling this method, the instance can no longer be used.
     * @return void
     */
    public function discard() {
    }
}

function format_backtrace($callers, $plaintext = false) {
    // do not use $CFG->dirroot because it might not be available in desctructors
    $dirroot = dirname(dirname(__FILE__));

    if (empty($callers)) {
        return '';
    }

    $from = $plaintext ? '' : '<ul style="text-align: left">';
    foreach ($callers as $caller) {
        if (!isset($caller['line'])) {
            $caller['line'] = '?'; // probably call_user_func()
        }
        if (!isset($caller['file'])) {
            $caller['file'] = 'unknownfile'; // probably call_user_func()
        }
        $from .= $plaintext ? '* ' : '<li>';
        $from .= 'line ' . $caller['line'] . ' of ' . str_replace($dirroot, '', $caller['file']);
        if (isset($caller['function'])) {
            $from .= ': call to ';
            if (isset($caller['class'])) {
                $from .= $caller['class'] . $caller['type'];
            }
            $from .= $caller['function'] . '()';
        } else if (isset($caller['exception'])) {
            $from .= ': '.$caller['exception'].' thrown';
        }
        $from .= $plaintext ? "\n" : '</li>';
    }
    $from .= $plaintext ? '' : '</ul>';

    return $from;
}

/**
 * Generate the HTML for calling a javascript funtion. You often need to do this
 * if you have your javascript in an external file, and need to call one function
 * to initialise it.
 *
 * You can pass in an optional list of arguments, which are properly escaped for
 * you using the json_encode function.
 *
 * @param string $function the name of the JavaScript function to call.
 * @param array $args an optional list of arguments to the function call.
 * @param boolean $return if true, return the HTML code, otherwise output it.
 * @return mixed string if $return is true, otherwise nothing.
 */
function print_js_call($function, $args = array(), $return = false) {
    $quotedargs = array();
    foreach ($args as $arg) {
        $quotedargs[] = json_encode($arg);
    }
    $html = '';
    $html .= '<script type="text/javascript">//<![CDATA[' . "\n";
    $html .= $function . '(' . implode(', ', $quotedargs) . ");\n";
    $html .= "//]]></script>\n";
    if ($return) {
        return $html;
    } else {
        echo $html;
    }
}

/**
 * Sometimes you need access to some values in your JavaScript that you can only
 * get from PHP code. You can handle this by generating your JS in PHP, but a
 * better idea is to write static javascrip code that reads some configuration
 * variable, and then just output the configuration variables from PHP using
 * this function.
 *
 * For example, look at the code in question_init_qenginejs_script() in
 * lib/questionlib.php. It writes out a bunch of $settings like
 * 'pixpath' => $CFG->pixpath, with $prefix = 'qengine_config'. This gets output
 * in print_header, then the code in question/qengine.js can access these variables
 * as qengine_config.pixpath, and so on.
 *
 * This method will also work without a prefix, but it is better to avoid that
 * we don't want to add more things than necessary to the global JavaScript scope.
 *
 * This method automatically wrapps the values in quotes, and addslashes_js them.
 *
 * @param array $settings the values you want to write out, as variablename => value.
 * @param string $prefix a namespace prefix to use in the JavaScript.
 * @param boolean $return if true, return the HTML code, otherwise output it.
 * @return mixed string if $return is true, otherwise nothing.
 */
function print_js_config($settings = array(), $prefix='', $return = false) {
    $html = '';
    $html .= '<script type="text/javascript">//<![CDATA[' . "\n";

    // Have to treat the prefix and no prefix cases separately.
    if ($prefix) {
        // Recommended way, only one thing in global scope.
        $html .= "var $prefix = " . json_encode($settings) . "\n";

    } else {
        // Old fashioned way.
        foreach ($settings as $name => $value) {
            $html .= "var $name = '" . addslashes_js($value) . "'\n";
        }
    }

    // Finish off and return/output.
    $html .= "//]]></script>\n";
    if ($return) {
        return $html;
    } else {
        echo $html;
    }
}

define('SQL_PARAMS_NAMED', 1);
define('SQL_PARAMS_QM', 2);
/**
 * Constructs IN () or = sql fragment. Backport of the similar method from Moodle 2.0.
 * @param mixed $items single or array of values
 * @param int $type not used
 * @param string named not used
 * @param bool true means equal, false not equal/NOT IN
 * @return array - $sql and an empty array.
 */
function get_in_or_equal($items, $type=SQL_PARAMS_QM, $start='param0000', $equal=true) {
    $extra = '';
    $op = '= ';
    if (!$equal) {
        $extra = 'NOT ';
        $op = '<> ';
    }
    if (count($items) == 1) {
        return array($op . "'" . reset($items) . "'", array());
    } else {
        return array($extra . "IN ('" . implode("','", $items) . "')", array());
    }
}
