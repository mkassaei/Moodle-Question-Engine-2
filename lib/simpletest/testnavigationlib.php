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
 * Unit tests for lib/navigationlib.php
 *
 * @package   moodlecore
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later (5)
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
require_once($CFG->libdir . '/navigationlib.php');

class navigation_node_test extends UnitTestCase {
    protected $tree;
    public static $includecoverage = array('./lib/navigationlib.php');
    public static $excludecoverage = array();

    public function setUp() {
        global $CFG, $FULLME;
        parent::setUp();
        $oldfullme = $FULLME;
        $FULLME = 'http://www.moodle.org/test.php';
        $this->node = new navigation_node('Test Node');
        $this->node->type = navigation_node::TYPE_SYSTEM;
        $this->node->add('demo1', null, 'demo1', navigation_node::TYPE_COURSE, 'http://www.moodle.org/',$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->node->add('demo2', null, 'demo2', navigation_node::TYPE_COURSE, 'http://www.moodle.com/',$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->node->add('demo3', null, 'demo3', navigation_node::TYPE_CATEGORY, 'http://www.moodle.org/',$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->node->get('demo3')->add('demo4', null, 'demo4', navigation_node::TYPE_COURSE, new moodle_url('http://www.moodle.org/'),$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->node->get('demo3')->add('demo5', null, 'demo5', navigation_node::TYPE_COURSE, 'http://www.moodle.org/test.php',$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->node->get('demo3')->get('demo5')->make_active();
        $this->node->get('demo3')->get('demo5')->add('activity1', null, 'activity1',navigation_node::TYPE_ACTIVITY);
        $this->node->get('demo3')->get('demo5')->get('activity1')->make_active();
        $this->node->add('hiddendemo1', null, 'hiddendemo1', navigation_node::TYPE_CATEGORY, 'http://www.moodle.org/',$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->node->get('hiddendemo1')->hidden = true;
        $this->node->get('hiddendemo1')->add('hiddendemo2', null, 'hiddendemo2', navigation_node::TYPE_COURSE, new moodle_url('http://www.moodle.org/'),$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->node->get('hiddendemo1')->add('hiddendemo3', null, 'hiddendemo3', navigation_node::TYPE_COURSE, new moodle_url('http://www.moodle.org/'),$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->node->get('hiddendemo1')->get('hiddendemo2')->helpbutton = 'Here is a help button';
        $this->node->get('hiddendemo1')->get('hiddendemo3')->display = false;
        $FULLME = $oldfullme;
    }
    public function test___construct() {
        global $CFG;
        $properties = array();
        $properties['text'] = 'text';
        $properties['shorttext'] = 'shorttext';
        $properties['key'] = 'key';
        $properties['type'] = 'navigation_node::TYPE_COURSE';
        $properties['action'] = 'http://www.moodle.org/';
        $properties['icon'] = $CFG->httpswwwroot . '/pix/i/course.gif';
        $node = new navigation_node($properties);
        $this->assertEqual($node->text, $properties['text']);
        $this->assertEqual($node->title, $properties['text']);
        $this->assertEqual($node->shorttext, $properties['shorttext']);
        $this->assertEqual($node->key, $properties['key']);
        $this->assertEqual($node->type, $properties['type']);
        $this->assertEqual($node->action, $properties['action']);
        $this->assertEqual($node->icon, $properties['icon']);
    }
    public function test_add() {
        global $CFG;
        // Add a node with all args set
        $key1 = $this->node->add('test_add_1','testadd1','key',navigation_node::TYPE_COURSE,'http://www.moodle.org/',$CFG->httpswwwroot . '/pix/i/course.gif');
        // Add a node with the minimum args required
        $key2 = $this->node->add('test_add_2','testadd2');
        $key3 = $this->node->add(str_repeat('moodle ', 15),str_repeat('moodle', 15));
        $this->assertEqual('key',$key1);
        $this->assertEqual($key2, $this->node->get($key2)->key);
        $this->assertEqual($key3, $this->node->get($key3)->key);
        $this->assertIsA($this->node->get('key'), 'navigation_node');
        $this->assertIsA($this->node->get($key2), 'navigation_node');
        $this->assertIsA($this->node->get($key3), 'navigation_node');
    }

    public function test_add_class() {
        $node = $this->node->get('demo1');
        $this->assertIsA($node, 'navigation_node');
        if ($node !== false) {
            $node->add_class('myclass');
            $classes = $node->classes;
            $this->assertTrue(in_array('myclass', $classes));
        }
    }

    public function test_add_to_path() {
        global $CFG;
        $path = array('demo3','demo5');
        $key1 = $this->node->add_to_path($path, 'testatp1', 'Test add to path 1', 'testatp1',  navigation_node::TYPE_COURSE, 'http://www.moodle.org/',$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->assertEqual($key1, 'testatp1');

        // This should generate an exception as we have not provided any text for
        // the node
        $this->expectException();
        $key3 = $this->node->add_to_path(array('demo3','dud1','dud2'), 'text', 'shorttext');
        $this->assertFalse($key3);

        // This should generate an exception as we have not provided any text for
        // the node
        $this->expectException(new coding_exception('You must set the text for the node when you create it.'));
        $key2 = $this->node->add_to_path($path);
    }

    public function test_check_if_active() {
        global $FULLME;
        $oldfullme = $FULLME;

        // First test the string urls
        $FULLME = 'http://www.moodle.org/';
        // demo1 -> action is http://www.moodle.org/, thus should be true
        $this->assertTrue($this->node->get('demo1')->check_if_active());
        // demo2 -> action is http://www.moodle.com/, thus should be false
        $this->assertFalse($this->node->get('demo2')->check_if_active());

        $FULLME = $oldfullme;
    }

    public function test_contains_active_node() {
        // demo5, and activity1 were set to active during setup
        // Should be true as it contains all nodes
        $this->assertTrue($this->node->contains_active_node());
        // Should be true as demo5 is a child of demo3
        $this->assertTrue($this->node->get('demo3')->contains_active_node());
        // Obviously duff
        $this->assertFalse($this->node->get('demo1')->contains_active_node());
        // Should be true as demo5 contains activity1
        $this->assertTrue($this->node->get('demo3')->get('demo5')->contains_active_node());
        // Should be false activity1 doesnt contain the active node... it is the active node
        $this->assertFalse($this->node->get('demo3')->get('demo5')->get('activity1')->contains_active_node());
        // Obviously duff
        $this->assertFalse($this->node->get('demo3')->get('demo4')->contains_active_node());
    }

    public function test_content() {
        $content1 = $this->node->get('demo1')->content();
        $content2 = $this->node->get('demo3')->content();
        $content3 = $this->node->get('demo3')->get('demo5')->content();
        $content4 = $this->node->get('hiddendemo1')->get('hiddendemo2')->content();
        $content5 = $this->node->get('hiddendemo1')->get('hiddendemo3')->content();
        $this->assert(new ContainsTagWithAttribute('a','href',$this->node->get('demo1')->action), $content1);
        $this->assert(new ContainsTagWithAttribute('a','href',$this->node->get('demo3')->action), $content2);
        $this->assert(new ContainsTagWithAttribute('a','href',$this->node->get('demo3')->get('demo5')->action), $content3);
        $this->assert(new ContainsTagWithAttribute('a','href',$this->node->get('hiddendemo1')->get('hiddendemo2')->action->out()), $content4);
        $this->assertTrue(empty($content5));
    }

    public function test_find_active_node() {
        $activenode1 = $this->node->find_active_node();
        $activenode2 = $this->node->find_active_node(navigation_node::TYPE_COURSE);
        $activenode3 = $this->node->find_active_node(navigation_node::TYPE_CATEGORY);
        $activenode4 = $this->node->get('demo1')->find_active_node(navigation_node::TYPE_COURSE);
        $this->assertIsA($activenode1, 'navigation_node');
        if ($activenode1 instanceof navigation_node) {
            $this->assertEqual($activenode1, $this->node->get('demo3')->get('demo5'));
        }
        $this->assertIsA($activenode2, 'navigation_node');
        if ($activenode1 instanceof navigation_node) {
            $this->assertEqual($activenode2, $this->node->get('demo3')->get('demo5'));
        }
        $this->assertIsA($activenode3, 'navigation_node');
        if ($activenode1 instanceof navigation_node) {
            $this->assertEqual($activenode3, $this->node->get('demo3'));
        }
        $this->assertNotA($activenode4, 'navigation_node');
    }

    public function test_find_child() {
        $node1 = $this->node->find_child('demo1', navigation_node::TYPE_COURSE);
        $node2 = $this->node->find_child('demo5', navigation_node::TYPE_COURSE);
        $node3 = $this->node->find_child('demo5', navigation_node::TYPE_CATEGORY);
        $node4 = $this->node->find_child('demo0', navigation_node::TYPE_COURSE);
        $this->assertIsA($node1, 'navigation_node');
        $this->assertIsA($node2, 'navigation_node');
        $this->assertNotA($node3, 'navigation_node');
        $this->assertNotA($node4, 'navigation_node');
    }

    public function test_find_child_depth() {
        $depth1 = $this->node->find_child_depth('demo1',navigation_node::TYPE_COURSE);
        $depth2 = $this->node->find_child_depth('demo5',navigation_node::TYPE_COURSE);
        $depth3 = $this->node->find_child_depth('demo5',navigation_node::TYPE_CATEGORY);
        $depth4 = $this->node->find_child_depth('demo0',navigation_node::TYPE_COURSE);
        $this->assertEqual(1, $depth1);
        $this->assertEqual(1, $depth2);
        $this->assertFalse($depth3);
        $this->assertFalse($depth4);
    }

    public function test_find_expandable() {
        $expandable = array();
        $this->node->find_expandable($expandable);
        $this->assertEqual(count($expandable), 5);
        if (count($expandable) === 5) {
            $name = $expandable[0]['branchid'];
            $name .= $expandable[1]['branchid'];
            $name .= $expandable[2]['branchid'];
            $name .= $expandable[3]['branchid'];
            $name .= $expandable[4]['branchid'];
            $this->assertEqual($name, 'demo1demo2demo4hiddendemo2hiddendemo3');
        }
    }

    public function test_get() {
        $node1 = $this->node->get('demo1'); // Exists
        $node2 = $this->node->get('demo4'); // Doesn't exist for this node
        $node3 = $this->node->get('demo0'); // Doesn't exist at all
        $node4 = $this->node->get(false);   // Sometimes occurs in nature code
        $this->assertIsA($node1, 'navigation_node');
        $this->assertFalse($node2);
        $this->assertFalse($node3);
        $this->assertFalse($node4);
    }

    public function test_get_by_path() {
        $node1 = $this->node->get_by_path(array('demo3', 'demo4')); // This path exists and should return a node
        $node2 = $this->node->get_by_path(array('demo1', 'demo2')); // Both elements exist but demo2 is not a child of demo1
        $node3 = $this->node->get_by_path(array('demo0', 'demo6')); // This path is totally bogus
        $this->assertIsA($node1, 'navigation_node');
        $this->assertFalse($node2);
        $this->assertFalse($node3);
    }

    public function test_get_css_type() {
        $csstype1 = $this->node->get('demo3')->get_css_type();
        $csstype2 = $this->node->get('demo3')->get('demo5')->get_css_type();
        $this->node->get('demo3')->get('demo5')->type = 1000;
        $csstype3 = $this->node->get('demo3')->get('demo5')->get_css_type();
        $this->assertEqual($csstype1, 'type_category');
        $this->assertEqual($csstype2, 'type_course');
        $this->assertEqual($csstype3, 'type_unknown');
    }

    public function test_make_active() {
        global $CFG;
        $key1 = $this->node->add('active node 1', null, 'anode1');
        $key2 = $this->node->add('active node 2', null, 'anode2', navigation_node::TYPE_COURSE, new moodle_url($CFG->wwwroot));
        $this->node->get($key1)->make_active();
        $this->node->get($key2)->make_active();
        $this->assertTrue($this->node->get($key1)->isactive);
        $this->assertTrue($this->node->get($key2)->isactive);
    }

    public function test_reiterate_active_nodes() {
        global $FULLME;
        $oldfullme = $FULLME;
        $FULLME = 'http://www.moodle.org/test.php';
        $cachenode = serialize($this->node);
        $cachenode = unserialize($cachenode);
        $this->assertFalse($cachenode->get('demo3')->get('demo5')->isactive);
        $this->assertTrue($cachenode->reiterate_active_nodes());
        $this->assertTrue($cachenode->get('demo3')->get('demo5')->isactive);
        $FULLME = $oldfullme;
    }
    public function test_remove_child() {
        $this->node->add('child to remove 1', null, 'remove1');
        $this->node->add('child to remove 2', null, 'remove2');
        $this->node->get('remove2')->add('child to remove 3', null, 'remove3');
        $this->assertIsA($this->node->get('remove1'), 'navigation_node');
        $this->assertTrue($this->node->remove_child('remove1'));
        $this->assertFalse($this->node->remove_child('remove3'));
        $this->assertFalse($this->node->remove_child('remove0'));
        $this->assertTrue($this->node->remove_child('remove2'));
    }
    public function test_remove_class() {
        $this->node->add_class('testclass');
        $this->assertTrue($this->node->remove_class('testclass'));
        $this->assertFalse(in_array('testclass', $this->node->classes));
    }
    public function test_respect_forced_open() {
        $this->node->respect_forced_open();
        $this->assertTrue($this->node->forceopen);
    }
    public function test_toggle_type_display() {
        $this->node->toggle_type_display(navigation_node::TYPE_CATEGORY);
        $this->assertFalse($this->node->get('demo1')->display);
        $this->assertFalse($this->node->get('demo3')->get('demo5')->display);
        $this->assertTrue($this->node->get('demo3')->display);
        $this->node->toggle_type_display(navigation_node::TYPE_CATEGORY, true);
    }
}

/**
 * This is a dummy object that allows us to call protected methods within the
 * global navigation class by prefixing the methods with `exposed_`
 */
class exposed_global_navigation extends global_navigation {
    protected $exposedkey = 'exposed_';
    function __construct() {
        parent::__construct();
        $this->cache = new navigation_cache('simpletest_nav');
    }
    function __call($method, $arguments) {
        if (strpos($method,$this->exposedkey) !== false) {
            $method = substr($method, strlen($this->exposedkey));
        }
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $arguments);
        }
        throw new coding_exception('You have attempted to access a method that does not exist for the given object '.$method, DEBUG_DEVELOPER);
    }
}

class global_navigation_test extends UnitTestCase {
    /**
     * @var global_navigation
     */
    public $node;
    protected $cache;
    public static $includecoverage = array('./lib/navigationlib.php');
    public static $excludecoverage = array();
    
    public function setUp() {
        $this->cache = new navigation_cache('simpletest_nav');
        $this->node = new exposed_global_navigation();
        // Create an initial tree structure to work with
        $this->node->add('category 1', null, 'cat1', navigation_node::TYPE_CATEGORY);
        $this->node->add('category 2', null, 'cat2', navigation_node::TYPE_CATEGORY);
        $this->node->add('category 3', null, 'cat3', navigation_node::TYPE_CATEGORY);
        $this->node->get('cat2')->add('sub category 1', null, 'sub1', navigation_node::TYPE_CATEGORY);
        $this->node->get('cat2')->add('sub category 2', null, 'sub2', navigation_node::TYPE_CATEGORY);
        $this->node->get('cat2')->add('sub category 3', null, 'sub3', navigation_node::TYPE_CATEGORY);
        $this->node->get('cat2')->get('sub2')->add('course 1', null, 'course1', navigation_node::TYPE_COURSE);
        $this->node->get('cat2')->get('sub2')->add('course 2', null, 'course2', navigation_node::TYPE_COURSE);
        $this->node->get('cat2')->get('sub2')->add('course 3', null, 'course3', navigation_node::TYPE_COURSE);
        $this->node->get('cat2')->get('sub2')->get('course2')->add('section 1', null, 'sec1', navigation_node::TYPE_COURSE);
        $this->node->get('cat2')->get('sub2')->get('course2')->add('section 2', null, 'sec2', navigation_node::TYPE_COURSE);
        $this->node->get('cat2')->get('sub2')->get('course2')->add('section 3', null, 'sec3', navigation_node::TYPE_COURSE);
        $this->node->get('cat2')->get('sub2')->get('course2')->get('sec2')->add('activity 1', null, 'act1', navigation_node::TYPE_ACTIVITY);
        $this->node->get('cat2')->get('sub2')->get('course2')->get('sec2')->add('activity 2', null, 'act2', navigation_node::TYPE_ACTIVITY);
        $this->node->get('cat2')->get('sub2')->get('course2')->get('sec2')->add('activity 3', null, 'act3', navigation_node::TYPE_ACTIVITY);
        $this->node->get('cat2')->get('sub2')->get('course2')->get('sec2')->add('resource 1', null, 'res1', navigation_node::TYPE_RESOURCE);
        $this->node->get('cat2')->get('sub2')->get('course2')->get('sec2')->add('resource 2', null, 'res2', navigation_node::TYPE_RESOURCE);
        $this->node->get('cat2')->get('sub2')->get('course2')->get('sec2')->add('resource 3', null, 'res3', navigation_node::TYPE_RESOURCE);

        $this->cache->clear();
        $this->cache->modinfo5 = unserialize('O:6:"object":6:{s:8:"courseid";s:1:"5";s:6:"userid";s:1:"2";s:8:"sections";a:1:{i:0;a:1:{i:0;s:3:"288";}}s:3:"cms";a:1:{i:288;O:6:"object":17:{s:2:"id";s:3:"288";s:8:"instance";s:2:"19";s:6:"course";s:1:"5";s:7:"modname";s:5:"forum";s:4:"name";s:10:"News forum";s:7:"visible";s:1:"1";s:10:"sectionnum";s:1:"0";s:9:"groupmode";s:1:"0";s:10:"groupingid";s:1:"0";s:16:"groupmembersonly";s:1:"0";s:6:"indent";s:1:"0";s:10:"completion";s:1:"0";s:5:"extra";s:0:"";s:4:"icon";s:0:"";s:11:"uservisible";b:1;s:9:"modplural";s:6:"Forums";s:9:"available";b:1;}}s:9:"instances";a:1:{s:5:"forum";a:1:{i:19;R:8;}}s:6:"groups";N;}');
        $this->cache->coursesections5 = unserialize('a:5:{i:0;O:8:"stdClass":6:{s:7:"section";s:1:"0";s:2:"id";s:2:"14";s:6:"course";s:1:"5";s:7:"summary";N;s:8:"sequence";s:3:"288";s:7:"visible";s:1:"1";}i:1;O:8:"stdClass":6:{s:7:"section";s:1:"1";s:2:"id";s:2:"97";s:6:"course";s:1:"5";s:7:"summary";s:0:"";s:8:"sequence";N;s:7:"visible";s:1:"1";}i:2;O:8:"stdClass":6:{s:7:"section";s:1:"2";s:2:"id";s:2:"98";s:6:"course";s:1:"5";s:7:"summary";s:0:"";s:8:"sequence";N;s:7:"visible";s:1:"1";}i:3;O:8:"stdClass":6:{s:7:"section";s:1:"3";s:2:"id";s:2:"99";s:6:"course";s:1:"5";s:7:"summary";s:0:"";s:8:"sequence";N;s:7:"visible";s:1:"1";}i:4;O:8:"stdClass":6:{s:7:"section";s:1:"4";s:2:"id";s:3:"100";s:6:"course";s:1:"5";s:7:"summary";s:0:"";s:8:"sequence";N;s:7:"visible";s:1:"1";}}');
        $this->cache->canviewhiddenactivities = true;
        $this->cache->canviewhiddensections = true;
        $this->cache->canviewhiddencourses = true;
        $this->node->get('cat2')->get('sub2')->add('Test Course 5',null,'5',navigation_node::TYPE_COURSE, new moodle_url('http://moodle.org'));
    }
    public function test_add_categories() {
        $categories = array();
        for ($i=0;$i<3;$i++) {
            $categories[$i] = new stdClass;
            $categories[$i]->id = 'sub4_'.$i;
            $categories[$i]->name = 'add_categories '.$i;
        }
        $this->node->exposed_add_categories(array('cat3'), $categories);
        $this->assertEqual(count($this->node->get('cat3')->children), 3);
        $this->assertIsA($this->node->get('cat3')->get('sub4_1'), 'navigation_node');
        $this->node->get('cat3')->children = array();
    }
    public function test_add_course_section_generic() {
        $keys = array('cat2', 'sub2', '5');
        $course = new stdClass;
        $course->id = '5';
        $this->node->add_course_section_generic($keys, $course, 'topic', 'topic');
        $this->assertEqual(count($this->node->get_by_path($keys)->children),4);
    }
    public function test_add_category_by_path() {
        $category = new stdClass;
        $category->id = 'sub3';
        $category->name = 'Sub category 3';
        $category->path = '/cat2/sub3';
        $this->node->exposed_add_category_by_path($category);
        $this->assertIsA($this->node->get('cat2')->get('sub3'), 'navigation_node');
    }
    public function test_add_courses() {
        $courses = array();
        for ($i=0;$i<5;$i++) {
            $course = new stdClass;
            $course->id = $i;
            $course->visible = true;
            $course->category = 'cat3';
            $course->fullname = "Test Course $i";
            $course->shortname = "tcourse$i";
            $courses[$i] = $course;
        }
        
        $this->node->add_courses($courses);
        $this->assertIsA($this->node->get('cat3')->get(0), 'navigation_node');
        $this->assertIsA($this->node->get('cat3')->get(1), 'navigation_node');
        $this->assertIsA($this->node->get('cat3')->get(2), 'navigation_node');
        $this->assertIsA($this->node->get('cat3')->get(3), 'navigation_node');
        $this->assertIsA($this->node->get('cat3')->get(4), 'navigation_node');
        $this->node->get('cat3')->children = array();
    }
    public function test_can_display_type() {
        $this->node->expansionlimit = navigation_node::TYPE_COURSE;
        $this->assertTrue($this->node->exposed_can_display_type(navigation_node::TYPE_CATEGORY));
        $this->assertTrue($this->node->exposed_can_display_type(navigation_node::TYPE_COURSE));
        $this->assertFalse($this->node->exposed_can_display_type(navigation_node::TYPE_SECTION));
        $this->node->expansionlimit = null;
    }
    public function test_content() {
        $html1 = $this->node->content();
        $this->node->expansionlimit = navigation_node::TYPE_CATEGORY;
        $html2 = $this->node->content();
        $this->node->expansionlimit = null;
        $this->assert(new ContainsTagWithAttribute('a','href',$this->node->action->out()), $html1);
        $this->assert(new ContainsTagWithAttribute('a','href',$this->node->action->out()), $html2);
    }
    public function test_format_display_course_content() {
        $this->assertTrue($this->node->exposed_format_display_course_content('topic'));
        $this->assertFalse($this->node->exposed_format_display_course_content('scorm'));
        $this->assertTrue($this->node->exposed_format_display_course_content('dummy'));
    }
    public function test_load_course() {
        $course = new stdClass;
        $course->id = 'tcourse10';
        $course->fullname = 'Test Course 10';
        $course->shortname = 'tcourse10';
        $course->visible = true;
        $this->node->exposed_load_course(array('cat2','sub3'), $course);
        $this->assertIsA($this->node->get('cat2')->get('sub3')->get('tcourse10'), 'navigation_node');
    }
    public function test_load_course_activities() {
        $keys = array('cat2', 'sub2', '5');
        $course = new stdClass;
        $course->id = '5';
        $modinfo = $this->cache->modinfo5;
        $modinfo->cms[290] = clone($modinfo->cms[288]);
        $modinfo->cms[290]->id = 290;
        $modinfo->cms[290]->modname = 'resource';
        $modinfo->cms[290]->instance = 21;
        $modinfo->instances['resource'] = array();
        $modinfo->instances['resource'][21] = clone($modinfo->instances['forum'][19]);
        $modinfo->instances['resource'][21]->id = 21;
        $this->cache->modinfo5 = $modinfo;
        $this->node->exposed_load_course_activities($keys, $course);
        $this->assertIsA($this->node->get_by_path(array_merge($keys, array(288))), 'navigation_node');
        $this->assertEqual($this->node->get_by_path(array_merge($keys, array(288)))->type, navigation_node::TYPE_ACTIVITY);
        $this->assertIsA($this->node->get_by_path(array_merge($keys, array(290))), 'navigation_node');
        $this->assertEqual($this->node->get_by_path(array_merge($keys, array(290)))->type, navigation_node::TYPE_RESOURCE);
    }
    public function test_load_course_sections() {
        $keys = array('cat2', 'sub2', '5');
        $course = new stdClass;
        $course->id = '5';
        $course->format = 'topics';
        $coursechildren = $this->node->get_by_path($keys)->children;
        
        $this->node->get_by_path(array('cat2', 'sub2', '5'))->children = array();
        $this->node->exposed_load_course_sections($keys, $course);

        $course->format = 'topics';
        $this->node->get_by_path(array('cat2', 'sub2', '5'))->children = array();
        $this->node->exposed_load_course_sections($keys, $course);

        $course->format = 'scorm';
        $this->node->get_by_path(array('cat2', 'sub2', '5'))->children = array();
        $this->node->exposed_load_course_sections($keys, $course);

        $course->format = 'sillywilly';
        $this->node->get_by_path(array('cat2', 'sub2', '5'))->children = array();
        $this->node->exposed_load_course_sections($keys, $course);

        $this->node->get_by_path($keys)->children = $coursechildren;
    }
    public function test_load_for_user() {
        $this->node->exposed_load_for_user();
    }
    public function test_load_section_activities() {
        $keys = array('cat2', 'sub2', '5');
        $course = new stdClass;
        $course->id = '5';
        $this->node->get_by_path($keys)->add('Test Section 1', null, $this->cache->coursesections5[1]->id, navigation_node::TYPE_SECTION);
        $modinfo = $this->cache->modinfo5;
        $modinfo->sections[1] = array(289, 290);
        $modinfo->cms[289] = clone($modinfo->cms[288]);
        $modinfo->cms[289]->id = 289;
        $modinfo->cms[289]->sectionnum = 1;
        $modinfo->cms[290]->modname = 'forum';
        $modinfo->cms[289]->instance = 20;
        $modinfo->cms[290] = clone($modinfo->cms[288]);
        $modinfo->cms[290]->id = 290;
        $modinfo->cms[290]->modname = 'resource';
        $modinfo->cms[290]->sectionnum = 1;
        $modinfo->cms[290]->instance = 21;
        $modinfo->instances['forum'][20] = clone($modinfo->instances['forum'][19]);
        $modinfo->instances['forum'][20]->id = 20;
        $modinfo->instances['resource'] = array();
        $modinfo->instances['resource'][21] = clone($modinfo->instances['forum'][19]);
        $modinfo->instances['resource'][21]->id = 21;
        $this->cache->modinfo5 = $modinfo;
        $this->node->exposed_load_section_activities($keys, 1, $course);
        $keys[] = 97;
        $this->assertIsA($this->node->get_by_path(array_merge($keys, array(289))),'navigation_node');
        $this->assertEqual($this->node->get_by_path(array_merge($keys, array(289)))->type, navigation_node::TYPE_ACTIVITY);
        $this->assertIsA($this->node->get_by_path(array_merge($keys, array(290))),'navigation_node');
        $this->assertEqual($this->node->get_by_path(array_merge($keys, array(290)))->type, navigation_node::TYPE_RESOURCE);
    }
    public function test_module_extends_navigation() {
        $this->cache->test1_extends_navigation = true;
        $this->cache->test2_extends_navigation = false;
        $this->assertTrue($this->node->exposed_module_extends_navigation('forum'));
        $this->assertTrue($this->node->exposed_module_extends_navigation('test1'));
        $this->assertFalse($this->node->exposed_module_extends_navigation('test2'));
        $this->assertFalse($this->node->exposed_module_extends_navigation('test3'));
    }
}

/**
 * This is a dummy object that allows us to call protected methods within the
 * global navigation class by prefixing the methods with `exposed_`
 */
class exposed_navbar extends navbar {
    protected $exposedkey = 'exposed_';
    function __construct() {
        global $PAGE;
        parent::__construct($PAGE);
        $this->cache = new navigation_cache('simpletest_nav');
    }
    function __call($method, $arguments) {
        if (strpos($method,$this->exposedkey) !== false) {
            $method = substr($method, strlen($this->exposedkey));
        }
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $arguments);
        }
        throw new coding_exception('You have attempted to access a method that does not exist for the given object '.$method, DEBUG_DEVELOPER);
    }
}

class navbar_test extends UnitTestCase {
    protected $node;
    protected $oldnav;

    public static $includecoverage = array('./lib/navigationlib.php');
    public static $excludecoverage = array();

    public function setUp() {
        global $PAGE;
        $this->oldnav = $PAGE->navigation;
        $this->cache = new navigation_cache('simpletest_nav');
        $this->node = new exposed_navbar();
        $temptree = new global_navigation_test();
        $temptree->setUp();
        $temptree->node->get_by_path(array('cat2','sub2', 'course2'))->make_active();
        $PAGE->navigation = $temptree->node;
    }
    public function tearDown() {
        global $PAGE;
        $PAGE->navigation = $this->oldnav;
    }
    public function test_add() {
        global $CFG;
        // Add a node with all args set
        $this->node->add('test_add_1','testadd1','testadd1',navigation_node::TYPE_COURSE,'http://www.moodle.org/',$CFG->httpswwwroot . '/pix/i/course.gif');
        // Add a node with the minimum args required
        $key2 = $this->node->add('test_add_2');
        $this->assertIsA($this->node->get('testadd1'), 'navigation_node');
        $this->assertIsA($this->node->get('testadd1')->get($key2), 'navigation_node');
    }
    public function test_content() {
        $html = $this->node->content();
        $this->assert(new ContainsTagWithAttribute('a','href',$this->node->action->out()), $html);
    }
    public function test_has_items() {
        global $PAGE;
        $this->assertTrue($this->node->has_items());
        $PAGE->navigation->get_by_path(array('cat2','sub2', 'course2'))->remove_class('active_tree_node');
        $PAGE->navigation->get_by_path(array('cat2','sub2', 'course2'))->isactive = false;
        $this->assertFalse($this->node->has_items());
        $PAGE->navigation->get_by_path(array('cat2','sub2', 'course2'))->make_active();
    }
    public function test_parse_branch_to_html() {
        global $CFG;
        $key = $this->node->add('test_add_1','testadd1','testadd1',navigation_node::TYPE_COURSE,'http://www.moodle.org/',$CFG->httpswwwroot . '/pix/i/course.gif');
        $this->node->get($key)->make_active();
        $html = $this->node->exposed_parse_branch_to_html($this->node->children, true, true);
        $this->assert(new ContainsTagWithAttribute('a','href',$this->node->action->out()), $html);
    }
}

class navigation_cache_test extends UnitTestCase {
    protected $cache;

    public static $includecoverage = array('./lib/navigationlib.php');
    public static $excludecoverage = array();

    public function setUp() {
        $this->cache = new navigation_cache('simpletest_nav');
        $this->cache->anysetvariable = true;
    }
    public function test___get() {
        $this->assertTrue($this->cache->anysetvariable);
        $this->assertEqual($this->cache->notasetvariable, null);
    }
    public function test___set() {
        $this->cache->myname = 'Sam Hemelryk';
        $this->assertTrue($this->cache->cached('myname'));
        $this->assertEqual($this->cache->myname, 'Sam Hemelryk');
    }
    public function test_cached() {
        $this->assertTrue($this->cache->cached('anysetvariable'));
        $this->assertFalse($this->cache->cached('notasetvariable'));
    }
    public function test_clear() {
        $cache = clone($this->cache);
        $this->assertTrue($cache->cached('anysetvariable'));
        $cache->clear();
        $this->assertFalse($cache->cached('anysetvariable'));
    }
    public function test_set() {
        $this->cache->set('software', 'Moodle');
        $this->assertTrue($this->cache->cached('software'));
        $this->assertEqual($this->cache->software, 'Moodle');
    }
}

/**
 * This is a dummy object that allows us to call protected methods within the
 * global navigation class by prefixing the methods with `exposed_`
 */
class exposed_settings_navigation extends settings_navigation {
    protected $exposedkey = 'exposed_';
    function __construct() {
        global $PAGE;
        parent::__construct($PAGE);
        $this->cache = new navigation_cache('simpletest_nav');
    }
    function __call($method, $arguments) {
        if (strpos($method,$this->exposedkey) !== false) {
            $method = substr($method, strlen($this->exposedkey));
        }
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $arguments);
        }
        throw new coding_exception('You have attempted to access a method that does not exist for the given object '.$method, DEBUG_DEVELOPER);
    }
}

class settings_navigation_test extends UnitTestCase {
    protected $node;
    protected $cache;

    public static $includecoverage = array('./lib/navigationlib.php');
    public static $excludecoverage = array();

    public function setUp() {
        global $PAGE;
        $this->cache = new navigation_cache('simpletest_nav');
        $this->node = new exposed_settings_navigation();
    }
    public function test___construct() {
        $this->node = new exposed_settings_navigation();
    }
    public function test___initialise() {
        $this->node->initialise();
        $this->assertEqual($this->node->id, 'settingsnav');
    }
    public function test_load_front_page_settings() {
        $this->node->exposed_load_front_page_settings();
        $settings = false;
        foreach ($this->node->children as $child) {
            if ($child->id === 'frontpagesettings') {
                $settings = $child;
            }
        }
        $this->assertIsA($settings, 'navigation_node');
    }
    public function test_in_alternative_role() {
        $this->assertFalse($this->node->exposed_in_alternative_role());
    }
    public function test_remove_empty_root_branches() {
        $this->node->add('rootbranch1', null, 'rootbranch1');
        $this->node->add('rootbranch2', null, 'rootbranch2');
        $this->node->add('rootbranch3', null, 'rootbranch3');
        $this->node->get('rootbranch2')->add('something', null, null, navigation_node::TYPE_SETTING);
        $this->node->remove_empty_root_branches();
        $this->assertFalse($this->node->get('rootbranch1'));
        $this->assertIsA($this->node->get('rootbranch2'), 'navigation_node');
        $this->assertFalse($this->node->get('rootbranch3'));
    }
}