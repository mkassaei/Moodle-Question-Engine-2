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
 * This file contains classes used to manage the navigation structures in Moodle
 * and was introduced as part of the changes occuring in Moodle 2.0
 *
 * @since 2.0
 * @package moodlecore
 * @subpackage navigation
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!function_exists('get_all_sections')) {
    /** Include course lib for its functions */
    require_once($CFG->dirroot.'/course/lib.php');
}

/**
 * This class is used to represent a node in a navigation tree
 *
 * This class is used to represent a node in a navigation tree within Moodle,
 * the tree could be one of global navigation, settings navigation, or the navbar.
 * Each node can be one of two types either a Leaf (default) or a branch.
 * When a node is first created it is created as a leaf, when/if children are added
 * the node then becomes a branch.
 *
 * @package moodlecore
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation_node {
    /** Used to identify this node a leaf (default) */
    const NODETYPE_LEAF = 0;
    /** Used to identify this node a branch, happens with children */
    const NODETYPE_BRANCH = 1;
    /** Unknown node type */
    const TYPE_UNKNOWN =    null;
    /**  System node type */
    const TYPE_SYSTEM =     0;
    /**  Category node type */
    const TYPE_CATEGORY =   10;
    /**  Course node type */
    const TYPE_COURSE =     20;
    /**  Course Structure node type */
    const TYPE_SECTION =  30;
    /**  Activity node type, e.g. Forum, Quiz */
    const TYPE_ACTIVITY =   40;
    /**  Resource node type, e.g. Link to a file, or label */
    const TYPE_RESOURCE =   50;
    /**  A custom node type, default when adding without specifing type */
    const TYPE_CUSTOM =     60;
    /**  Setting node type, used only within settings nav */
    const TYPE_SETTING =    70;
    /**  Setting node type, used only within settings nav */
    const TYPE_USER =    80;

    /** @var int Parameter to aid the coder in tracking [optional] */
    public $id = null;
    /** @var string|int The identifier for the node, used to retrieve the node */
    public $key = null;
    /** @var string The text to use for the node */
    public $text = null;
    /** @var string Short text to use if requested [optional] */
    public $shorttext = null;
    /** @var string The title attribute for an action if one is defined */
    public $title = null;
    /** @var string A string that can be used to build a help button */
    public $helpbutton = null;
    /** @var moodle_url|string|null An action for the node (link) */
    public $action = null;
    /** @var string The path to an icon to use for this node */
    public $icon = null;
    /** @var int See TYPE_* constants defined for this class */
    public $type = self::TYPE_UNKNOWN;
    /** @var int See NODETYPE_* constants defined for this class */
    public $nodetype = self::NODETYPE_LEAF;
    /** @var bool If set to true the node will be collapsed by default */
    public $collapse = false;
    /** @var bool If set to true the node will be expanded by default */
    public $forceopen = false;
    /** @var string An array of CSS classes for the node */
    public $classes = array();
    /** @var array An array of child nodes */
    public $children = array();
    /** @var bool If set to true the node will be recognised as active */
    public $isactive = false;
    /** @var string If set to true the node will be dimmed */
    public $hidden = false;
    /** @var bool If set to false the node will not be displayed */
    public $display = true;
    /** @var bool If set to true then an HR will be printed before the node */
    public $preceedwithhr = false;
    /** @var bool If set to true the the navigation bar should ignore this node */
    public $mainnavonly = false;
    /** @var bool If set to true a title will be added to the action no matter what */
    public $forcetitle = false;
    /** @var array */
    protected $namedtypes = array(0=>'system',10=>'category',20=>'course',30=>'structure',40=>'activity',50=>'resource',60=>'custom',70=>'setting', 80=>'user');
    /** @var moodle_url */
    protected static $fullmeurl = null;

    /**
     * Establish the node, with either text string or array or properites
     *
     * Called when first creating the node, requires one argument which can be either
     * a string containing the text for the node or an array or properties one of
     * which must be text.
     *
     * <code>
     * $PAGE->navigation->newitem = 'This is a new nav item';
     *  // or
     * $properties = array()
     * $properties['text'] = 'This is a new nav item';
     * $properties['short'] = 'This is a new nav item';
     * $properties['action'] = moodle_url($CFG->wwwroot.'/course/category.php');
     * $properties['icon'] = $OUTPUT->old_icon_url('i/course');
     * $properties['type'] = navigation_node::TYPE_COURSE;
     * $properties['key'] = 'newitem';
     * $PAGE->navigation->newitem = $properties;
     * </code>
     *
     * The following are properties that must/can be set in the properties array
     * <ul>
     * <li><b>text</b>: You must set text, if this is not set a coding exception is thrown.</li>
     * <li><b>short</b> <i>optional</i>: A short description used for navbar optional.</li>
     * <li><b>action</b> <i>optional</i>: This can be either a {@link moodle_url} for a link, or string that can be directly output in instead of the text.</li>
     * <li><b>icon</b> <i>optional</i>: The path to an icon to display with the node.</li>
     * <li><b>type</b> <i>optional</i>: This type of the node, defaults to TYPE_CUSTOM.</li>
     * <li><b>key</b> <i>optional</i>: This can be set to allow you to easily retrieve a node you have created.</li>
     * </ul>
     *
     * @param string|array $properties
     */
    public function __construct($properties) {
        global $PAGE;
        if (is_array($properties)) {
            if (array_key_exists('text', $properties)) {
                $this->text = $properties['text'];
            }
            if (array_key_exists('shorttext', $properties)) {
                $this->shorttext = $properties['shorttext'];
            }
            if (array_key_exists('action', $properties)) {
                $this->action = $properties['action'];
                $this->check_if_active();
            }
            if (array_key_exists('icon', $properties)) {
                $this->icon = $properties['icon'];
            }
            if (array_key_exists('type', $properties)) {
                $this->type = $properties['type'];
            } else {
                $this->type = self::TYPE_CUSTOM;
            }
            if (array_key_exists('key', $properties)) {
                $this->key = $properties['key'];
            }
        } else if (is_string($properties)) {
            $this->text = $properties;
        }
        if ($this->text === null) {
            throw new coding_exception('You must set the text for the node when you create it.');
        }
        $this->title = $this->text;
        if (strlen($this->text)>50) {
            $this->text = substr($this->text, 0, 50).'...';
        }
        if (is_string($this->shorttext) && strlen($this->shorttext)>25) {
            $this->shorttext = substr($this->shorttext, 0, 25).'...';
        }
    }

    /**
     * This function overrides the active URL that is used to compare new nodes
     * to find out if they are active.
     * If null is passed then $fullmeurl will be regenerated when the next node
     * is created/added
     */
    public static function override_active_url(moodle_url $url=null) {
        self::$fullmeurl = $url;
    }

    /**
     * This function checks if the node is the active child by comparing its action
     * to the current page URL obtained via $ME
     *
     * @staticvar moodle_url $fullmeurl
     * @return bool True is active, false otherwise
     */
    public function check_if_active() {
        global $FULLME;
        if (self::$fullmeurl == null) {
            $pos = strpos($FULLME, '?');
            if ($pos===false) {
                $pos = strlen($FULLME);
            }
            $url = substr($FULLME, 0, $pos);
            $args = substr($FULLME, strpos($FULLME, '?')+1);
            preg_match_all('#\&([^\=]*?)\=([^\&]*)#si', '&'.$args, $matches, PREG_SET_ORDER);
            self::$fullmeurl = new moodle_url($url);
            foreach ($matches as $pair) {
                self::$fullmeurl->param($pair[1],$pair[2]);
            }
        }
        if ($this->action instanceof moodle_url && $this->action->compare(self::$fullmeurl)) {
            $this->make_active();
            return true;
        } else if (is_string($this->action) && $this->action==$FULLME) {
            $this->make_active();
            return true;
        }
        return false;
    }
    /**
     * This function allows the user to add a child node to this node.
     *
     * @param string $text The text to display in the node
     * @param string $action Either a moodle_url or a bit of html to use instead of the text <i>optional</i>
     * @param int $type The type of node should be one of the const types of navigation_node <i>optional</i>
     * @param string $shorttext The short text to use for this node
     * @param string|int $key Sets the key that can be used to retrieve this node <i>optional</i>
     * @param string $icon The path to an icon to use for this node <i>optional</i>
     * @return string The key that was used for this node
     */
    public function add($text, $action=null, $type=null, $shorttext=null, $key=null, $icon=null) {
        if ($this->nodetype !== self::NODETYPE_BRANCH) {
            $this->nodetype = self::NODETYPE_BRANCH;
        }
        $itemarray = array('text'=>$text);
        if ($type!==null) {
            $itemarray['type'] = $type;
        } else {
            $type = self::TYPE_CUSTOM;
        }
        if ($action!==null) {
            $itemarray['action'] = $action;
        }

        if ($shorttext!==null) {
            $itemarray['shorttext'] = $shorttext;
        }
        if ($icon!==null) {
            $itemarray['icon'] = $icon;
        }
        if ($key===null) {
            $key = count($this->children);
        }
        $itemarray['key'] = $key;
        $this->children[$key] = new navigation_node($itemarray);
        if (($type==self::TYPE_CATEGORY) || (isloggedin() && $type==self::TYPE_COURSE)) {
            $this->children[$key]->nodetype = self::NODETYPE_BRANCH;
        }
        if ($this->hidden) {
            $this->children[$key]->hidden = true;
        }
        return $key;
    }

    /**
     * Adds a new node to a particular point by recursing through an array of node keys
     *
     * @param array $patharray An array of keys to recurse to find the correct node
     * @param string $text The text to display in the node
     * @param string|int $key Sets the key that can be used to retrieve this node <i>optional</i>
     * @param int $type The type of node should be one of the const types of navigation_node <i>optional</i>
     * @param string $action Either a moodle_url or a bit of html to use instead of the text <i>optional</i>
     * @param string $icon The path to an icon to use for this node <i>optional</i>
     * @return mixed Either the key used for the node once added or false for failure
     */
    public function add_to_path($patharray, $key=null, $text=null, $shorttext=null, $type=null, $action=null, $icon=null) {
        if (count($patharray)==0) {
            $key = $this->add($text, $action, $type, $shorttext, $key, $icon);
            return $key;
        } else {
            $pathkey = array_shift($patharray);
            $child = $this->get($pathkey);
            if ($child!==false) {
                return $child->add_to_path($patharray, $key, $text, $shorttext, $type, $action, $icon);
            } else {
                return false;
            }
        }
    }

    /**
     * Add a css class to this particular node
     * 
     * @param string $class The css class to add
     * @return bool Returns true
     */
    public function add_class($class) {
        if (!in_array($class, $this->classes)) {
            $this->classes[] = $class;
        }
        return true;
    }

    /**
     * Removes a given class from this node if it exists
     *
     * @param string $class
     * @return bool
     */
    public function remove_class($class) {
        if (in_array($class, $this->classes)) {
            $key = array_search($class,$this->classes);
            if ($key!==false) {
                unset($this->classes[$key]);
                return true;
            }
        }
        return false;
    }

    /**
     * Recurse down child nodes and collapse everything once a given
     * depth of recursion has been reached.
     *
     * This function is used internally during the initialisation of the nav object
     * after the tree has been generated to collapse it to a suitable depth.
     *
     * @param int $depth defualts to 2
     * @return bool Returns true
     */
    protected function collapse_at_depth($depth=2) {
        if ($depth>0 && $this->nodetype===self::NODETYPE_BRANCH) {
            foreach (array_keys($this->children) as $key) {
                $this->children[$key]->collapse_at_depth($depth-1);
            }
            return true;
        } else {
            $this->collapse_children();
            return true;
        }
    }

    /**
     * Collapses all of the child nodes recursion optional
     *
     * @param bool $recurse If set to true child nodes are closed recursively
     * @return bool Returns true
     */
    protected function collapse_children($recurse=true) {
        if ($this->nodetype === self::NODETYPE_BRANCH && count($this->children)>0) {
            foreach ($this->children as &$child) {
                if (!$this->forceopen) {
                    $child->collapse = true;
                }
                if ($recurse && $child instanceof navigation_node) {
                    $child->collapse_children($recurse);
                }
            }
            unset($child);
        }
        return true;
    }

    /**
     * Produce the actual HTML content for the node including any action or icon
     *
     * @param bool $shorttext If true then short text is used rather than text if it has been set
     * @return string The HTML content
     */
    public function content($shorttext=false) {
        global $OUTPUT, $CFG;
        if (!$this->display) {
            return '';
        }
        if ($shorttext && $this->shorttext!==null) {
            $content = clean_text($this->shorttext);
        } else {
            $content = clean_text($this->text);
        }
        $title = '';
        if ($this->forcetitle || ($this->shorttext!==null && $this->title !== $this->shorttext) || $this->title !== $this->text) {
             $title = $this->title;
        }

        if ($content != '' && ((is_object($this->action) && ($this->action instanceof moodle_url || $this->action instanceof html_link)) || is_string($this->action))) {
            if (!($this->action instanceof html_link)) {
                $link = new html_link();
                $link->url = $this->action;
                $link->text = clean_text($content);
            } else {
                $link = $this->action;
            }
            if ($title !== '') {
                $link->title = $title;
            }
            if ($this->hidden) {
                $link->add_class('dimmed');
            }
            $content = $OUTPUT->link($link);
        } else {
            if ($title !== '') {
                $title = ' title="'.s($title).'"';
            }
            if ($this->hidden) {
                $content = sprintf('<span class="dimmed_text"%s>%s</span>', $title, clean_text($content));
            } else {
                $content = sprintf('<span%s>%s</span>', $title, clean_text($content));
            }
        }
        if ($this->icon!==null) {
            $content = sprintf('<img src="%s" alt="" /> %s',$this->icon,$content);
        } else if ($this->helpbutton!==null) {
            $content = sprintf('%s<span class="clearhelpbutton">%s</span>',trim($this->helpbutton),$content);
        }
        return $content;
    }
    
    /**
     * Get the CSS type for this node
     * 
     * @return string
     */
    public function get_css_type() {
        if (array_key_exists($this->type, $this->namedtypes)) {
            return 'type_'.$this->namedtypes[$this->type];
        }
        return 'type_unknown';
    }

    /**
     * Find and return a child node if it exists (returns a reference to the child)
     *
     * This function is used to search for and return a reference to a child node when provided
     * with the child nodes key and type.
     * If the child is found a reference to it is returned otherwise the default is returned.
     *
     * @param string|int $key The key of the child node you are searching for.
     * @param int $type The type of the node you are searching for. Defaults to TYPE_CATEGORY
     * @param mixed $default The value to return if the child cannot be found
     * @return mixed The child node or what ever default contains (usually false)
     */
    public function find_child($key, $type=self::TYPE_CATEGORY, $default = false) {
        if (array_key_exists($key, $this->children) && $this->children[$key]->type == $type) {
            return $this->children[$key];
        } else if ($this->nodetype === self::NODETYPE_BRANCH && count($this->children)>0 && $this->type<=$type) {
            foreach ($this->children as &$child) {
                $outcome = $child->find_child($key, $type);
                if ($outcome !== false) {
                    return $outcome;
                }
            }
        }
        return $default;
    }

    /**
     * Find the active child
     *
     * @param null|int $type
     * @return navigation_node|bool
     */
    public function find_active_node($type=null) {
        if ($this->contains_active_node()) {
            if ($type!==null && $this->type===$type) {
                return $this;
            }
            if ($this->nodetype === self::NODETYPE_BRANCH && count($this->children)>0) {
                foreach ($this->children as $child) {
                    if ($child->isactive) {
                        return $child;
                    } else {
                        $outcome = $child->find_active_node($type);
                        if ($outcome!==false) {
                            return $outcome;
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Returns the depth of a child
     *
     * @param string|int $key The key for the child we are looking for
     * @param int $type The type of the child we are looking for
     * @return int The depth of the child once found
     */
    public function find_child_depth($key, $type=self::TYPE_CATEGORY) {
        $depth = 0;
        if (array_key_exists($key, $this->children) && $this->children[$key]->type == $type) {
            $depth = 1;
        } else if ($this->nodetype === self::NODETYPE_BRANCH && count($this->children)>0 && $this->type<=$type) {
            foreach ($this->children as $child) {
                $depth += $child->find_child_depth($key, $type);
            }
        }
        return $depth;
    }

    /**
     * Toogles display of nodes and child nodes based on type
     *
     * If the type of a node if more than the type specified it's display property is set to false
     * and it is not shown
     *
     * @param int $type
     * @param bool $display
     */
    public function toggle_type_display($type=self::TYPE_COURSE, $display=false) {
        if ((int)$this->type > $type) {
            $this->display = $display;
        }
        if (count($this->children)>0) {
            foreach ($this->children as $child) {
                $child->toggle_type_display($type, $display);
            }
        }
    }

    /**
     * Find out if a child (or subchild) of this node contains an active node
     *
     * @return bool True if it does fales otherwise
     */
    public function contains_active_node() {
        if ($this->nodetype === self::NODETYPE_BRANCH && count($this->children)>0) {
            foreach ($this->children as $child) {
                if ($child->isactive || $child->contains_active_node()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Find all nodes that are expandable for this node and its given children.
     *
     * This function recursively finds all nodes that are expandable by AJAX within
     * [and including] this child.
     *
     * @param array $expandable An array to fill with the HTML id's of all branches
     * that can be expanded by AJAX. This is a forced reference.
     */
    public function find_expandable(&$expandable) {
        static $branchcount;
        if ($branchcount==null) {
            $branchcount=1;
        }
        if ($this->nodetype == self::NODETYPE_BRANCH && count($this->children)==0) {
            $this->id = 'expandable_branch_'.$branchcount;
            $branchcount++;
            $expandable[] = array('id'=>$this->id,'branchid'=>$this->key,'type'=>$this->type);
        } else if ($this->nodetype==self::NODETYPE_BRANCH) {
            foreach ($this->children as $child) {
                $child->find_expandable($expandable);
            }
        }
    }

    /**
     * Used to return a child node with a given key
     *
     * This function searchs for a child node with the provided key and returns the
     * child. If the child doesn't exist then this function returns false.
     *
     * @param int|string $key The key to search for
     * @param navigation_node|bool The child if it exists or false
     */
    public function get($key) {
        if ($key===false) {
            return false;
        }
        if ($this->nodetype === self::NODETYPE_BRANCH && count($this->children)>0) {
            if (array_key_exists($key, $this->children)) {
                return $this->children[$key];
            }
        }
        return false;
    }

    /**
     * Fetch a node given a set of keys that describe its path
     *
     * @param array $keys An array of keys
     * @return navigation_node|bool The node or false
     */
    public function get_by_path($keys) {
        if (count($keys)==1) {
            $key = array_shift($keys);
            return $this->get($key);
        } else {
            $key = array_shift($keys);
            $child = $this->get($key);
            if ($child !== false) {
                return $child->get_by_path($keys);
            }
            return false;
        }
    }

    /**
     * Returns the child marked as active if there is one, false otherwise.
     *
     * @return navigation_node|bool The active node or false
     */
    public function get_active_node() {
        foreach ($this->children as $child) {
            if ($child->isactive) {
                return $child;
            }
        }
        return false;
    }

    /**
     * Mark this node as active
     *
     * This function marks the node as active my forcing the node to be open,
     * setting isactive to true, and adding the class active_tree_node
     */
     public function make_active() {
        $this->forceopen = true;
        $this->isactive = true;
        $this->add_class('active_tree_node');
     }

    /**
     * This intense little function looks for branches that are forced open
     * and checks to ensure that all parent nodes are also forced open.
     */
    public function respect_forced_open() {
        foreach ($this->children as $child) {
            $child->respect_forced_open();
            if ($child->forceopen) {
                $this->forceopen = true;
            }
        }
    }

    /**
     * This function simply removes a given child node
     *
     * @param string|int $key The key that identifies a child node
     * @return bool
     */
    public function remove_child($key) {
        if (array_key_exists($key, $this->children)) {
            unset($this->children[$key]);
            return true;
        }
        return false;
    }

    /**
     * Iterate all children and check if any of them are active
     *
     * This function iterates all children recursively until it sucecssfully marks
     * a node as active, or gets to the end of the tree.
     * This can be used on a cached branch to mark the active child.
     *
     * @return bool True is a node was marked active false otherwise
     */
    public function reiterate_active_nodes() {
        if ($this->nodetype !== self::NODETYPE_BRANCH) {
            return false;
        }
        foreach ($this->children as $child) {
            $outcome = $child->check_if_active();
            if (!$outcome && $child->nodetype === self::NODETYPE_BRANCH) {
                $outcome = $child->reiterate_active_nodes();
            }
            if ($outcome) {
                return true;
            }
        }
    }

    /**
     * This function sets the title for the node and at the same time sets
     * forcetitle to true to ensure that it is used if possible
     *
     * @param string $title
     */
    public function title($title) {
        $this->title = $title;
        $this->forcetitle = true;
    }

    /**
     * Magic Method: When we unserialise an object make it `unactive`
     *
     * This is to ensure that when we take a branch out of the cache it is not marked
     * active anymore, as we can't be sure it still is (infact it most likely isnt)
     */
    public function __wakeup(){
        $this->forceopen = false;
        $this->isactive = false;
        $this->remove_class('active_tree_node');
    }
}

/**
 * The global navigation class used for... the global navigation
 *
 * This class is used by PAGE to store the global navigation for the site
 * and is then used by the settings nav and navbar to save on processing and DB calls
 *
 * See
 * <ul>
 * <li><b>{@link lib/pagelib.php}</b> {@link moodle_page::initialise_theme_and_output()}<li>
 * <li><b>{@link lib/ajax/getnavbranch.php}</b> Called by ajax<li>
 * </ul>
 *
 * @package moodlecore
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class global_navigation extends navigation_node {
    /** @var int */
    protected $depthforward = 1;
    /** @var cache */
    protected $cache = null;
    /** @var bool */
    protected $initialised = false;

    /** @var null|int */
    public $expansionlimit = null;
    /** @var stdClass */
    public $context = null;
    /** @var mixed */
    public $expandable = null;
    /** @var bool */
    public $showemptybranches = true;
    /** @var bool  */
    protected $isloggedin = false;

    /**
     * Sets up the object with basic settings and preparse it for use
     */
    public function __construct() {
        global $CFG, $PAGE;
        if (during_initial_install()) {
            return false;
        }
        $this->key = 0;
        $this->type = self::TYPE_SYSTEM;
        $this->isloggedin = isloggedin();
        $this->text = get_string('home');
        $this->forceopen = true;
        $this->action = new moodle_url($CFG->wwwroot);
        $this->cache = new navigation_cache('navigation');
        $PAGE->requires->string_for_js('moveallsidetabstoblock','moodle');
        $regenerate = optional_param('regenerate', null, PARAM_TEXT);
        if ($regenerate==='navigation') {
            $this->cache->clear();
        }
    }

    /**
     * Override: This function generated the content of the navigation
     *
     * If an expansion limit has been set then we hide everything to after that
     * set limit type
     *
     * @return string
     */
    public function content() {
        if ($this->expansionlimit!==null) {
            $this->toggle_type_display($this->expansionlimit);
        }
        return parent::content();
    }
    
    /**
     * Initialise the navigation object, calling it to auto generate
     *
     * This function starts the navigation object automatically retrieving what it
     * needs from Moodle objects.
     *
     * It also passed Javascript args and function calls as required
     *
     * @return bool Returns true
     */
    public function initialise($jsargs = null) {
        global $PAGE, $SITE;
        if ($this->initialised || during_initial_install()) {
            return true;
        }
        $start = microtime(false);
        $this->depthforward = 1;
        $this->context = &$PAGE->context;
        $contextlevel = $this->context->contextlevel;
        if ($contextlevel == CONTEXT_COURSE && $PAGE->course->id==$SITE->id) {
            $contextlevel = 10;
        }
        $depth = 0;

        switch ($contextlevel) {
            case CONTEXT_SYSTEM: 
                $depth = $this->load_for_category(false);
                break;
            case CONTEXT_COURSECAT: 
                $depth = $this->load_for_category();
                break;
            case CONTEXT_BLOCK:
            case CONTEXT_COURSE: 
                $depth = $this->load_for_course();
                break;
            case CONTEXT_MODULE:
                $depth = $this->load_for_activity();
                break;
            case CONTEXT_USER: 
                $depth = $this->load_for_user();
                break;
        }
        $this->collapse_at_depth($this->depthforward+$depth);
        $this->respect_forced_open();
        $expandable = array();
        $this->find_expandable($expandable);
        $this->expandable = $expandable;
        $this->initialised = true;
        return true;
    }
    /**
     * This function loads the global navigation structure for a user.
     *
     * This gets called by {@link initialise()} when the context is CONTEXT_USER
     */
    protected function load_for_user() {
        global $DB, $SITE, $PAGE;
        if (!empty($PAGE->course->id)) {
            $courseid = $PAGE->course->id;
        } else {
            $courseid = optional_param('course', false, PARAM_INT);
        }
        if ($courseid!==false && $courseid!=$SITE->id) {
            $course = $DB->get_record('course', array('id'=>$courseid));
        }
        if (isset($course) && $course) {
            $this->load_for_course();
        } else {
            $this->load_categories();
        }
    }

    /**
     * Called by the initalise methods if the context was system or category
     *
     * @param bool $lookforid If system context then we dont want ID because
     *      it could be userid, courseid, or anything else
     * @return int The depth to the active(requested) node
     */
    protected function load_for_category($lookforid=true) {
        global $PAGE, $CFG;
        $id = optional_param('id', null);
        if ($lookforid && $id!==null) {
            $this->load_categories($id);
            $depth = $this->find_child_depth($id);
        } else {
            $depth = $this->load_categories();
        }
        return $depth;
    }

    /**
     * Called by the initialise methods if the context was course
     *
     * @return int The depth to the active(requested) node
     */
    protected function load_for_course() {
        global $PAGE, $CFG, $USER;
        $keys = array();
        $depth = $this->load_course_categories($keys);
        $depth += $this->load_course($keys);
        if (!$this->format_display_course_content($PAGE->course->format)) {
            $child = $this->get_by_path($keys);
            if ($child!==false) {
                $child->nodetype = self::NODETYPE_LEAF;
            }
            return $depth;
        }

        if (isloggedin() && has_capability('moodle/course:view', get_context_instance(CONTEXT_COURSE, $PAGE->course->id))) {
            $depth += $this->load_course_activities($keys);
            $depth += $this->load_course_sections($keys);
        }
        return $depth;
    }

    /**
     * Check whether the course format defines a display_course_content function
     * that can be used to toggle whether or not to display course content
     *
     * $default is set to true, which may seem counter productive, however it ensures
     * backwards compatibility for course types that havn't yet defined the callback
     *
     * @param string $format
     * @param bool $default
     * @return bool
     */
    protected function format_display_course_content($format, $default=true) {
        global $CFG;
        // 
        // 
        $formatlib = $CFG->dirroot.'/course/format/'.$format.'/lib.php';
        if (file_exists($formatlib)) {
            require_once($formatlib);
            $displayfunc = 'callback_'.$format.'_display_content';
            if (function_exists($displayfunc) && !$displayfunc()) {
                return $displayfunc();
            }
        }
        return $default;
    }

    /**
     * Internal method to load course activities into the global navigation structure
     * Course activities are activities that are in section 0
     *
     * @param array $keys By reference
     */
    protected function load_course_activities(&$keys, $course=null) {
        global $PAGE, $OUTPUT, $CFG, $FULLME;

        if ($course === null) {
            $course = $PAGE->course;
        }

        if (!$this->cache->compare('modinfo'.$course->id, $course->modinfo, false)) {
            $this->cache->{'modinfo'.$course->id} = get_fast_modinfo($course);
        }
        $modinfo =  $this->cache->{'modinfo'.$course->id};

        $resources = array('resource', 'label');
        if (!$this->cache->cached('canviewhiddenactivities')) {
            $this->cache->canviewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $this->context);
        }
        $viewhiddenactivities = $this->cache->canviewhiddenactivities;
        
        foreach ($modinfo->cms as $module) {
            if ($module->sectionnum!='0' || (!$viewhiddenactivities && !$module->visible)) {
                continue;
            }
            $icon = null;
            if (!in_array($module->modname, $resources)) {
                if ($module->icon=='') {
                    $icon = $OUTPUT->mod_icon_url('icon', $module->modname);
                }
                $url = new moodle_url($CFG->wwwroot.'/mod/'.$module->modname.'/view.php', array('id'=>$module->id));
                $type = navigation_node::TYPE_ACTIVITY;
            } else {
                $url = null;
                $type = navigation_node::TYPE_RESOURCE;
                if ($module->modname!='label') {
                    $url = new moodle_url('/mod/'.$module->modname.'/view.php', array('id'=>$module->id));
                }
                if ($module->icon!=='') {
                    $icon = $OUTPUT->old_icon_url(preg_replace('#\.(png|gif)$#i','',$module->icon));
                }
            }
            $this->add_to_path($keys, $module->id, $module->name, $module->name, $type, $url, $icon);
            $child = $this->find_child($module->id, $type);
            if ($child != false) {
                $child->title(get_string('modulename', $module->modname));
                if ($type==navigation_node::TYPE_ACTIVITY && $this->module_extends_navigation($module->modname)) {
                    $child->nodetype = self::NODETYPE_BRANCH;
                }
                if (!$module->visible) {
                    $child->hidden = true;
                }
            }
        }
    }
    /**
     * Internal function to load the activities within sections
     * 
     * @param array $keys By reference
     */
    protected function load_section_activities(&$keys, $singlesectionid=false, $course=null) {
        global $PAGE, $OUTPUT, $CFG, $FULLME;

        if ($course === null) {
            $course = $PAGE->course;
        }

        if (!$this->cache->compare('modinfo'.$course->id, $course->modinfo, false)) {
            $this->cache->{'modinfo'.$course->id} = get_fast_modinfo($course);
        }
        $modinfo =  $this->cache->{'modinfo'.$course->id};

        if (!$this->cache->cached('coursesections'.$course->id)) {
            $this->cache->{'coursesections'.$course->id} = get_all_sections($course->id);
        }
        $sections = $this->cache->{'coursesections'.$course->id};

        $resources = array('resource', 'label');

        if (!$this->cache->cached('canviewhiddenactivities')) {
            $this->cache->canviewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $this->context);
        }
        $viewhiddenactivities = $this->cache->viewhiddenactivities;
        foreach ($modinfo->cms as $module) {
            if ($module->sectionnum=='0' || (!$viewhiddenactivities && !$module->visible) || ($singlesectionid!=false && $module->sectionnum!==$singlesectionid)) {
                continue;
            }
            $icon = null;
            if (!in_array($module->modname, $resources)) {
                if ($module->icon=='') {
                    $icon = $OUTPUT->mod_icon_url('icon', $module->modname);
                }
                $url = new moodle_url($CFG->wwwroot.'/mod/'.$module->modname.'/view.php', array('id'=>$module->id));
                $type = navigation_node::TYPE_ACTIVITY;
            } else {
                $url = null;
                $type = navigation_node::TYPE_RESOURCE;
                if ($module->modname!='label') {
                    $url = new moodle_url($CFG->wwwroot.'/mod/'.$module->modname.'/view.php', array('id'=>$module->id));
                }
                if ($module->icon!=='') {
                    $icon = $OUTPUT->old_icon_url(preg_replace('#\.(png|gif)$#i','',$module->icon));
                }
            }
            $path = $keys;
            $path[] = $sections[$module->sectionnum]->id;
            $this->add_to_path($path, $module->id, $module->name, $module->name, $type, $url, $icon);
            $child = $this->find_child($module->id, $type);
            if ($child != false) {
                $child->title(get_string('modulename', $module->modname));
                if (!$module->visible) {
                    $child->hidden = true;
                }
                if ($type==navigation_node::TYPE_ACTIVITY && $this->module_extends_navigation($module->modname)) {
                    $child->nodetype = self::NODETYPE_BRANCH;
                }
            }
        }
    }

    /**
     * Check if a given module has a method to extend the navigation
     *
     * @param string $modname
     * @return bool
     */
    protected function module_extends_navigation($modname) {
        global $CFG;
        if ($this->cache->cached($modname.'_extends_navigation')) {
            return $this->cache->{$modname.'_extends_navigation'};
        }
        $file = $CFG->dirroot.'/mod/'.$modname.'/lib.php';
        $function = $modname.'_extend_navigation';
        if (function_exists($function)) {
            $this->cache->{$modname.'_extends_navigation'} = true;
            return true;
        } else if (file_exists($file)) {
            require_once($file);
            if (function_exists($function)) {
                $this->cache->{$modname.'_extends_navigation'} = true;
                return true;
            }
        }
        $this->cache->{$modname.'_extends_navigation'} = false;
        return false;
    }
    /**
     * Load the global navigation structure for an activity
     *
     * @return int
     */
    protected function load_for_activity() {
        global $PAGE, $DB;
        $keys = array();

        $sectionnum = false;
        if (!empty($PAGE->cm->section)) {
            $section = $DB->get_record('course_sections', array('id'=>$PAGE->cm->section));
            if (!empty($section->section)) {
                $sectionnum = $section->section;
            }
        }

        $depth = $this->load_course_categories($keys);
        $depth += $this->load_course($keys);
        $depth += $this->load_course_activities($keys);
        $depth += $this->load_course_sections($keys);
        $depth += $this->load_section_activities($keys,$sectionnum);
        $depth += $this->load_activity($keys);
        return $depth;
    }

    /**
     * This function loads any navigation items that might exist for an activity
     * by looking for and calling a function within the modules lib.php
     *
     * @param int $instanceid
     * @return void
     */
    protected function load_activity($keys) {
        global $DB, $CFG, $PAGE;

        $module = $DB->get_record('modules', array('id'=>$PAGE->cm->module));
        if (!$module) {
            echo "Invalid Module ID";
            return;
        }

        $node = $this->find_child($PAGE->cm->id, self::TYPE_ACTIVITY);
        if ($node) {
            $node->make_active();
            $this->context = $PAGE->course->context;
            $file = $CFG->dirroot.'/mod/'.$module->name.'/lib.php';
            $function = $module->name.'_extend_navigation';
            if (file_exists($file)) {
                require_once($file);
                if (function_exists($function)) {    
                    $function($node, $PAGE->course, $module, $PAGE->cm);
                }
            }
        }
    }

    /**
     * Recursively adds an array of category objexts to the path provided by $keys
     *
     * @param array $keys An array of keys representing the path to add to
     * @param array $categories An array of [nested] categories to add
     * @param int $depth The current depth, this ensures we don't generate more than
     *      we need to
     */
    protected function add_categories(&$keys, $categories, $depth=0) {
        global $CFG;
        if (is_array($categories) && count($categories)>0) {
            foreach ($categories as $category) {
                $url = new moodle_url($CFG->wwwroot.'/course/category.php', array('id'=>$category->id, 'categoryedit'=>'on', 'sesskey'=>sesskey()));
                $categorykey = $this->add_to_path($keys,  $category->id, $category->name, $category->name, self::TYPE_CATEGORY, $url);
                if ($depth < $this->depthforward) {
                    $this->add_categories(array_merge($keys, array($categorykey)), $category->id, $depth+1);
                }
            }
        }
    }

    /**
     * This function adds a category to the nav tree based on the categories path
     * 
     * @param stdClass $category
     */
    protected function add_category_by_path($category) {
        global $CFG;
        $url = new moodle_url($CFG->wwwroot.'/course/category.php', array('id'=>$category->id, 'categoryedit'=>'on', 'sesskey'=>sesskey()));
        $keys = explode('/',trim($category->path,'/ '));
        $currentcategory = array_pop($keys);
        $categorykey = $this->add_to_path($keys,  $category->id, $category->name, $category->name, self::TYPE_CATEGORY, $url);
        return $categorykey;
    }

    /**
     * Adds an array of courses to thier correct categories if the categories exist
     *
     * @param array $courses An array of course objects
     * @param int $categoryid An override to add the courses to
     * @return bool
     */
    public function add_courses($courses, $categoryid=null) {
        global $CFG, $OUTPUT, $SITE;
        if (is_array($courses) && count($courses)>0) {
            // Work out if the user can view hidden courses, just incase
            if (!$this->cache->cached('canviewhiddencourses')) {
                $this->cache->canviewhiddencourses = has_capability('moodle/course:viewhiddencourses', $this->context);
            }
            $canviewhidden = $this->cache->canviewhiddencourses;
            $expandcourse = $this->can_display_type(self::TYPE_SECTION);
            foreach ($courses as $course) {
                // Check if the user can't view hidden courses and if the course is hidden, if so skip and continue
                if ($course->id!=$SITE->id && !$canviewhidden && (!$course->visible || !course_parent_visible($course))) {
                    continue;
                }
                // Process this course into the nav structure
                $url = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$course->id));
                if ($categoryid===null) {
                    $category = $this->find_child($course->category);
                } else {
                    $category = $this->find_child($categoryid);
                }
                if ($category!==false) {
                    $coursekey = $category->add($course->fullname, $url, self::TYPE_COURSE, $course->shortname, $course->id, $OUTPUT->old_icon_url('i/course'));
                    if (!$course->visible) {
                        $category->get($course->id)->hidden = true;
                    }
                    if ($expandcourse!==true) {
                        $category->get($course->id)->nodetype = self::NODETYPE_LEAF;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Loads the current course into the navigation structure
     *
     * Loads the current course held by $PAGE {@link moodle_page()} into the navigation
     * structure.
     * If the course structure has an appropriate display method then the course structure
     * will also be displayed.
     *
     * @param array $keys The path to add the course to
     * @return bool
     */
    protected function load_course(&$keys, $course=null) {
        global $PAGE, $CFG, $OUTPUT;
        if ($course===null) {
            $course = $PAGE->course;
        }
        if (is_object($course)) {
            if (!$this->cache->cached('canviewhiddencourses')) {
                $this->cache->canviewhiddencourses = has_capability('moodle/course:viewhiddencourses', $this->context);
            }
            $canviewhidden = $this->cache->canviewhiddencourses;

            if (!$canviewhidden && (!$course->visible || !course_parent_visible($course))) {
                return;
            }
            $url = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$course->id));
            $keys[] = $this->add_to_path($keys, $course->id, $course->fullname, $course->shortname, self::TYPE_COURSE, $url, $OUTPUT->old_icon_url('i/course'));
            $currentcourse = $this->find_child($course->id, self::TYPE_COURSE);
            if ($currentcourse!==false){
                $currentcourse->make_active();
                if (!$course->visible) {
                    $currentcourse->hidden = true;
                }

                //Participants
                if (has_capability('moodle/course:viewparticipants', $this->context)) {
                    $participantskey = $currentcourse->add(get_string('participants'));
                    $participants = $currentcourse->get($participantskey);
                    if ($participants) {
                        $participants->mainnavonly = true;
                        $url = new moodle_url($CFG->wwwroot.'/user/index.php?id='.$course->id);
                        $participants->add(get_string('participantslist'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/users'));

                        require_once($CFG->dirroot.'/blog/lib.php');

                        $currentgroup = groups_get_course_group($course, true);
                        if ($course->id == SITEID) {
                            $filterselect = '';
                        } else if ($course->id && !$currentgroup) {
                            $filterselect = $course->id;
                        } else {
                            $filterselect = $currentgroup;
                        }
                        $filterselect = clean_param($filterselect, PARAM_INT);

                        if ($CFG->bloglevel >= 3) {
                            $participants->add(get_string('blogs','blog'), blog_get_blogs_url(array('course'=>$filterselect))->out());
                        }
                        
                        if (!empty($CFG->enablenotes) && (has_capability('moodle/notes:manage', $this->context) || has_capability('moodle/notes:view', $this->context))) {
                            $participants->add(get_string('notes','notes'), new moodle_url($CFG->wwwroot.'/notes/index.php', array('filtertype'=>'course', 'filterselect'=>$filterselect)));
                        }
                    }
                }

                // View course reports
                if (has_capability('moodle/site:viewreports', $this->context)) { // basic capability for listing of reports
                    $reportkey = $currentcourse->add(get_string('reports'), self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/stats'));
                    $reportnav = $currentcourse->get($reportkey);
                    if ($reportnav) {
                        $coursereports = get_plugin_list('coursereport');
                        foreach ($coursereports as $report=>$dir) {
                            if (has_capability('coursereport/'.$report.':view', $this->context)) {
                                $url = new moodle_url($CFG->wwwroot.'/course/report/'.$report.'/view/index.php', array('id'=>$course->id));
                                $reportnav->add(get_string($report.':view', 'coursereport_'.$report), $url, navigation_node::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/report'));
                            }
                        }
                    }
                }
            }

            if (!$this->can_display_type(self::TYPE_SECTION)) {
                if ($currentcourse!==false) {
                    $currentcourse->nodetype = self::NODETYPE_LEAF;
                }
                return true;
            }
        }
    }
    /**
     * Loads the sections for a course
     *
     * @param array $keys By reference
     * @param stdClass $course The course that we are loading sections for
     */
    protected function load_course_sections(&$keys, $course=null) {
        global $PAGE, $CFG;
        if ($course === null) {
            $course = $PAGE->course;
        }
        $structurefile = $CFG->dirroot.'/course/format/'.$course->format.'/lib.php';
        $structurefunc = 'callback_'.$course->format.'_load_content';
        if (function_exists($structurefunc)) {
            $structurefunc($this, $keys, $course);
        } else if (file_exists($structurefile)) {
            require_once $structurefile;
            if (function_exists($structurefunc)) {
                $structurefunc($this, $keys, $course);
            } else {
                $this->add_course_section_generic($keys, $course);
            }
        } else {
            $this->add_course_section_generic($keys, $course);
        }
    }
    /**
     * This function loads the sections for a course if no given course format
     * methods have been defined to do so. Thus generic
     *
     * @param array $keys By reference
     * @param stdClass $course The course object to load for
     * @param string $name String to use to describe the current section to the user
     * @param string $activeparam Request variable to look for to determine the current section
     * @return bool
     */
    public function add_course_section_generic(&$keys, $course=null, $name=null, $activeparam = null) {
        global $PAGE, $CFG, $OUTPUT;

        if ($course === null) {
            $course = $PAGE->course;
        }

        $coursesecstr = 'coursesections'.$course->id;
        if (!$this->cache->cached($coursesecstr)) {
            $sections = get_all_sections($course->id);
            $this->cache->$coursesecstr = $sections;
        } else {
            $sections = $this->cache->$coursesecstr;
        }
        
        if (!$this->cache->compare('modinfo'.$course->id, $course->modinfo, false)) {
            $this->cache->{'modinfo'.$course->id} = get_fast_modinfo($course);
        }
        $modinfo =  $this->cache->{'modinfo'.$course->id};

        $depthforward = 0;
        if (!is_array($modinfo->sections)) {
            return $keys;
        }

        if ($name === null) {
            $name = get_string('topic');
        }

        if ($activeparam === null) {
            $activeparam = 'topic';
        }

        $coursenode = $this->find_child($course->id, navigation_node::TYPE_COURSE);
        if ($coursenode!==false) {
            $coursenode->action->param($activeparam,'0');
        }

        if (!$this->cache->cached('canviewhiddenactivities')) {
            $this->cache->canviewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $this->context);
        }
        $viewhiddenactivities = $this->cache->canviewhiddenactivities;

        if (!$this->cache->cached('canviewhiddensections')) {
            $this->cache->canviewhiddensections = has_capability('moodle/course:viewhiddensections', $this->context);
        }
        $viewhiddensections = $this->cache->canviewhiddensections;

        $selectedstructure = optional_param($activeparam,false,PARAM_INT);

        // This is required to make sure that if people have reduced the number
        // of sections after adding activities to sections that no longer exist
        // we dont show them
        // MDL-20242
        $sections = array_slice($sections, 0, $course->numsections, true);

        foreach ($sections as $section) {
            if ((!$viewhiddensections && !$section->visible) || (!$this->showemptybranches && !array_key_exists($section->section, $modinfo->sections))) {
                continue;
            }
            if ($section->section!=0) {
                $sectionkeys = $keys;
                $url = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$course->id, $activeparam=>$section->section));
                $this->add_to_path($sectionkeys, $section->id, $name.' '.$section->section, null, navigation_node::TYPE_SECTION, $url);
                $sectionchild = $this->find_child($section->id, navigation_node::TYPE_SECTION);
                if ($sectionchild !== false) {
                    $sectionchild->nodetype = self::NODETYPE_BRANCH;
                    if ($sectionchild->isactive) {
                        $this->load_section_activities($sectionkeys, $section->section);
                    }
                    if (!$section->visible) {
                        $sectionchild->hidden = true;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Check if we are permitted to display a given type
     *
     * @return bool True if we are, False otherwise
     */
    protected function can_display_type($type) {
        if (!is_null($this->expansionlimit) && $this->expansionlimit < $type) {
            return false;
        }
        return true;
    }

    /**
     * Loads the categories for the current course into the navigation structure
     *
     * @param array $keys Forced reference to and array to use for the keys
     * @return int The number of categories
     */
    protected function load_course_categories(&$keys) {
        global $PAGE, $CFG, $DB;
        $categories = $PAGE->categories;
        if (is_array($categories) && count($categories)>0) {
            $categories = array_reverse($categories);
            foreach ($categories as $category) {
                $url = new moodle_url($CFG->wwwroot.'/course/category.php', array('id'=>$category->id, 'categoryedit'=>'on', 'sesskey'=>sesskey()));
                $keys[] = $this->add_to_path($keys, $category->id, $category->name, $category->name, self::TYPE_CATEGORY, $url);
            }
        }
        return count($categories);
    }

    /**
     * This is called by load_for_category to load categories into the navigation structure
     *
     * @param int $categoryid The specific category to load
     * @return int The depth of categories that were loaded
     */
    protected function load_categories($categoryid=0) {
        global $PAGE, $CFG, $DB, $USER;

        // Cache capability moodle/site:config we use this in the next bit of code
        if (!$this->cache->cached('hassiteconfig')) {
            $this->cache->hassiteconfig = has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));
        }

        // If the user is logged in (but not as a guest), doesnt have the site config capability,
        // and my courses havn't been disabled then we will show the user's courses in the
        // global navigation, otherwise we will show up to FRONTPAGECOURSELIMIT available courses
        if (isloggedin() && !$this->cache->hassiteconfig && !isguest() && empty($CFG->disablemycourses)) {
            if (!$this->cache->cached('mycourses')) {
                $this->cache->mycourses = get_my_courses($USER->id);
            }
            $courses = $this->cache->mycourses;
        } else {
            // Check whether we have already cached the available courses
            if (!$this->cache->cached('availablecourses')) {
                // Non-cached - get accessinfo
                if (isset($USER->access)) {
                    $accessinfo = $USER->access;
                } else {
                    $accessinfo = get_user_access_sitewide($USER->id);
                }
                // Get the available courses using get_user_courses_bycap
                $this->cache->availablecourses = get_user_courses_bycap($USER->id, 'moodle/course:view',
                                                                        $accessinfo, true, 'c.sortorder ASC',
                                                                        array('fullname','visible', 'category'),
                                                                        FRONTPAGECOURSELIMIT);
            }
            // Cache the available courses for a refresh
            $courses = $this->cache->availablecourses;
        }

        // Iterate through all courses, and explode thier course category paths so that
        // we can retrieve all of the individual category id's that are required
        // to display the list of courses in the tree
        $categoryids = array();
        foreach ($courses as $course) {
            // If a category id has been specified and the current course is not within
            // that category or one of its children then skip this course
            if ($categoryid!==0 && !preg_match('#/('.$categoryid.')(\/|$)#', $course->categorypath)) {
                continue;
            }
            $categorypathids = explode('/',trim($course->categorypath,' /'));
            // If no category has been specified limit the depth we display immediatly to
            // that of the nav var depthforwards
            if ($categoryid===0 && count($categorypathids)>($this->depthforward+1)) {
                $categorypathids = array_slice($categorypathids, 0, ($this->depthforward+1));
            }
            $categoryids = array_merge($categoryids, $categorypathids);
        }
        // Remove duplicate categories (and there will be a few)
        $categoryids = array_unique($categoryids);

        // Check whether we have some category ids to display and make sure that either
        // no category has been specified ($categoryid===0) or that the category that
        // has been specified is in the list.
        if (count($categoryids)>0 && ($categoryid===0 || in_array($categoryid, $categoryids))) {
            $catcachestr = 'categories'.join($categoryids);
            if (!$this->cache->cached($catcachestr)) {
                $this->cache->{$catcachestr} = $DB->get_records_select('course_categories', 'id IN ('.join(',', $categoryids).')', array(), 'path ASC, sortorder ASC');
            }
            $categories = $this->cache->{$catcachestr};
            // Retrieve the nessecary categories and then proceed to add them to the tree
            foreach ($categories as $category) {
                $this->add_category_by_path($category);
            }
            // Add the courses that were retrieved earlier to the
            $this->add_courses($courses);
        } else {
            $keys = array();
            if ($categoryid!=0) {
                if (!$this->cache->cached('category'.$categoryid)) {
                    $this->cache->{'category'.$categoryid} = $DB->get_record('course_categories', array('id' => $categoryid), 'id,name,path');
                }
                $category = $this->cache->{'category'.$categoryid};
                if ($category!=false) {
                    $keys = explode('/',trim($category->path,'/ '));
                    $categories = $DB->get_records_select('course_categories', 'id IN ('.join(',', $keys).')', array(), 'path ASC, sortorder ASC');
                    foreach ($categories as $category) {
                        $this->add_category_by_path($category);
                    }
                }
            }   
            $categories = $DB->get_records('course_categories', array('parent' => $categoryid), 'sortorder ASC');
            $this->add_categories($keys, $categories);
            #$courses = $DB->get_records('course', array('category' => $categoryid), 'sortorder ASC', 'id,fullname,shortname,visible,category'); 
            $this->add_courses($courses, $categoryid);
        }
        return 0;
    }
}

/**
 * The limited global navigation class used for the AJAX extension of the global
 * navigation class.
 *
 * The primary methods that are used in the global navigation class have been overriden
 * to ensure that only the relevant branch is generated at the root of the tree.
 * This can be done because AJAX is only used when the backwards structure for the
 * requested branch exists.
 * This has been done only because it shortens the amounts of information that is generated
 * which of course will speed up the response time.. because no one likes laggy AJAX.
 *
 * @package moodlecore
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class limited_global_navigation extends global_navigation {
    /**
     * Initialise the limited navigation object, calling it to auto generate
     *
     * This function can be used to initialise the global navigation object more
     * flexibly by providing a couple of overrides.
     * This is used when the global navigation is being generated without other fully
     * initialised Moodle objects
     *
     * @param int $type What to load for e.g. TYPE_SYSTEM
     * @param int $instanceid The instance id for what ever is being loaded
     * @return array An array of nodes that are expandable by AJAX
     */
    public function initialise($type, $instanceid) {
        if ($this->initialised || during_initial_install()) {
            return $this->expandable;
        }
        $depth = 0;
        switch ($type) {
            case self::TYPE_CATEGORY: 
                $depth = $this->load_category($instanceid);
                break;
            case self::TYPE_COURSE: 
                $depth = $this->load_course($instanceid);
                break;
            case self::TYPE_SECTION: 
                $depth = $this->load_section($instanceid);
                break;
            case self::TYPE_ACTIVITY: 
                $depth = $this->load_activity($instanceid);
                break;
        }
        $this->collapse_at_depth($this->depthforward+$depth);
        $this->respect_forced_open();
        $expandable = array();
        $this->find_expandable($expandable);
        $this->expandable = $expandable;
        $this->initialised = true;
        return $expandable;
    }

    /**
     * Loads the content (sub categories and courses) for a given a category
     *
     * @param int $instanceid
     */
    protected function load_category($instanceid) {
        if (!$this->cache->cached('coursecontext'.$instanceid)) {
            $this->cache->{'coursecontext'.$instanceid} = get_context_instance(CONTEXT_COURSE, $instanceid);
        }
        $this->context = $this->cache->{'coursecontext'.$instanceid};
        $this->load_categories($instanceid);
    }

    /**
     * Use the instance id to load a course
     * 
     * {@link global_navigation::load_course()}
     * @param int $instanceid
     */
    protected function load_course($instanceid) {
        global $DB, $PAGE;

        if (!$this->cache->cached('course'.$instanceid)) {
            $this->cache->{'course'.$instanceid} = $DB->get_record('course', array('id'=>$instanceid));
        }
        $course = $this->cache->{'course'.$instanceid};

        if (!$course) {
            echo "Invalid Course ID";
            break;
        }

        if (!$this->format_display_course_content($course->format)) {
            return true;
        }

        if (!$this->cache->cached('coursecontext'.$course->id)) {
            $this->cache->{'coursecontext'.$course->id} = get_context_instance(CONTEXT_COURSE, $course->id);
        }
        $this->context = $this->cache->{'coursecontext'.$course->id};

        $keys = array();
        parent::load_course($keys, $course);

        if (isloggedin() && has_capability('moodle/course:view', get_context_instance(CONTEXT_COURSE, $instanceid))) {

            if (!$this->cache->cached('course'.$course->id.'section0')) {
                $this->cache->{'course'.$course->id.'section0'} = $DB->get_record('course_sections', array('course'=>$course->id, 'section'=>'0'));
            }
            $section = $this->cache->{'course'.$course->id.'section0'};
            $this->load_section_activities($course, $section);
            if ($this->depthforward>0) {
                $this->load_course_sections($keys, $course);
            }
        }
    }
    /**
     * Use the instance id to load a specific course section
     *
     * @param int $instanceid
     */
    protected function load_section($instanceid=0) {
        global $DB, $PAGE, $CFG;
        $section = $DB->get_record('course_sections', array('id'=>$instanceid));
        
        if (!$section) {
            echo "Invalid Course Section ID";
        }

        if (!$this->cache->cached('course'.$section->course)) {
            $this->cache->{'course'.$section->course} = $DB->get_record('course', array('id'=>$section->course));
        }
        $course = $this->cache->{'course'.$section->course};
        if (!$course) {
            echo "Invalid Course ID";
        }

        if (!$this->cache->cached('coursecontext'.$course->id)) {
            $this->cache->{'coursecontext'.$course->id} = get_context_instance(CONTEXT_COURSE, $course->id);
        }
        $this->context = $this->cache->{'coursecontext'.$course->id};

        // Call the function to generate course section
        $keys = array();
        $structurefile = $CFG->dirroot.'/course/format/'.$course->format.'/navigation_format.php';
        $structurefunc = 'callback_'.$course->format.'_load_limited_section';
        if (function_exists($structurefunc)) {
            $sectionnode = $structurefunc($this, $keys, $course, $section);
        } else if (file_exists($structurefile)) {
            include $structurefile;
            if (function_exists($structurefunc)) {
                $sectionnode = $structurefunc($this, $keys, $course, $section);
            } else {
                $sectionnode = $this->limited_load_section_generic($keys, $course, $section);
            }
        } else {
            $sectionnode = $this->limited_load_section_generic($keys, $course, $section);
        }
        if ($this->depthforward>0) {
            $this->load_section_activities($course, $section);
        }
    }
    /**
     * This function is called if there is no specific course format function set
     * up to load sections into the global navigation.
     *
     * Note that if you are writing a course format you can call this function from your
     * callback function if you don't want to load anything special but just specify the
     * GET argument that identifies the current section as well as the string that
     * can be used to describve the section. e.g. weeks or topic
     *
     * @param array $keys
     * @param stdClass $course
     * @param stdClass $section
     * @param string $name
     * @param string $activeparam
     * @return navigation_node|bool
     */
    public function limited_load_section_generic($keys, $course, $section, $name=null, $activeparam = null) {
        global $PAGE, $CFG;
        if ($name === null) {
            $name = get_string('topic');
        }

        if ($activeparam === null) {
            $activeparam = 'topic';
        }

        if (!$this->cache->cached('canviewhiddensections')) {
            $this->cache->canviewhiddensections = has_capability('moodle/course:viewhiddensections', $this->context);
        }
        $viewhiddensections = $this->cache->canviewhiddensections;

        $selectedstructure = optional_param($activeparam,false,PARAM_INT);
        if (!$viewhiddensections && !$section->visible) {
            continue;
        }
        if ($section->section!=0) {
            $url = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$course->id, $activeparam=>$section->id));
            $keys[] = $this->add_to_path($keys, $section->id, $name.' '.$section->section, null, navigation_node::TYPE_SECTION, $url);
            $sectionchild = $this->find_child($section->id, navigation_node::TYPE_SECTION);
            if ($sectionchild !== false) {
                $sectionchild->nodetype = self::NODETYPE_BRANCH;
                $sectionchild->make_active();
                if (!$section->visible) {
                    $sectionchild->hidden = true;
                }
                return $sectionchild;
            }
        }
        return false;
    }

    /**
     * This function is used to load a course sections activities
     *
     * @param stdClass $course
     * @param stdClass $section
     * @return void
     */
    protected function load_section_activities($course, $section) {
        global $OUTPUT, $CFG;
        if (!is_object($section)) {
            return;
        }
        if ($section->section=='0') {
            $keys = array($section->course);
        } else {
            $keys = array($section->id);
        }

        $modinfo = get_fast_modinfo($course);
        
        $resources = array('resource', 'label');

        if (!$this->cache->cached('canviewhiddenactivities')) {
            $this->cache->canviewhiddenactivities = has_capability('moodle/course:viewhiddenactivities', $this->context);
        }
        $viewhiddenactivities = $this->cache->canviewhiddenactivities;
        
        foreach ($modinfo->cms as $module) {
            if ((!$viewhiddenactivities && !$module->visible) || $module->sectionnum != $section->section) {
                continue;
            }
            $icon = null;
            if (!in_array($module->modname, $resources)) {
                if ($module->icon=='') {
                    $icon = $OUTPUT->mod_icon_url('icon', $module->modname);
                }
                $url = new moodle_url($CFG->wwwroot.'/mod/'.$module->modname.'/view.php', array('id'=>$module->id));
                $type = navigation_node::TYPE_ACTIVITY;
            } else {
                $url = null;
                $type = navigation_node::TYPE_RESOURCE;
                if ($module->modname!='label') {
                    $url = new moodle_url($CFG->wwwroot.'/mod/'.$module->modname.'/view.php', array('id'=>$module->id));
                }
                if ($module->icon!=='') {
                    $icon = $OUTPUT->old_icon_url(preg_replace('#\.(png|gif)$#i','',$module->icon));
                }
            }
            $this->add_to_path($keys, $module->id, $module->name, $module->name, $type, $url, $icon);
            $child = $this->find_child($module->id, $type);
            if ($child != false) {
                $child->title(get_string('modulename', $module->modname));
                if (!$module->visible) {
                    $child->hidden = true;
                }
                if ($type==navigation_node::TYPE_ACTIVITY && $this->module_extends_navigation($module->modname)) {
                    $child->nodetype = self::NODETYPE_BRANCH;
                }
            }
        }
    }

    /**
     * This function loads any navigation items that might exist for an activity
     * by looking for and calling a function within the modules lib.php
     *
     * @param int $instanceid
     * @return void
     */
    protected function load_activity($instanceid) {
        global $DB, $CFG;
        $cm = $DB->get_record('course_modules', array('id'=>$instanceid));
        if (!$cm) {
            echo "Invalid Course Module ID";
            return;
        }
        $module = $DB->get_record('modules', array('id'=>$cm->module));
        if (!$module) {
            echo "Invalid Module ID";
            return;
        }
        $course = $DB->get_record('course', array('id'=>$cm->course));
        if (!$course) {
            echo "Invalid Course ID";
            return;
        }
        $this->context = get_context_instance(CONTEXT_COURSE, $course->id);

        $key = $this->add($module->name, null, self::TYPE_ACTIVITY, null, $instanceid);

        $file = $CFG->dirroot.'/mod/'.$module->name.'/lib.php';
        $function = $module->name.'_extend_navigation';

        if (file_exists($file)) {
            require_once($file);
            if (function_exists($function)) {
                $node = $this->get($key);
                $function($node, $course, $module, $cm);
            }
        }
    }
}

/**
 * Navbar class
 *
 * This class is used to manage the navbar, which is initialised from the navigation
 * object held by PAGE
 *
 * @package moodlecore
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navbar extends navigation_node {
    /** @var bool */
    protected $initialised = false;
    /** @var mixed */
    protected $keys = array();
    /** @var null|string */
    protected $content = null;
    /** @var page object */
    protected $page;
    /** @var bool */
    protected $ignoreactive = false;
    /** @var bool */
    protected $duringinstall = false;

    /**
     * The almighty constructor
     */
    public function __construct(&$page) {
        global $SITE, $CFG;
        if (during_initial_install()) {
            $this->duringinstall = true;
            return false;
        }
        $this->page = $page;
        $this->text = get_string('home');
        $this->shorttext = get_string('home');
        $this->action = new moodle_url($CFG->wwwroot);
        $this->nodetype = self::NODETYPE_BRANCH;
        $this->type = self::TYPE_SYSTEM;
    }

    /**
     * Quick check to see if the navbar will have items in.
     *
     * @return bool Returns true if the navbar will have items, false otherwise
     */
    public function has_items() {
        if ($this->duringinstall) {
            return false;
        }
        $this->page->navigation->initialise();
        return (count($this->page->navbar->children)>0 || (!$this->ignoreactive && (
                        $this->page->navigation->contains_active_node() ||
                        $this->page->settingsnav->contains_active_node())
                ));
    }

    public function ignore_active($setting=true) {
        $this->ignoreactive = ($setting);
    }

    /**
     * Generate the XHTML content for the navbar and return it
     *
     * We are lucky in that we can rely on PAGE->navigation for the structure
     * we simply need to look for the `active` path through the tree. We make this
     * easier by calling {@link strip_down_to_final_active()}.
     *
     * This function should in the future be refactored to work with a copy of the
     * PAGE->navigation object and strip it down to just this the active nodes using
     * a function that could be written again navigation_node called something like
     * strip_inactive_nodes(). I wrote this originally but currently the navigation
     * object is managed via references.
     *
     * @return string XHTML navbar content
     */
    public function content() {
        if ($this->duringinstall) {
            return '';
        }

        // Make sure that navigation is initialised
        $this->page->navigation->initialise();

        if ($this->content !== null) {
            return $this->content;
        }

        // For screen readers
        $output = get_accesshide(get_string('youarehere','access'), 'h2')."<ul>\n";
        
        $customchildren = (count($this->children) > 0);
        // Check if navigation contains the active node
        if (!$this->ignoreactive && $this->page->navigation->contains_active_node()) {
            // Parse the navigation tree to get the active node
            $output .= $this->parse_branch_to_html($this->page->navigation->children, true, $customchildren);
        } else if (!$this->ignoreactive && $this->page->settingsnav->contains_active_node()) {
            // Parse the settings navigation to get the active node
            $output .= $this->parse_branch_to_html($this->page->settingsnav->children, true, $customchildren);
        } else {
            $output .= $this->parse_branch_to_html($this, true, $customchildren);
        }
        // Check if there are any children added by code
        if ($customchildren) {
            // Add the custom children
            $output .= $this->parse_branch_to_html($this->children, false, false);
        }
        $output .= "</ul>\n";
        $this->content = $output;
        return $output;
    }
    /**
     * This function converts an array of nodes into XHTML for the navbar
     *
     * @param array $navarray
     * @param bool $firstnode
     * @return string HTML
     */
    protected function parse_branch_to_html($navarray, $firstnode=true, $moreafterthis) {
        $separator = get_separator();
        $output = '';
        if ($firstnode===true) {
            // If this is the first node add the class first and display the
            // navbar properties (normally sitename)
            $output .= '<li class="first">'.parent::content(true).'</li>';
        }
        $count = 0;
        if (!is_array($navarray)) return $output;
        // Iterate the navarray and display each node
        while (count($navarray)>0) {
            // Sanity check make sure we don't display WAY too much information
            // on the navbar. If we get to 20 items just stop!
            $count++;
            if ($count>20) {
                // Maximum number of nodes in the navigation branch
                return $output;
            }
            $child = false;
            // Iterate the nodes in navarray and finde the active node
            foreach ($navarray as $tempchild) {
                if ($tempchild->isactive || $tempchild->contains_active_node()) {
                    $child = $tempchild;
                    // We've got the first child we can break out of this foreach
                    break;
                }
            }
            // Check if we found the child
            if ($child===false || $child->mainnavonly) {
                // Set navarray to an empty array so that we complete the while
                $navarray = array();
            } else {
                // We found an/the active node, set navarray to it's children so that
                // we come back through this while with the children of the active node
                $navarray = $child->children;
                // If there are not more arrays being processed after this AND this is the last element
                // then we want to set the action to null so that it is not used
                if (!$moreafterthis && (!$child->contains_active_node() || ($child->find_active_node()==false || $child->find_active_node()->mainnavonly))) {
                    $oldaction = $child->action;
                    $child->action = null;
                }
                // Now display the node
                $output .= '<li>'.$separator.' '.$child->content(true).'</li>';
                if (isset($oldaction)) {
                    $child->action = $oldaction;
                }
            }
        }
        // XHTML
        return $output;
    }
    /**
     * Add a new node to the navbar, overrides parent::add
     *
     * This function overrides {@link navigation_node::add()} so that we can change
     * the way nodes get added to allow us to simply call add and have the node added to the
     * end of the navbar
     *
     * @param string $text
     * @param string|moodle_url $action
     * @param int $type
     * @param string|int $key
     * @param string $shorttext
     * @param string $icon
     * @return string|int Identifier for this particular node
     */
    public function add($text, $action=null, $type=self::TYPE_CUSTOM, $shorttext=null, $key=null, $icon=null) {
        // Check if there are any keys in the objects keys array
        if (count($this->keys)===0) {
            // If there are no keys then we can use the add method
            $key = parent::add($text, $action, $type, $shorttext, $key, $icon);
        } else {
            $key = $this->add_to_path($this->keys, $key, $text, $shorttext, $type, $action, $icon);
        }
        $this->keys[] = $key;
        $child = $this->get_by_path($this->keys);
        if ($child!==false) {
            // This ensure that the child will be shown
            $child->make_active();
        }
        return $key;
    }
}

/**
 * Class used to manage the settings option for the current page
 *
 * This class is used to manage the settings options in a tree format (recursively)
 * and was created initially for use with the settings blocks.
 *
 * @package moodlecore
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_navigation extends navigation_node {
    /** @var stdClass */
    protected $context;
    /** @var cache */
    protected $cache;
    /** @var page object */
    protected $page;
    /**
     * Sets up the object with basic settings and preparse it for use
     */
    public function __construct(&$page) {
        if (during_initial_install()) {
            return false;
        }
        static $settingsnavcount;
        $this->page = $page;
        // Initialise the main navigation. It is most important that this is done
        // before we try anything
        $this->page->navigation->initialise();
        // Initialise the navigation cache
        $this->cache = new navigation_cache('navigation');
    }
    /**
     * Initialise the settings navigation based on the current context
     *
     * This function initialises the settings navigation tree for a given context
     * by calling supporting functions to generate major parts of the tree.
     */
    public function initialise() {
        global $SITE, $OUTPUT, $CFG, $ME;
        if (during_initial_install()) {
            return false;
        }
        $this->id = 'settingsnav';
        $this->context = $this->page->context;
        switch ($this->context->contextlevel) {
            case CONTEXT_SYSTEM:
                $adminkey = $this->load_administration_settings();
                $settingskey = $this->load_user_settings(SITEID);
                break;
            case CONTEXT_COURSECAT:
                $adminkey = $this->load_administration_settings();
                $adminnode = $this->get($adminkey);
                if ($adminnode!==false) {
                    $adminnode->forceopen =  true;
                }
                $settingskey = $this->load_user_settings(SITEID);
                break;
            case CONTEXT_COURSE:
                if ($this->page->course->id!==SITEID) {
                    $coursekey = $this->load_course_settings();
                    $coursenode = $this->get($coursekey);
                    if ($coursenode!==false) {
                        $coursenode->forceopen =  true;
                    }
                    $settingskey = $this->load_user_settings($this->page->course->id);
                    $adminkey = $this->load_administration_settings();
                } else {
                    $this->load_front_page_settings();
                    $settingskey = $this->load_user_settings($SITE->id);
                    $adminkey = $this->load_administration_settings();
                }
                break;
            case CONTEXT_MODULE:
                $modulekey = $this->load_module_settings();
                $modulenode = $this->get($modulekey);
                if ($modulenode!==false) {
                    $modulenode->forceopen =  true;
                }
                $coursekey = $this->load_course_settings();
                $settingskey = $this->load_user_settings($this->page->course->id);
                $adminkey = $this->load_administration_settings();
                break;
            case CONTEXT_USER:
                $settingskey = $this->load_user_settings($this->page->course->id);
                $settingsnode = $this->get($settingskey);
                if ($settingsnode!==false) {
                    $settingsnode->forceopen =  true;
                }
                if ($this->page->course->id!==SITEID) {
                    $coursekey = $this->load_course_settings();
                }
                $adminkey = $this->load_administration_settings();
                break;
            default:
                debugging('An unknown context has passed into settings_navigation::initialise', DEBUG_DEVELOPER);
                break;
        }

        // Check if the user is currently logged in as another user
        if (session_is_loggedinas()) {
            // Get the actual user, we need this so we can display an informative return link
            $realuser = session_get_realuser();
            // Add the informative return to original user link
            $url = new moodle_url($CFG->wwwroot.'/course/loginas.php',array('id'=>$this->page->course->id, 'return'=>1,'sesskey'=>sesskey()));
            $this->add(get_string('returntooriginaluser', 'moodle', fullname($realuser, true)), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('t/left'));
        }

        // Make sure the first child doesnt have proceed with hr set to true
        reset($this->children);
        current($this->children)->preceedwithhr = false;

        $this->remove_empty_root_branches();
        $this->respect_forced_open();
    }
    /**
     * Override the parent function so that we can add preceeding hr's and set a
     * root node class against all first level element
     *
     * It does this by first calling the parent's add method {@link navigation_node::add()}
     * and then proceeds to use the key to set class and hr 
     *
     * @param string $text
     * @param sting|moodle_url $url
     * @param string $shorttext
     * @param string|int $key
     * @param int $type
     * @param string $icon
     * @return sting|int A key that can be used to reference the newly added node
     */
    public function add($text, $url=null, $type=null, $shorttext=null, $key=null, $icon=null) {
        $key = parent::add($text, $url, $type, $shorttext, $key, $icon);
        $this->get($key)->add_class('root_node');
        $this->get($key)->preceedwithhr = true;
        return $key;
    }

    /**
     * This function allows the user to add something to the start of the settings
     * navigation, which means it will be at the top of the settings navigation block
     *
     * @param string $text
     * @param sting|moodle_url $url
     * @param string $shorttext
     * @param string|int $key
     * @param int $type
     * @param string $icon
     * @return sting|int A key that can be used to reference the newly added node
     */
    public function prepend($text, $url=null, $type=null, $shorttext=null, $key=null, $icon=null) {
        $key = $this->add($text, $url, $type, $shorttext, $key, $icon);
        $children = $this->children;
        $this->children = array();
        $this->children[$key] = array_pop($children);
        foreach ($children as $k=>$child) {
            $this->children[$k] = $child;
            $this->get($k)->add_class('root_node');
            $this->get($k)->preceedwithhr = true;
        }
        return $key;
    }
    /**
     * Load the site administration tree
     *
     * This function loads the site administration tree by using the lib/adminlib library functions
     *
     * @param navigation_node $referencebranch A reference to a branch in the settings
     *      navigation tree
     * @param null|object $adminbranch The branch to add, if null generate the admin
     *      tree and start at the beginning
     * @return mixed A key to access the admin tree by
     */
    protected function load_administration_settings($referencebranch=null, $adminbranch=null) {
        global $CFG, $OUTPUT, $FULLME, $ME;
        // Check if we are just starting to generate this navigation.
        if ($referencebranch === null) {
            // Check if we have cached an appropriate generation of the admin branch
            if (!$this->cache->cached('adminbranch')) {
                // We dont have a cached admin branch for this page so regenerate
                if (!function_exists('admin_get_root')) {
                    require_once($CFG->dirroot.'/lib/adminlib.php');
                }
                $adminroot = admin_get_root();
                $branchkey = $this->add(get_string('administrationsite'),null, self::TYPE_SETTING);
                $referencebranch = $this->get($branchkey);
                foreach ($adminroot->children as $adminbranch) {
                    $this->load_administration_settings($referencebranch, $adminbranch);
                }
                $this->cache->adminbranch = $this->get($branchkey);
            } else {
                // We have a cached admin branch so we simply need to stick it back in the tree
                $adminbranch = $this->cache->adminbranch;
                $outcome = $adminbranch->reiterate_active_nodes();
                $branchkey = count($this->children);
                $adminbranch->key = $branchkey;
                $this->nodetype = self::NODETYPE_BRANCH;
                $this->children[$branchkey] = $adminbranch;
            }
            // Return the branch key
            return $branchkey;
        } else if ($adminbranch->check_access() && !$adminbranch->is_hidden()) {
            // We have a reference branch that we can access and is not hidden `hurrah`
            // Now we need to display it and any children it may have
            $url = null;
            $icon = null;
            if ($adminbranch instanceof admin_settingpage) {
                $url = new moodle_url($CFG->wwwroot.'/'.$CFG->admin.'/settings.php', array('section'=>$adminbranch->name));
            } else if ($adminbranch instanceof admin_externalpage) {
                $url = $adminbranch->url;
            }

            // Add the branch
            $branchkey = $referencebranch->add($adminbranch->visiblename, $url, self::TYPE_SETTING, null, null, $icon);
            $reference = $referencebranch->get($branchkey);
            // Check if we are generating the admin notifications and whether notificiations exist
            if ($adminbranch->name === 'adminnotifications' && admin_critical_warnings_present()) {
                $reference->add_class('criticalnotification');
            }
            // Check if this branch has children
            if ($reference && isset($adminbranch->children) && is_array($adminbranch->children) && count($adminbranch->children)>0) {
                foreach ($adminbranch->children as $branch) {
                    // Generate the child branches as well now using this branch as the reference
                    $this->load_administration_settings($reference, $branch);
                }
            } else {
                $reference->icon = $OUTPUT->old_icon_url('i/settings');
            }
        }
    }

    /**
     * Generate the list of modules for the given course.
     *
     * The array of resources and activities that can be added to a course is then
     * stored in the cache so that we can access it for anywhere.
     * It saves us generating it all the time
     *
     * <code php>
     * // To get resources:
     * $this->cache->{'course'.$courseid.'resources'}
     * // To get activities:
     * $this->cache->{'course'.$courseid.'activities'}
     * </code>
     *
     * @param stdClass $course The course to get modules for
     */
    protected function get_course_modules($course) {
        global $CFG;
        $mods = $modnames = $modnamesplural = $modnamesused = array();
        // This function is included when we include course/lib.php at the top
        // of this file
        get_all_mods($course->id, $mods, $modnames, $modnamesplural, $modnamesused);
        $resources = array();
        $activities = array();
        foreach($modnames as $modname=>$modnamestr) {
            if (!course_allowed_module($course, $modname)) {
                continue;
            }

            $libfile = "$CFG->dirroot/mod/$modname/lib.php";
            if (!file_exists($libfile)) {
                continue;
            }
            include_once($libfile);
            $gettypesfunc =  $modname.'_get_types';
            if (function_exists($gettypesfunc)) {
                $types = $gettypesfunc();
                foreach($types as $type) {
                    if (!isset($type->modclass) || !isset($type->typestr)) {
                        debugging('Incorrect activity type in '.$modname);
                        continue;
                    }
                    if ($type->modclass == MOD_CLASS_RESOURCE) {
                        $resources[html_entity_decode($type->type)] = $type->typestr;
                    } else {
                        $activities[html_entity_decode($type->type)] = $type->typestr;
                    }
                }
            } else {
                $archetype = plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
                if ($archetype == MOD_ARCHETYPE_RESOURCE) {
                    $resources[$modname] = $modnamestr;
                } else {
                    // all other archetypes are considered activity
                    $activities[$modname] = $modnamestr;
                }
            }
        }
        $this->cache->{'course'.$course->id.'resources'} = $resources;
        $this->cache->{'course'.$course->id.'activities'} = $activities;
    }

    /**
     * This function loads the course settings that are available for the user
     *
     * @return bool|mixed Either false of a key to access the course tree by
     */
    protected function load_course_settings() {
        global $CFG, $OUTPUT, $USER, $SESSION;

        $course = $this->page->course;
        if (empty($course->context)) {
            if (!$this->cache->cached('coursecontext'.$course->id)) {
                $this->cache->{'coursecontext'.$course->id} = get_context_instance(CONTEXT_COURSE, $course->id);   // Course context
            }
            $course->context = $this->cache->{'coursecontext'.$course->id};
        }
        if (!$this->cache->cached('canviewcourse'.$course->id)) {
            $this->cache->{'canviewcourse'.$course->id} = has_capability('moodle/course:view', $course->context);
        }
        if ($course->id === SITEID || !$this->cache->{'canviewcourse'.$course->id}) {
            return false;
        }

        $coursenode = $this->page->navigation->find_child($course->id, global_navigation::TYPE_COURSE);
        
        $coursenodekey = $this->add(get_string('courseadministration'), null, $coursenode->type, null, 'courseadmin');
        $coursenode = $this->get($coursenodekey);
        
        if (has_capability('moodle/course:update', $course->context)) {
            // Add the turn on/off settings
            $url = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$course->id, 'sesskey'=>sesskey()));
            if ($this->page->user_is_editing()) {
                $url->param('edit', 'off');
                $editstring = get_string('turneditingoff');
            } else {
                $url->param('edit', 'on');
                $editstring = get_string('turneditingon');
            }
            $coursenode->add($editstring, $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/edit'));

            if ($this->page->user_is_editing()) {
                // Add `add` resources|activities branches
                $structurefile = $CFG->dirroot.'/course/format/'.$course->format.'/lib.php';
                if (file_exists($structurefile)) {
                    require_once($structurefile);
                    $formatstring = call_user_func('callback_'.$course->format.'_definition');
                    $formatidentifier = optional_param(call_user_func('callback_'.$course->format.'_request_key'), 0, PARAM_INT);
                } else {
                    $formatstring = get_string('topic');
                    $formatidentifier = optional_param('topic', 0, PARAM_INT);
                }
                if (!$this->cache->cached('coursesections'.$course->id)) {
                    $this->cache->{'coursesections'.$course->id} = get_all_sections($course->id);
                }
                $sections = $this->cache->{'coursesections'.$course->id};
                
                $addresource = $this->get($this->add(get_string('addresource')));
                $addactivity = $this->get($this->add(get_string('addactivity')));
                if ($formatidentifier!==0) {
                    $addresource->forceopen = true;
                    $addactivity->forceopen = true;
                }

                if (!$this->cache->cached('course'.$course->id.'resources')) {
                    $this->get_course_modules($course);
                }
                $resources = $this->cache->{'course'.$course->id.'resources'};
                $activities = $this->cache->{'course'.$course->id.'activities'};
                
                foreach ($sections as $section) {
                    if ($formatidentifier !== 0 && $section->section != $formatidentifier) {
                        continue;
                    }
                    $sectionurl = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$course->id, $formatstring=>$section->section));
                    if ($section->section == 0) {
                        $sectionresources = $addresource->add(get_string('course'), $sectionurl, self::TYPE_SETTING);
                        $sectionactivities = $addactivity->add(get_string('course'), $sectionurl, self::TYPE_SETTING);
                    } else {
                        $sectionresources = $addresource->add($formatstring.' '.$section->section, $sectionurl, self::TYPE_SETTING);
                        $sectionactivities = $addactivity->add($formatstring.' '.$section->section, $sectionurl, self::TYPE_SETTING);
                    }
                    foreach ($resources as $value=>$resource) {
                        $url = new moodle_url($CFG->wwwroot.'/course/mod.php', array('id'=>$course->id, 'sesskey'=>sesskey(), 'section'=>$section->section));
                        $pos = strpos($value, '&type=');
                        if ($pos!==false) {
                            $url->param('add', substr($value, 0,$pos));
                            $url->param('type', substr($value, $pos+6));
                        } else {
                            $url->param('add', $value);
                        }
                        $addresource->get($sectionresources)->add($resource, $url, self::TYPE_SETTING);
                    }
                    $subbranch = false;
                    foreach ($activities as $activityname=>$activity) {
                        if ($activity==='--') {
                            $subbranch = false;
                            continue;
                        }
                        if (strpos($activity, '--')===0) {
                            $subbranch = $addactivity->get($sectionactivities)->add(trim($activity, '-'));
                            continue;
                        }
                        $url = new moodle_url($CFG->wwwroot.'/course/mod.php', array('id'=>$course->id, 'sesskey'=>sesskey(), 'section'=>$section->section));
                        $pos = strpos($activityname, '&type=');
                        if ($pos!==false) {
                            $url->param('add', substr($activityname, 0,$pos));
                            $url->param('type', substr($activityname, $pos+6));
                        } else {
                            $url->param('add', $activityname);
                        }
                        if ($subbranch !== false) {
                            $addactivity->get($sectionactivities)->get($subbranch)->add($activity, $url, self::TYPE_SETTING);
                        } else {
                            $addactivity->get($sectionactivities)->add($activity, $url, self::TYPE_SETTING);
                        }
                    }
                }
            }

            // Add the course settings link
            $url = new moodle_url($CFG->wwwroot.'/course/edit.php', array('id'=>$course->id));
            $coursenode->add(get_string('settings'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/settings'));
        }
        
        // Add assign or override roles if allowed
        if (has_capability('moodle/role:assign', $course->context)) {
            $url = new moodle_url($CFG->wwwroot.'/'.$CFG->admin.'/roles/assign.php', array('contextid'=>$course->context->id));
            $coursenode->add(get_string('assignroles', 'role'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/roles'));
        } else if (get_overridable_roles($course->context, ROLENAME_ORIGINAL)) {
            $url = new moodle_url($CFG->wwwroot.'/'.$CFG->admin.'/roles/override.php', array('contextid'=>$course->context->id));
            $coursenode->add(get_string('overridepermissions', 'role'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/roles'));
        }

        // Add view grade report is permitted
        $reportavailable = false;
        if (has_capability('moodle/grade:viewall', $course->context)) {
            $reportavailable = true;
        } else if (!empty($course->showgrades)) {
            $reports = get_plugin_list('gradereport');
            if (is_array($reports) && count($reports)>0) {     // Get all installed reports
                arsort($reports); // user is last, we want to test it first
                foreach ($reports as $plugin => $plugindir) {
                    if (has_capability('gradereport/'.$plugin.':view', $course->context)) {
                        //stop when the first visible plugin is found
                        $reportavailable = true;
                        break;
                    }
                }
            }
        }
        if ($reportavailable) {
            $url = new moodle_url($CFG->wwwroot.'/grade/report/index.php', array('id'=>$course->id));
            $coursenode->add(get_string('grades'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/grades'));
        }

        //  Add outcome if permitted
        if (!empty($CFG->enableoutcomes) && has_capability('moodle/course:update', $course->context)) {
            $url = new moodle_url($CFG->wwwroot.'/grade/edit/outcome/course.php', array('id'=>$course->id));
            $coursenode->add(get_string('outcomes', 'grades'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/outcomes'));
        }

        // Add meta course links
        if ($course->metacourse) {
            if (has_capability('moodle/course:managemetacourse', $course->context)) {
                $url = new moodle_url($CFG->wwwroot.'/course/importstudents.php', array('id'=>$course->id));
                $coursenode->add(get_string('childcourses'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/course'));
            } else if (has_capability('moodle/role:assign', $course->context)) {
                $key = $coursenode->add(get_string('childcourses'), null,  self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/course'));
                $coursenode->get($key)->hidden = true;;
            }
        }

        // Manage groups in this course
        if (($course->groupmode || !$course->groupmodeforce) && has_capability('moodle/course:managegroups', $course->context)) {
            $url = new moodle_url($CFG->wwwroot.'/group/index.php', array('id'=>$course->id));
            $coursenode->add(get_string('groups'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/group'));
        }

        // Backup this course
        if (has_capability('moodle/site:backup', $course->context)) {
            $url = new moodle_url($CFG->wwwroot.'/backup/backup.php', array('id'=>$course->id));
            $coursenode->add(get_string('backup'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/backup'));
        }

        // Restore to this course
        if (has_capability('moodle/site:restore', $course->context)) {
            $url = new moodle_url($CFG->wwwroot.'/files/index.php', array('id'=>$course->id, 'wdir'=>'/backupdata'));
            $coursenode->add(get_string('restore'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/restore'));
        }

        // Import data from other courses
        if (has_capability('moodle/site:import', $course->context)) {
            $url = new moodle_url($CFG->wwwroot.'/course/import.php', array('id'=>$course->id));
            $coursenode->add(get_string('import'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/restore'));
        }

        // Reset this course
        if (has_capability('moodle/course:reset', $course->context)) {
            $url = new moodle_url($CFG->wwwroot.'/course/reset.php', array('id'=>$course->id));
            $coursenode->add(get_string('reset'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/return'));
        }

        // Manage questions
        $questioncaps = array('moodle/question:add',
                              'moodle/question:editmine',
                              'moodle/question:editall',
                              'moodle/question:viewmine',
                              'moodle/question:viewall',
                              'moodle/question:movemine',
                              'moodle/question:moveall');
        if (has_any_capability($questioncaps, $this->context)) {
            $questionlink = $CFG->wwwroot.'/question/edit.php';
        } else if (has_capability('moodle/question:managecategory', $this->context)) {
            $questionlink = $CFG->wwwroot.'/question/category.php';
        }
        if (isset($questionlink)) {
            $url = new moodle_url($questionlink, array('courseid'=>$course->id));
            $coursenode->add(get_string('questions','quiz'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/questions'));
        }

        // Repository Instances
        require_once($CFG->dirroot.'/repository/lib.php');
        $editabletypes = repository::get_editable_types($this->context);
        if (has_capability('moodle/course:update', $this->context) && !empty($editabletypes)) {
            $url = new moodle_url($CFG->wwwroot.'/repository/manage_instances.php', array('contextid'=>$this->context->id));
            $coursenode->add(get_string('repositories'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/repository'));
        }

        // Manage files
        if (has_capability('moodle/course:managefiles', $this->context)) {
            $url = new moodle_url($CFG->wwwroot.'/files/index.php', array('id'=>$course->id));
            $coursenode->add(get_string('files'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/files'));
        }

        // Authorize hooks
        if ($course->enrol == 'authorize' || (empty($course->enrol) && $CFG->enrol == 'authorize')) {
            require_once($CFG->dirroot.'/enrol/authorize/const.php');
            $url = new moodle_url($CFG->wwwroot.'/enrol/authorize/index.php', array('course'=>$course->id));
            $coursenode->add(get_string('payments'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/payment'));
            if (has_capability('enrol/authorize:managepayments', $this->page->context)) {
                $cnt = $DB->count_records('enrol_authorize', array('status'=>AN_STATUS_AUTH, 'courseid'=>$course->id));
                if ($cnt) {
                    $url = new moodle_url($CFG->wwwroot.'/enrol/authorize/index.php', array('course'=>$course->id,'status'=>AN_STATUS_AUTH));
                    $coursenode->add(get_string('paymentpending', 'moodle', $cnt), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/payment'));
                }
            }
        }

        // Unenrol link
        if (empty($course->metacourse)) {
            if (has_capability('moodle/legacy:guest', $this->context, NULL, false)) {   // Are a guest now
                $url = new moodle_url($CFG->wwwroot.'/course/enrol.php', array('id'=>$course->id));
                $coursenode->add(get_string('enrolme', '', format_string($course->shortname)), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/user'));
            } else if (has_capability('moodle/role:unassignself', $this->context, NULL, false) && get_user_roles($this->context, 0, false)) {  // Have some role
                $url = new moodle_url($CFG->wwwroot.'/course/unenrol.php', array('id'=>$course->id));
                $coursenode->add(get_string('unenrolme', '', format_string($course->shortname)), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/user'));
            }
        }

        // Link to the user own profile (except guests)
        if (!isguestuser() and isloggedin()) {
            $url = new moodle_url($CFG->wwwroot.'/user/view.php', array('id'=>$USER->id, 'course'=>$course->id));
            $coursenode->add(get_string('profile'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/user'));
        }

        // Switch roles
        $roles = array();
        $assumedrole = $this->in_alternative_role();
        if ($assumedrole!==false) {
            $roles[0] = get_string('switchrolereturn');
        }
        if (has_capability('moodle/role:switchroles', $this->context)) {
            $availableroles = get_switchable_roles($this->context);
            if (is_array($availableroles)) {
                foreach ($availableroles as $key=>$role) {
                    if ($key == $CFG->guestroleid || $assumedrole===(int)$key) {
                        continue;
                    }
                    $roles[$key] = $role;
                }
            }
        }
        if (is_array($roles) && count($roles)>0) {
            $switchroleskey = $this->add(get_string('switchroleto'));
            if ((count($roles)==1 && array_key_exists(0, $roles))|| $assumedrole!==false) {
                $this->get($switchroleskey)->forceopen = true;
            }
            $returnurl = $this->page->url;
            $returnurl->param('sesskey', sesskey());
            $SESSION->returnurl = serialize($returnurl);
            foreach ($roles as $key=>$name) {
                $url = new moodle_url($CFG->wwwroot.'/course/switchrole.php', array('id'=>$course->id,'sesskey'=>sesskey(), 'switchrole'=>$key, 'returnurl'=>'1'));
                $this->get($switchroleskey)->add($name, $url, self::TYPE_SETTING, null, $key, $OUTPUT->old_icon_url('i/roles'));
            }
        }
        // Return we are done
        return $coursenodekey;
    }

    /**
     * This function calls the module function to inject module settings into the
     * settings navigation tree.
     *
     * This only gets called if there is a corrosponding function in the modules
     * lib file.
     *
     * For examples mod/forum/lib.php ::: forum_extend_settings_navigation()
     *
     * @return void|mixed The key to access the module method by
     */
    protected function load_module_settings() {
        global $CFG, $DB;
        $cm = $this->page->cm;
        $module = $DB->get_record('modules', array('id'=>$cm->module));
        if (!$module) {
            return;
        }

        $file = $CFG->dirroot.'/mod/'.$module->name.'/lib.php';
        $function = $module->name.'_extend_settings_navigation';
        
        if (file_exists($file)) {
            require_once($file);
        }
        if (!function_exists($function)) {
            return;
        }
        return $function($this,$module);
    }

    /**
     * Loads the user settings block of the settings nav
     * 
     * This function is simply works out the userid and whether we need to load
     * just the current users profile settings, or the current user and the user the 
     * current user is viewing.
     * 
     * This function has some very ugly code to work out the user, if anyone has
     * any bright ideas please feel free to intervene.
     *
     * @param int $courseid The course id of the current course
     */
    protected function load_user_settings($courseid=SITEID) {
        global $USER, $FULLME;

        if (isguestuser() || !isloggedin()) {
            return false;
        }

        // This is terribly ugly code, but I couldn't see a better way around it
        // we need to pick up the user id, it can be the current user or someone else
        // and the key depends on the current location
        // Default to look at id
        $userkey='id';
        if ($this->context->contextlevel >= CONTEXT_COURSECAT && strpos($FULLME, '/message/')===false && strpos($FULLME, '/mod/forum/user')===false) {
            // If we have a course context and we are not in message or forum
            // Message and forum both pick the user up from `id`
            $userkey = 'user';
        } else if (strpos($FULLME,'/blog/') || strpos($FULLME, '/roles/')) {
            // And blog and roles just do thier own thing using `userid`
            $userkey = 'userid';
        }

        $userid = optional_param($userkey, $USER->id, PARAM_INT);
        if ($userid!=$USER->id) {
            $this->generate_user_settings($courseid,$userid,'userviewingsettings');
            $this->generate_user_settings($courseid,$USER->id);
        } else {
            $this->generate_user_settings($courseid,$USER->id);
        }
    }

    /**
     * This function gets called by {@link load_user_settings()} and actually works out
     * what can be shown/done
     *
     * @param int $courseid The current course' id
     * @param int $userid The user id to load for
     * @param string $gstitle The string to pass to get_string for the branch title
     * @return string|int The key to reference this user's settings
     */
    protected function generate_user_settings($courseid, $userid, $gstitle='usercurrentsettings') {
        global $DB, $CFG, $USER;

        $course = $DB->get_record("course", array("id"=>$courseid));
        if (!$course) {
            return false;
        }

        $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);   // Course context
        $systemcontext   = get_context_instance(CONTEXT_SYSTEM);
        $currentuser = ($USER->id == $userid);
        if ($currentuser) {
            $user = $USER;
            $usercontext = get_context_instance(CONTEXT_USER, $user->id);       // User context
        } else {
            $user = $DB->get_record('user', array('id'=>$userid));
            if (!$user) {
                return false;
            }
            // Check that the user can view the profile
            $usercontext = get_context_instance(CONTEXT_USER, $user->id);       // User context
            if ($course->id==SITEID) {
                if ($CFG->forceloginforprofiles && !isteacherinanycourse() && !isteacherinanycourse($user->id) && !has_capability('moodle/user:viewdetails', $usercontext)) {  // Reduce possibility of "browsing" userbase at site level
                    // Teachers can browse and be browsed at site level. If not forceloginforprofiles, allow access (bug #4366)
                    return false;
                }
            } else {
                if ((!has_capability('moodle/user:viewdetails', $coursecontext) && !has_capability('moodle/user:viewdetails', $usercontext)) || !has_capability('moodle/course:view', $coursecontext, $user->id, false)) {
                    return false;
                }
                if (groups_get_course_groupmode($course) == SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $coursecontext)) {
                    // If groups are in use, make sure we can see that group
                    return false;
                }
            }
        }

        $fullname = fullname($user, has_capability('moodle/site:viewfullnames', $this->page->context));

        // Add a user setting branch
        $usersettingskey = $this->add(get_string($gstitle, 'moodle', $fullname));
        $usersetting = $this->get($usersettingskey);
        $usersetting->id = 'usersettings';

        // URL to the users profile
        $profileurl = new moodle_url($CFG->wwwroot.'/user/view.php', array('id'=>$user->id, 'course'=>$course->id));

        // Check if the user has been deleted
        if ($user->deleted) {
            if (!has_capability('moodle/user:update', $coursecontext)) {
                // We can't edit the user so just show the user deleted message
                $usersetting->add(get_string('userdeleted'), null, self::TYPE_SETTING);
            } else {
                // We can edit the user so show the user deleted message and link it to the profile
                $usersetting->add(get_string('userdeleted'), $profileurl, self::TYPE_SETTING);
            }
            return true;
        }

        // Add a link to view the user profile
        if ($currentuser) {
            $usersetting->add(get_string('viewprofile'), $profileurl, self::TYPE_SETTING);
        } else {
            $usersetting->add(get_string('viewprofile','',$fullname), $profileurl, self::TYPE_SETTING);
        }

        // Add the profile edit link
        if (isloggedin() && !isguestuser($user) && !is_mnet_remote_user($user)) {
            $url = false;
            if (($currentuser && has_capability('moodle/user:update', $systemcontext)) || (has_capability('moodle/user:update', $systemcontext) && !is_primary_admin($user->id))) {
                $url = new moodle_url($CFG->wwwroot.'/user/editadvanced.php', array('id'=>$user->id, 'course'=>$course->id));
            } else if ((has_capability('moodle/user:editprofile', $usercontext) && !is_primary_admin($user->id)) || ($currentuser && has_capability('moodle/user:editownprofile', $systemcontext))) {
                $url = new moodle_url($CFG->wwwroot.'/user/edit.php', array('id'=>$user->id, 'course'=>$course->id));
            }
            if ($url!==false) {
                $usersetting->add(get_string('editmyprofile'), $url, self::TYPE_SETTING);
            }
        }

        // Change password link
        if (!empty($user->auth)) {
            $userauth = get_auth_plugin($user->auth);
            if ($currentuser && !session_is_loggedinas() && $userauth->can_change_password() && !isguestuser() && has_capability('moodle/user:changeownpassword', $systemcontext)) {
                $passwordchangeurl = $userauth->change_password_url();
                if (!$passwordchangeurl) {
                    if (empty($CFG->loginhttps)) {
                        $wwwroot = $CFG->wwwroot;
                    } else {
                        $wwwroot = str_replace('http:', 'https:', $CFG->wwwroot);
                    }
                    $passwordchangeurl = new moodle_url($CFG->wwwroot.'/login/change_password.php');
                } else {
                    $urlbits = explode($passwordchangeurl. '?', 1);
                    $passwordchangeurl = new moodle_url($urlbits[0]);
                    if (count($urlbits)==2 && preg_match_all('#\&([^\=]*?)\=([^\&]*)#si', '&'.$urlbits[1], $matches)) {
                        foreach ($matches as $pair) {
                            $fullmeurl->param($pair[1],$pair[2]);
                        }
                    }
                }
                $passwordchangeurl->param('id', $course->id);
                $usersetting->add(get_string("changepassword"), $passwordchangeurl, self::TYPE_SETTING);
            }
        }

        // View the roles settings
        if (has_any_capability(array('moodle/role:assign', 'moodle/role:safeoverride','moodle/role:override', 'moodle/role:manage'), $usercontext)) {
            $roleskey = $usersetting->add(get_string('roles'), null, self::TYPE_SETTING);

            $url = new moodle_url($CFG->wwwroot.'/'.$CFG->admin.'/roles/usersroles.php', array('userid'=>$user->id, 'courseid'=>$course->id));
            $usersetting->get($roleskey)->add(get_string('thisusersroles', 'role'), $url, self::TYPE_SETTING);

            $assignableroles = get_assignable_roles($usercontext, ROLENAME_BOTH);
            $overridableroles = get_overridable_roles($usercontext, ROLENAME_BOTH);

            if (!empty($assignableroles)) {
                $url = new moodle_url($CFG->wwwroot.'/'.$CFG->admin.'/roles/assign.php', array('contextid'=>$usercontext->id,'userid'=>$user->id, 'courseid'=>$course->id));
                $usersetting->get($roleskey)->add(get_string('assignrolesrelativetothisuser', 'role'), $url, self::TYPE_SETTING);
            }

            if (!empty($overridableroles)) {
                $url = new moodle_url($CFG->wwwroot.'/'.$CFG->admin.'/roles/override.php', array('contextid'=>$usercontext->id,'userid'=>$user->id, 'courseid'=>$course->id));
                $usersetting->get($roleskey)->add(get_string('overridepermissions', 'role'), $url, self::TYPE_SETTING);
            }

            $url = new moodle_url($CFG->wwwroot.'/'.$CFG->admin.'/roles/check.php', array('contextid'=>$usercontext->id,'userid'=>$user->id, 'courseid'=>$course->id));
            $usersetting->get($roleskey)->add(get_string('checkpermissions', 'role'), $url, self::TYPE_SETTING);
        }

        // Portfolio
        if (empty($userindexpage) && $currentuser && !empty($CFG->enableportfolios) && has_capability('moodle/portfolio:export', $systemcontext) && portfolio_instances(true, false)) {
            $portfoliokey = $usersetting->add(get_string('portfolios', 'portfolio'), null, self::TYPE_SETTING);
            $url = new moodle_url($CFG->wwwroot .'/user/portfolio.php');
            $usersetting->get($portfoliokey)->add(get_string('configure', 'portfolio'), $url, self::TYPE_SETTING);
            $url = new moodle_url($CFG->wwwroot .'/user/portfoliologs.php');
            $usersetting->get($portfoliokey)->add(get_string('logs', 'portfolio'), $url, self::TYPE_SETTING);
        }

        // Repository
        if (!$currentuser) {
            require_once($CFG->dirroot . '/repository/lib.php');
            $editabletypes = repository::get_editable_types($usercontext);
            if ($usercontext->contextlevel == CONTEXT_USER && !empty($editabletypes)) {
                $url = new moodle_url($CFG->wwwroot .'/repository/manage_instances.php', array('contextid'=>$usercontext->id));
                $usersetting->add(get_string('repositories', 'repository'), $url, self::TYPE_SETTING);
            }
        }

        // Messaging
        if (empty($userindexpage) && has_capability('moodle/user:editownmessageprofile', $systemcontext)) {
            $url = new moodle_url($CFG->wwwroot.'/message/edit.php', array('id'=>$user->id, 'course'=>$course->id));
            $usersetting->add(get_string('editmymessage', 'message'), $url, self::TYPE_SETTING);
        }

        return $usersettingskey;
    }

    /**
     * Determine whether the user is assuming another role
     *
     * This function checks to see if the user is assuming another role by means of
     * role switching. In doing this we compare each RSW key (context path) against
     * the current context path. This ensures that we can provide the switching
     * options against both the course and any page shown under the course.
     *
     * @return bool|int The role(int) if the user is in another role, false otherwise
     */
    protected function in_alternative_role() {
        global $USER;
        if (!empty($USER->access['rsw']) && is_array($USER->access['rsw'])) {
            if (!empty($this->page->context) && !empty($USER->access['rsw'][$this->page->context->path])) {
                return $USER->access['rsw'][$this->page->context->path];
            }
            foreach ($USER->access['rsw'] as $key=>$role) {
                if (strpos($this->context->path,$key)===0) {
                    return $role;
                }
            }
        }
        return false;
    }

    /**
     * This function loads all of the front page settings into the settings navigation.
     * This function is called when the user is on the front page, or $COURSE==$SITE
     */
    protected function load_front_page_settings() {
        global $CFG, $USER, $OUTPUT, $SITE;

        $course = $SITE;
        if (empty($course->context)) {
            $course->context = get_context_instance(CONTEXT_COURSE, $course->id);   // Course context
        }
        if (has_capability('moodle/course:update', $course->context)) {

            $frontpage = $this->add(get_string('frontpagesettings'), null, self::TYPE_SETTING, null, 'frontpage');
            $this->get($frontpage)->id = 'frontpagesettings';
            $this->get($frontpage)->forceopen = true;

            // Add the turn on/off settings
            $url = new moodle_url($CFG->wwwroot.'/course/view.php', array('id'=>$course->id, 'sesskey'=>sesskey()));
            if ($this->page->user_is_editing()) {
                $url->param('edit', 'off');
                $editstring = get_string('turneditingoff');
            } else {
                $url->param('edit', 'on');
                $editstring = get_string('turneditingon');
            }
            $this->get($frontpage)->add($editstring, $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/edit'));

            // Add the course settings link
            $url = new moodle_url($CFG->wwwroot.'/admin/settings.php', array('section'=>'frontpagesettings'));
            $this->get($frontpage)->add(get_string('settings'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/settings'));
        }

        //Participants
        if (has_capability('moodle/site:viewparticipants', $course->context)) {
            $url = new moodle_url($CFG->wwwroot.'/user/index.php?contextid='.$course->context->id);
            $this->get($frontpage)->add(get_string('participants'), $url, self::TYPE_SETTING, null, null, $OUTPUT->old_icon_url('i/users'));
        }
    }


    /**
     * This function removes all root branches that have no children
     */
    public function remove_empty_root_branches() {
        foreach ($this->children as $key=>$node) {
            if ($node->nodetype != self::NODETYPE_BRANCH || count($node->children)===0) {
                $this->remove_child($key);
            }
        }
    }
}

/**
 * Simple class used to output a navigation branch in XML
 *
 * @package moodlecore
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation_xml {
    /** @var array */
    protected $nodetype = array('node','branch');
    /** @var array */
    protected $expandable = array();
    /**
     * Turns a branch and all of its children into XML
     *
     * @param navigation_node $branch
     * @return string XML string
     */
    public function convert($branch) {
        $xml = $this->convert_child($branch);
        return $xml;
    }
    /**
     * Set the expandable items in the array so that we have enough information
     * to attach AJAX events
     */
    public function set_expandable($expandable) {
        foreach ($expandable as $node) {
            $this->expandable[(string)$node['branchid']] = $node;
        }
    }
    /**
     * Recusively converts a child node and its children to XML for output
     *
     * @param navigation_node $child The child to convert
     * @param int $depth Pointlessly used to track the depth of the XML structure
     */
    protected function convert_child($child, $depth=1) {
        global $OUTPUT;

        if (!$child->display) {
            return '';
        }
        $attributes = array();
        $attributes['id'] = $child->id;
        $attributes['type'] = $child->type;
        $attributes['key'] = $child->key;
        $attributes['icon'] = $child->icon;
        $attributes['class'] = $child->get_css_type();
        if ($child->forcetitle || $child->title !== $child->text) {
            $attributes['title'] = htmlentities($child->title);
        }
        if (array_key_exists((string)$child->key, $this->expandable)) {
            $attributes['expandable'] = $child->key;
            $child->add_class($this->expandable[$child->key]['id']);
        }
        if (count($child->classes)>0) {
            $attributes['class'] .= ' '.join(' ',$child->classes);
        }
        if (is_string($child->action)) {
            $attributes['link'] = $child->action;
        } else if ($child->action instanceof moodle_url) {
            $attributes['link'] = $child->action->out();
        }
        $attributes['hidden'] = ($child->hidden);
        $attributes['haschildren'] = (count($child->children)>0 || $child->type == navigation_node::TYPE_CATEGORY);

        $xml = '<'.$this->nodetype[$child->nodetype];
        if (count($attributes)>0) {
            foreach ($attributes as $key=>$value) {
                if (is_bool($value)) {
                    if ($value) {
                        $xml .= ' '.$key.'="true"';
                    } else {
                        $xml .= ' '.$key.'="false"';
                    }
                } else if ($value !== null) {
                    $xml .= ' '.$key.'="'.$value.'"';
                }
            }
        }
        $xml .= '>';
        $xml .= '<name>'.htmlentities($child->text).'</name>';
        if (count($child->children)>0) {
            $xml .= '<children>';
            foreach ($child->children as $subchild) {
                $xml .= $this->convert_child($subchild, $depth+1);
            }
            $xml .= '</children>';
        }
        $xml .= '</'.$this->nodetype[$child->nodetype].'>';
        return $xml;
    }
}

/**
 * The cache class used by global navigation and settings navigation to cache bits
 * and bobs that are used during their generation.
 *
 * It is basically an easy access point to session with a bit of smarts to make
 * sure that the information that is cached is valid still.
 *
 * Example use:
 * <code php>
 * if (!$cache->viewdiscussion()) {
 *     // Code to do stuff and produce cachable content
 *     $cache->viewdiscussion = has_capability('mod/forum:viewdiscussion', $coursecontext);
 * }
 * $content = $cache->viewdiscussion;
 * </code>
 *
 * @package moodlecore
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation_cache {
    /** @var int */
    protected $creation;
    /** @var array */
    protected $session;
    /** @var string */
    protected $area;
    /** @var int */
    protected $timeout;
    /** @var stdClass */
    protected $currentcontext;
    /** @var int */
    const CACHETIME = 0;
    /** @var int */
    const CACHEUSERID = 1;
    /** @var int */
    const CACHEVALUE = 2;
    
    /**
     * Contructor for the cache. Requires two arguments
     *
     * @param string $area The string to use to segregate this particular cache
     *                it can either be unique to start a fresh cache or if you want
     *                to share a cache then make it the string used in the original
     *                cache
     * @param int $timeout The number of seconds to time the information out after
     */
    public function __construct($area, $timeout=60) {
        global $SESSION, $PAGE;
        $this->creation = time();
        $this->area = $area;

        if (!isset($SESSION->navcache)) {
            $SESSION->navcache = new stdClass;
        }

        if (!isset($SESSION->navcache->{$area})) {
            $SESSION->navcache->{$area} = array();
        }
        $this->session = &$SESSION->navcache->{$area};
        $this->timeout = time()-$timeout;
        if (rand(0,10)===0) {
            $this->garbage_collection();
        }
    }

    /**
     * Magic Method to retrieve something by simply calling using = cache->key
     *
     * @param mixed $key The identifier for the information you want out again
     * @return void|mixed Either void or what ever was put in
     */
    public function __get($key) {
        if (!$this->cached($key)) {
            return;
        }
        $information = $this->session[$key][self::CACHEVALUE];
        return unserialize($information);
    }

    /**
     * Magic method that simply uses {@link set();} to store something in the cache
     *
     * @param string|int $key
     * @param mixed $information
     */
    public function __set($key, $information) {
        $this->set($key, $information);
    }
    
    /**
     * Sets some information against the cache (session) for later retrieval
     *
     * @param string|int $key
     * @param mixed $information
     */
    public function set($key, $information) {
        global $USER;
        $information = serialize($information);
        $this->session[$key]= array(self::CACHETIME=>time(), self::CACHEUSERID=>$USER->id, self::CACHEVALUE=>$information);
    }
    /**
     * Check the existence of the identifier in the cache
     *
     * @param string|int $key
     * @return bool
     */
    public function cached($key) {
        global $USER;
        if (!array_key_exists($key, $this->session) || !is_array($this->session[$key]) || $this->session[$key][self::CACHEUSERID]!=$USER->id || $this->session[$key][self::CACHETIME] < $this->timeout) {
            return false;
        }
        return true;
    }
    /**
     * Compare something to it's equivilant in the cache
     *
     * @param string $key
     * @param mixed $value
     * @param bool $serialise Whether to serialise the value before comparison
     *              this should only be set to false if the value is already
     *              serialised
     * @return bool If the value is the same false if it is not set or doesn't match
     */
    public function compare($key, $value, $serialise=true) {
        if ($this->cached($key)) {
            if ($serialise) {
                $value = serialize($value);
            }
            if ($this->session[$key][self::CACHEVALUE] === $value) {
                return true;
            }
        }
        return false;
    }
    /**
     * Whipes the entire cache, good to force regeneration
     */
    public function clear() {
        $this->session = array();
    }
    /**
     * Checks all cache entries and removes any that have expired, good ole cleanup
     */
    protected function garbage_collection() {
        foreach ($this->session as $key=>$cachedinfo) {
            if (is_array($cachedinfo) && $cachedinfo[self::CACHETIME]<$this->timeout) {
                unset($this->session[$key]);
            }
        }
    }
}