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
class renderer_base {
    /** @var xhtml_container_stack the xhtml_container_stack to use. */
    protected $opencontainers;
    /** @var object the page we are rendering for. Not used. */
    protected $page;

    /**
     * Constructor
     * @param object $page the page we are doing output for. Not used.
     */
    public function __construct($page) {
        $this->opencontainers = new null_continer_stack();
        $this->page = $page;
    }
}


// ==== HTML writer and helper classes, will be probably moved elsewhere ======

/**
 * Simple html output class
 * @copyright 2009 Tim Hunt, 2010 Petr Skoda
 */
class html_writer {
    /**
     * Outputs a tag with attributes and contents
     * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
     * @param string $contents What goes between the opening and closing tags
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     * @return string HTML fragment
     */
    public static function tag($tagname, $contents, array $attributes = null) {
        return self::start_tag($tagname, $attributes) . $contents . self::end_tag($tagname);
    }

    /**
     * Outputs an opening tag with attributes
     * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     * @return string HTML fragment
     */
    public static function start_tag($tagname, array $attributes = null) {
        return '<' . $tagname . self::attributes($attributes) . '>';
    }

    /**
     * Outputs a closing tag
     * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
     * @return string HTML fragment
     */
    public static function end_tag($tagname) {
        return '</' . $tagname . '>';
    }

    /**
     * Outputs an empty tag with attributes
     * @param string $tagname The name of tag ('input', 'img', 'br' etc.)
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     * @return string HTML fragment
     */
    public static function empty_tag($tagname, array $attributes = null) {
        return '<' . $tagname . self::attributes($attributes) . ' />';
    }

    /**
     * Outputs a tag, but only if the contents are not empty
     * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
     * @param string $contents What goes between the opening and closing tags
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     * @return string HTML fragment
     */
    public static function nonempty_tag($tagname, $contents, array $attributes = null) {
        if ($contents === '' || is_null($contents)) {
            return '';
        }
        return self::tag($tagname, $contents, $attributes);
    }

    /**
     * Outputs a HTML attribute and value
     * @param string $name The name of the attribute ('src', 'href', 'class' etc.)
     * @param string $value The value of the attribute. The value will be escaped with {@link s()}
     * @return string HTML fragment
     */
    public static function attribute($name, $value) {
        if (is_array($value)) {
            debugging("Passed an array for the HTML attribute $name", DEBUG_DEVELOPER);
        }
        if ($value instanceof moodle_url) {
            return ' ' . $name . '="' . $value->out() . '"';
        }

        // special case, we do not want these in output
        if ($value === null) {
            return '';
        }

        // no sloppy trimming here!
        return ' ' . $name . '="' . s($value) . '"';
    }

    /**
     * Outputs a list of HTML attributes and values
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     *       The values will be escaped with {@link s()}
     * @return string HTML fragment
     */
    public static function attributes(array $attributes = null) {
        $attributes = (array)$attributes;
        $output = '';
        foreach ($attributes as $name => $value) {
            $output .= self::attribute($name, $value);
        }
        return $output;
    }

    /**
     * Generates random html element id.
     * @param string $base
     * @return string
     */
    public static function random_id($base='random') {
        return uniqid($base);
    }

    /**
     * Generates a simple html link
     * @param string|moodle_url $url
     * @param string $text link txt
     * @param array $attributes extra html attributes
     * @return string HTML fragment
     */
    public static function link($url, $text, array $attributes = null) {
        $attributes = (array)$attributes;
        $attributes['href']  = $url;
        return self::tag('a', $text, $attributes);
    }

    /**
     * generates a simple checkbox with optional label
     * @param string $name
     * @param string $value
     * @param bool $checked
     * @param string $label
     * @param array $attributes
     * @return string html fragment
     */
    public static function checkbox($name, $value, $checked = true, $label = '', array $attributes = null) {
        $attributes = (array)$attributes;
        $output = '';

        if ($label !== '' and !is_null($label)) {
            if (empty($attributes['id'])) {
                $attributes['id'] = self::random_id('checkbox_');
            }
        }
        $attributes['type']    = 'checkbox';
        $attributes['value']   = $value;
        $attributes['name']    = $name;
        $attributes['checked'] = $checked ? 'selected' : null;

        $output .= self::empty_tag('input', $attributes);

        if ($label !== '' and !is_null($label)) {
            $output .= self::tag('label', $label, array('for'=>$attributes['id']));
        }

        return $output;
    }

    /**
     * Generates a simple select yes/no form field
     * @param string $name name of select element
     * @param bool $selected
     * @param array $attributes - html select element attributes
     * @return string HRML fragment
     */
    public static function select_yes_no($name, $selected=true, array $attributes = null) {
        $options = array('1'=>get_string('yes'), '0'=>get_string('no'));
        return self::select($options, $name, $selected, null, $attributes);
    }

    /**
     * Generates a simple select form field
     * @param array $options associative array value=>label ex.:
     *                array(1=>'One, 2=>Two)
     *              it is also possible to specify optgroup as complex label array ex.:
     *                array(array('Odd'=>array(1=>'One', 3=>'Three)), array('Even'=>array(2=>'Two')))
     *                array(1=>'One', '--1uniquekey'=>array('More'=>array(2=>'Two', 3=>'Three')))
     * @param string $name name of select element
     * @param string|array $selected value or arary of values depending on multiple attribute
     * @param array|bool $nothing, add nothing selected option, or false of not added
     * @param array $attributes - html select element attributes
     * @return string HTML fragment
     */
    public static function select(array $options, $name, $selected = '', $nothing = array(''=>'choosedots'), array $attributes = null) {
        $attributes = (array)$attributes;
        if (is_array($nothing)) {
            foreach ($nothing as $k=>$v) {
                if ($v === 'choose' or $v === 'choosedots') {
                    $nothing[$k] = get_string('choosedots');
                }
            }
            $options = $nothing + $options; // keep keys, do not override

        } else if (is_string($nothing) and $nothing !== '') {
            // BC
            $options = array(''=>$nothing) + $options;
        }

        // we may accept more values if multiple attribute specified
        $selected = (array)$selected;
        foreach ($selected as $k=>$v) {
            $selected[$k] = (string)$v;
        }

        if (!isset($attributes['id'])) {
            $id = 'menu'.$name;
            // name may contaion [], which would make an invalid id. e.g. numeric question type editing form, assignment quickgrading
            $id = str_replace('[', '', $id);
            $id = str_replace(']', '', $id);
            $attributes['id'] = $id;
        }

        if (!isset($attributes['class'])) {
            $class = 'menu'.$name;
            // name may contaion [], which would make an invalid class. e.g. numeric question type editing form, assignment quickgrading
            $class = str_replace('[', '', $class);
            $class = str_replace(']', '', $class);
            $attributes['class'] = $class;
        }
        $attributes['class'] = 'select ' . $attributes['class']; /// Add 'select' selector always

        $attributes['name'] = $name;

        $output = '';
        foreach ($options as $value=>$label) {
            if (is_array($label)) {
                // ignore key, it just has to be unique
                $output .= self::select_optgroup(key($label), current($label), $selected);
            } else {
                $output .= self::select_option($label, $value, $selected);
            }
        }
        return self::tag('select', $output, $attributes);
    }

    private static function select_option($label, $value, array $selected) {
        $attributes = array();
        $value = (string)$value;
        if (in_array($value, $selected, true)) {
            $attributes['selected'] = 'selected';
        }
        $attributes['value'] = $value;
        return self::tag('option', $label, $attributes);
    }

    private static function select_optgroup($groupname, $options, array $selected) {
        if (empty($options)) {
            return '';
        }
        $attributes = array('label'=>$groupname);
        $output = '';
        foreach ($options as $value=>$label) {
            $output .= self::select_option($label, $value, $selected);
        }
        return self::tag('optgroup', $output, $attributes);
    }

    /**
     * This is a shortcut for making an hour selector menu.
     * @param string $type The type of selector (years, months, days, hours, minutes)
     * @param string $name fieldname
     * @param int $currenttime A default timestamp in GMT
     * @param int $step minute spacing
     * @param array $attributes - html select element attributes
     * @return HTML fragment
     */
    public static function select_time($type, $name, $currenttime=0, $step=5, array $attributes=null) {
        if (!$currenttime) {
            $currenttime = time();
        }
        $currentdate = usergetdate($currenttime);
        $userdatetype = $type;
        $timeunits = array();

        switch ($type) {
            case 'years':
                for ($i=1970; $i<=2020; $i++) {
                    $timeunits[$i] = $i;
                }
                $userdatetype = 'year';
                break;
            case 'months':
                for ($i=1; $i<=12; $i++) {
                    $timeunits[$i] = userdate(gmmktime(12,0,0,$i,15,2000), "%B");
                }
                $userdatetype = 'month';
                $currentdate['month'] = $currentdate['mon'];
                break;
            case 'days':
                for ($i=1; $i<=31; $i++) {
                    $timeunits[$i] = $i;
                }
                $userdatetype = 'mday';
                break;
            case 'hours':
                for ($i=0; $i<=23; $i++) {
                    $timeunits[$i] = sprintf("%02d",$i);
                }
                break;
            case 'minutes':
                if ($step != 1) {
                    $currentdate['minutes'] = ceil($currentdate['minutes']/$step)*$step;
                }

                for ($i=0; $i<=59; $i+=$step) {
                    $timeunits[$i] = sprintf("%02d",$i);
                }
                break;
            default:
                throw new coding_exception("Time type $type is not supported by html_writer::select_time().");
        }

        if (empty($attributes['id'])) {
            $attributes['id'] = self::random_id('ts_');
        }
        $timerselector = self::select($timeunits, $name, $currentdate[$userdatetype], null, array('id'=>$attributes['id']));
        $label = self::tag('label', get_string(substr($type, 0, -1), 'form'), array('for'=>$attributes['id'], 'class'=>'accesshide'));

        return $label.$timerselector;
    }

    /**
     * Shortcut for quick making of lists
     * @param array $items
     * @param string $tag ul or ol
     * @param array $attributes
     * @return string
     */
    public static function alist(array $items, array $attributes = null, $tag = 'ul') {
        //note: 'list' is a reserved keyword ;-)

        $output = '';

        foreach ($items as $item) {
            $output .= html_writer::start_tag('li') . "\n";
            $output .= $item . "\n";
            $output .= html_writer::end_tag('li') . "\n";
        }

        return html_writer::tag($tag, $output, $attributes);
    }

    /**
     * Returns hidden input fields created from url parameters.
     * @param moodle_url $url
     * @param array $exclude list of excluded parameters
     * @return string HTML fragment
     */
    public static function input_hidden_params(moodle_url $url, array $exclude = null) {
        $exclude = (array)$exclude;
        $params = $url->params();
        foreach ($exclude as $key) {
            unset($params[$key]);
        }

        $output = '';
        foreach ($params as $key => $value) {
            $attributes = array('type'=>'hidden', 'name'=>$key, 'value'=>$value);
            $output .= self::empty_tag('input', $attributes)."\n";
        }
        return $output;
    }

    /**
     * Generate a script tag containing the the specified code.
     *
     * @param string $js the JavaScript code
     * @param moodle_url|string optional url of the external script, $code ignored if specified
     * @return string HTML, the code wrapped in <script> tags.
     */
    public static function script($jscode, $url=null) {
        if ($jscode) {
            $attributes = array('type'=>'text/javascript');
            return self::tag('script', "\n//<![CDATA[\n$jscode\n//]]>\n", $attributes) . "\n";

        } else if ($url) {
            $attributes = array('type'=>'text/javascript', 'src'=>$url);
            return self::tag('script', '', $attributes) . "\n";

        } else {
            return '';
        }
    }
}

// ==== JS writer and helper classes, will be probably moved elsewhere ======

/**
 * Simple javascript output class
 * @copyright 2010 Petr Skoda
 */
class js_writer {
    /**
     * Returns javascript code calling the function
     * @param string $function function name, can be complex lin Y.Event.purgeElement
     * @param array $arguments parameters
     * @param int $delay execution delay in seconds
     * @return string JS code fragment
     */
    public function function_call($function, array $arguments = null, $delay=0) {
        if ($arguments) {
            $arguments = array_map('json_encode', $arguments);
            $arguments = implode(', ', $arguments);
        } else {
            $arguments = '';
        }
        $js = "$function($arguments);";

        if ($delay) {
            $delay = $delay * 1000; // in miliseconds
            $js = "setTimeout(function() { $js }, $delay);";
        }
        return $js . "\n";
    }

    /**
     * Special function which adds Y as first argument of fucntion call.
     * @param string $function
     * @param array $extraarguments
     * @return string
     */
    public function function_call_with_Y($function, array $extraarguments = null) {
        if ($extraarguments) {
            $extraarguments = array_map('json_encode', $extraarguments);
            $arguments = 'Y, ' . implode(', ', $extraarguments);
        } else {
            $arguments = 'Y';
        }
        return "$function($arguments);\n";
    }

    /**
     * Returns JavaScript code to initialise a new object
     * @param string|null $var If it is null then no var is assigned the new object
     * @param string $class
     * @param array $arguments
     * @param array $requirements
     * @param int $delay
     * @return string
     */
    public function object_init($var, $class, array $arguments = null, array $requirements = null, $delay=0) {
        if (is_array($arguments)) {
            $arguments = array_map('json_encode', $arguments);
            $arguments = implode(', ', $arguments);
        }

        if ($var === null) {
            $js = "new $class(Y, $arguments);";
        } else if (strpos($var, '.')!==false) {
            $js = "$var = new $class(Y, $arguments);";
        } else {
            $js = "var $var = new $class(Y, $arguments);";
        }

        if ($delay) {
            $delay = $delay * 1000; // in miliseconds
            $js = "setTimeout(function() { $js }, $delay);";
        }

        if (count($requirements) > 0) {
            $requirements = implode("', '", $requirements);
            $js = "Y.use('$requirements', function(Y){ $js });";
        }
        return $js."\n";
    }

    /**
     * Returns code setting value to variable
     * @param string $name
     * @param mixed $value json serialised value
     * @param bool $usevar add var definition, ignored for nested properties
     * @return string JS code fragment
     */
    public function set_variable($name, $value, $usevar=true) {
        $output = '';

        if ($usevar) {
            if (strpos($name, '.')) {
                $output .= '';
            } else {
                $output .= 'var ';
            }
        }

        $output .= "$name = ".json_encode($value).";";

        return $output;
    }

    /**
     * Writes event handler attaching code
     * @param mixed $selector standard YUI selector for elemnts, may be array or string, element id is in the form "#idvalue"
     * @param string $event A valid DOM event (click, mousedown, change etc.)
     * @param string $function The name of the function to call
     * @param array  $arguments An optional array of argument parameters to pass to the function
     * @return string JS code fragment
     */
    public function event_handler($selector, $event, $function, array $arguments = null) {
        $selector = json_encode($selector);
        $output = "Y.on('$event', $function, $selector, null";
        if (!empty($arguments)) {
            $output .= ', ' . json_encode($arguments);
        }
        return $output . ");\n";
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
