/**
 * JavaScript objects, functions as well as usage of some YUI library for
 * enabling drag and drop interaction for dran-anddrop words into sentences
 * (ddwtos)
 *
 * @package qtype_ddwtos
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//global variables
var ddwtos_currentzindex = 10;


/*
 * The way it seems to be, if there are more than one of this type of question
 * in a quiz, then this file is shared between them. Therefore it has to cope
 * with ALL the questions of this type on the page.
 */
(function() {

    var Dom = YAHOO.util.Dom;
    var Event = YAHOO.util.Event;
    var Lang = YAHOO.lang;
    var DD = YAHOO.util.DragDrop;
    var Manager = YAHOO.util.DragDropMgr;
    var ViewportWidth = YAHOO.util.Dom.getViewportWidth();

    // start of App object SHARED BY ALL QUESTIONS OF THIS TYPE IN THE QUIZ /////
    YAHOO.example.DDApp = {
        init : function() {
            try {
                var questionspans = Dom.getElementsByClassName("ddwtos_questionid_for_javascript");

                // we need this loop in case of more than one of this qtype on one page
                for (var i = 0; i < questionspans.length; i++) {
                    // The Questions object should now contain a QuestionDataObject
                    //object for each question of this type in the quiz.
                    Questions[questionspans[i].id] = new QuestionDataObject(questionspans[i].id);
                }

                // populate the arrays "slots" and "players" for each question
                var tempSlots = Dom.getElementsByClassName("slot", "span");
                var tempPlayers = Dom.getElementsByClassName("player", "span");

                //ie7 zoom message
                ie7_zoom_message();

                for (var i = 0; i < tempSlots.length; i++) {
                    var name_prefix = tempSlots[i].id.split("_")[0] + "_";
                    var q = Questions[name_prefix];
                    var g = getGroupForThis(tempSlots[i].id);

                    var ddtarget = new YAHOO.util.DDTarget(tempSlots[i].id, g);
                    q.tempSlots.push(tempSlots[i]);
                    q.slots.push(ddtarget);
                }

                for (var i = 0; i < tempPlayers.length; i++) {
                    var name_prefix = tempPlayers[i].id.split("_")[0] + "_";
                    var q = Questions[name_prefix];
                    var g = getGroupForThis(tempPlayers[i].id);
                    var ddplayer = new YAHOO.example.DDPlayer(tempPlayers[i].id, g);
                    q.tempPlayers.push(tempPlayers[i]);
                    q.players.push(ddplayer);
                }

                for (var i = 0; i < questionspans.length; i++) {
                    var q = Questions[questionspans[i].id];
                    var groupwidth = getWidthForAllGroups(q.tempPlayers);
                    for (var j = 0; j < q.tempSlots.length; j++) {
                        var g = getGroupForThis(q.tempSlots[j].id);
                        setWidth(q.tempSlots[j], groupwidth[g]);
                    }
                    for (var j = 0; j < q.tempPlayers.length; j++) {
                        var g = getGroupForThis(q.tempPlayers[j].id);
                        setWidth(q.tempPlayers[j], groupwidth[g]);
                    }

                    // set responses for all slots
                    setResponsesForAllSlots(q.tempSlots, q.tempPlayers);
                }

            } catch (e) {
                alert('ERROR in (init): ' + e.message);
            }
        }
    };
    // end of App object ////////////////////////////////////////////////////////

    // beginning of Player object (the draggable item) //////////////////////////
    YAHOO.example.DDPlayer = function(id, sGroup, config) {
        YAHOO.example.DDPlayer.superclass.constructor.apply(this, arguments);
        this.initPlayer(id, sGroup, config);
    };

    YAHOO.extend(YAHOO.example.DDPlayer, YAHOO.util.DD, {
        TYPE :"DDPlayer",

        initPlayer : function(id, sGroup, config) {
            this.isTarget = false;
            this.currentPos = Dom.getXY(this.getEl());

        },

        //Abstract method called after a drag/drop object is clicked and the drag or mousedown time thresholds have beeen met.
        startDrag : function(x, y) {
            Dom.setStyle(this.getEl(), "zIndex", ddwtos_currentzindex++);

            if (is_infinite(this.getEl()) && !this.slot){
                var currentplayer = this.getEl().id.replace(/_clone[0-9]+$/, '');
                var ddplayer = Manager.getDDById(currentplayer);
                clone_player(ddplayer);
            }

            if (this.slot) { // dragging starts from a slot
                var hiddenElement = document.getElementById(this.slot.getEl().id + '_hidden');
                hiddenElement.value = '';
                this.slot.player = null;
                this.slot = null;
            }
        },

        //Abstract method called when this item is dropped on another DragDrop obj
        onDragDrop : function(e, id) {
            // get the drag and drop object that was targeted
            var target = Manager.getDDById(id);
            var dragged = this.getEl();

            //get the question-prefix of slot and player and check whether they belong to the same question
            var slotprefix = target.id.split("_")[0] + "_";
            var playerprefix = dragged.id.split("_")[0] + "_";
            if (slotprefix != playerprefix){
                var p = Manager.getDDById(dragged.id);
                p.startDrag(0,0);
                p.onInvalidDrop(null);
                return;
            }

            show_element(this.getEl());

            if (target.player) { // there's a player already there
                var oldplayer = target.player;
                oldplayer.startDrag(0,0);
                oldplayer.onInvalidDrop(null);
            }

            Manager.moveToEl(dragged, target.getEl());
            this.slot = target;
            target.player = this;
            Dom.setXY(target.player.getEl(), Dom.getXY(target.getEl()));

            // set value
            var hiddenElement = document.getElementById(id + '_hidden');
            hiddenElement.value = this.getEl().id.replace(/_clone[0-9]+$/, '');
        },

        //Abstract method called when this item is dropped on an area with no drop target
        onInvalidDrop : function(e) {
            Dom.setXY(this.getEl(), Dom.getXY(this.originalplayer.getEl()));
        }
    });
    // end of Player object (the draggable item) ////////////////////////////////

    Event.onDOMReady(YAHOO.example.DDApp.init, YAHOO.example.DDApp, true);


    // Objects///////////////////////////////////////////////////////////////////
    var Questions = new Object();

    function QuestionDataObject(theid) {
        try {
            this.id = theid;
            this.tempSlots = [];
            this.tempPlayers = [];
            this.slots = [];
            this.players = [];
        } catch (e) {
            alert('ERROR in (QuestionDataObject): ' + e.message);
        }
    }

    // template for the slot object
    function SlotObject(id, group, currentvalue, values, callback) {
        try {
            this.id = id;
            this.group = group;
            this.currentvalue = currentvalue;
            this.values = values;
            this.callback = callback;
        } catch (e) {
            alert('ERROR in obj (SlotObject): ' + e.message);
        }
    }
    // End of Objects ///////////////////////////////////////////////////////////

    // functions ////////////////////////////////////////////////////////////////

    function clone_player(p) {
        var el = p.getEl();
        var newNode = document.createElement('div');
        newNode.className = el.className;
        newNode.innerHTML = el.innerHTML;

        if (!p.clones) {
            p.clones = Array();
        }
        newNode.id = el.id + '_clone' + p.clones.length; // _clone0 for first

        newNode.style.textAlign = 'center';

        newNode.style.position = 'absolute';

        var region = Dom.getRegion(el);
        var height = region.bottom - region.top;
        newNode.style.height = (height-7) + "px";
        newNode.style.paddingTop = "1px";
        newNode.style.paddingBottom = "5px";

        var width = region.right - region.left;
        newNode.style.width = (width-8) + "px";
        newNode.style.paddingLeft = "3px";
        newNode.style.paddingRight = "3px";

        el.parentNode.parentNode.appendChild(newNode);
        Dom.setXY(newNode, Dom.getXY(el));

        var g = getGroupForThis(el.id);
        var p2 = new YAHOO.example.DDPlayer(newNode.id, g);
        p2.originalplayer = p;

        hide_element(el);
        show_element(newNode);

        if (is_readonly(newNode)) {
            p2.lock();
        }

        p.clones[p.clones.length] = p2;
        return p2;
    }

    function clone_players(players){
        try{
            for (i in players) {
                var player = players[i];
                var p = Manager.getDDById(player.id);
                if (!p){
                    return;
                }

                //clone player
                clone_player(p);
            }
        } catch (e) {
            alert('ERROR in fun (clone_players): ' + e.message);
        }
    }

    function is_readonly(el){
        try{
            return document.getElementById(
                el.id.split("_")[0] + '_readonly').value == '1';
        } catch (e) {
            alert('ERROR in fun (is_readonly): ' + e.message);
        }
    }

    function is_infinite(el){
        try{
            var inf = el.id.split("_")[3];
            if (inf == 1) {
                return true;
            }
            return false;
        } catch (e) {
            alert('ERROR in fun (is_infinite): ' + e.message);
        }
    }

    function show_element(el){
        try{
            Dom.setStyle(el, "visibility", 'visible');
        } catch (e) {
            alert('ERROR in fun (show_element): ' + e.message);
        }
    }

    function hide_element(el){
        try{
            Dom.setStyle(el, "visibility", 'hidden');
        } catch (e) {
            alert('ERROR in fun (hide_element): ' + e.message);
        }
    }

    function list_of_slots_and_players(slots, players) {
        try {
            this.slots = slots;
            this.players = players;
        } catch (e) {
            alert('ERROR in obj (list_of_slots_and_players): ' + e.message);
        }
    }

    function set_xy_after_resize(e, slotsandplayerobj){
        setTimeout(function() {set_xy_after_resize_actual(e,slotsandplayerobj); }, 0);
    }
    function set_xy_after_resize_actual(e, slotsandplayerobj) {
        //document.title='Resize done';
        try{
            var slots = slotsandplayerobj.slots;
            var players = slotsandplayerobj.players;
            for (var p in players) {
                var original = Manager.getDDById(players[p].id);
                for(var index in original.clones) {
                    var c = original.clones[index];
                    if (c.slot) {
                        //player is in slot
                        Dom.setXY(c.getEl(), Dom.getXY(c.slot.getEl()));
                    }
                    else {
                        //player is not in slot
                        Dom.setXY(c.getEl(), Dom.getXY(c.originalplayer.getEl()));
                    }
                }
            }
        } catch (e) {
            alert('ERROR in fun (set_xy_after_resize): ' + e.message);
        }
    }

    function strip_suffix(str, suffix){
        var index = str.indexOf(suffix);
        return str.substr(0, index);
    }

    function setResponsesForAllSlots(slots, players) {
        try {
            clone_players(players);

            for (var i in slots) {

                // set up the variables for the SlotObject
                var slot = document.getElementById(slots[i].id);
                if (!slot) continue;

                var hiddenElement = document.getElementById(slot.id + '_hidden');
                if (!hiddenElement) continue;

                // get group
                var group = getGroupForThis(slot.id);

                // get array of values
                var values = getValuesForThisSlot(slot.id, players);

                var currentvalue = hiddenElement.value ? hiddenElement.value : 0;
                // if slot is occupied
                if (currentvalue) {
                    // Find player
                    var original = Manager.getDDById(currentvalue);
                    var index = original.clones.length-1;
                    original.clones[index].startDrag(0, 0);
                    original.clones[index].onDragDrop(null, slot.id);
                }
                Event.on(slot.id, "focus", setFocus);
                Event.on(slot.id, "blur", setBlur);

                Event.on(slot.id, "mousedown", mouseDown);

                var myobj = new SlotObject(slot.id, group, currentvalue, values, funCallKeys);

                // event keydown
                Event.addListener(slot.id, "keydown", funCallKeys, myobj);
            }

            //resize
            var listofslotsandplayers = new list_of_slots_and_players(slots, players);
            Event.on(window, "resize", set_xy_after_resize, listofslotsandplayers);

        } catch (e) {
            alert('ERROR in fun (setResponsesForAllSlots): ' + e.message);
        }
    }

    function getValuesForThisSlot(slotid, players) {
        try {
            var gslot = getGroupForThis(slotid);
            var values = new Array();
            var i = 0;
            for (var player in players) {
                var pElement = document.getElementById(players[player].id);
                var gplayer = getGroupForThis(pElement.id);

                // from the same group
                if (gslot == gplayer) {
                    values[i++] = pElement.id;
                }
            }
            return values;
        } catch (e) {
            alert('ERROR in fun (getValuesForThisSlot): ' + e.message);
        }
    }

    function getWidthForAllGroups(allplayers) {
        try {
            var widtharray = new Array();
            for (var i = 0; i < allplayers.length; i++) {
                var g = getGroupForThis(allplayers[i].id);
                var region = Dom.getRegion(allplayers[i]);
                var width = region.right - region.left;
                if (widtharray[g]) {
                    if (width < widtharray[g]) {
                        continue;
                    }
                }
                widtharray[g] = width;
            }
            return widtharray;
        } catch (e) {
            alert('ERROR in fun (getWidthForAllGroups): ' + e.message);
        }
    }

    function getWidthForThisGroup(allplayers, group) {
        try {
            var tempwidth = 0;
            for (var i = 0; i < allplayers.length; i++) {
                var g = getGroupForThis(allplayers[i].id);
                if (group != g)
                    continue;
                var region = Dom.getRegion(allplayers[i]);
                var width = region.right - region.left;
                if (width > tempwidth) {
                    tempwidth = width;
                }
            }
            return tempwidth;
        } catch (e) {
            alert('ERROR in fun (getWidthForThisGroup): ' + e.message);
        }
    }

    function getWidthForThisElement(el) {
        try {
            var region = Dom.getRegion(el);
            var width = region.right - region.left;
            return width;
        } catch (e) {
            alert('ERROR in fun (getWidthForThisElement): ' + e.message);
        }
    }

    function setWidth(el, gwidth) {
        try {
            var width = getWidthForThisElement(el);
            var remainder = (gwidth - width) + 10;

            // IE8 does not rewrap lines when the padding changes, so this
            // change uses different layout for that browser version only.
            if (navigator.appVersion.indexOf('MSIE 8') != -1) {
                var region = Dom.getRegion(el);
                var height = region.bottom - region.top;
                el.style.display = 'inline-block';
                el.style.width = gwidth + 'px';
                el.style.height = (el.className.indexOf('slot ')==0 ? height-5 : height-6) + 'px';
                return;
            }

            Dom.setStyle(el, 'padding-right', Math.floor((remainder + 1) / 2) + 'px');
            Dom.setStyle(el, 'padding-left', Math.floor(remainder / 2) + 'px');

            //not IE
            if (!window.ActiveXObject) {
                Dom.setStyle(el, 'padding-top', '3px');
                Dom.setStyle(el, 'padding-bottom', '3px');
            }
        } catch (e) {
            alert('ERROR in fun (setWidth): ' + e.message);
        }
    }

    function funCallKeys(e, slotobj) {
        //disable the key access when readonly
        if (is_readonly(document.getElementById(slotobj.values[0]))) {
            return;
        }

        try {
            var evt = e || window.event;
            var key = evt.keyCode;

            switch (key) {
            case 39: // arrow right (forwards)
            case 40: // arrow down (forwards)
            case 32: // space (forwards)
                changeObject(slotobj, 1);
                return false; //this has to return false because of IE

            case 37: // arrow left (backwards)
            case 38: // arrow up (backwards)
                changeObject(slotobj, -1);
                return false; //this has to return false because of IE

            case 66: // B (backwards)
            case 98: // b (backwards)
            case 80: // P (previous)
            case 112: // p (previous)
                changeObject(slotobj, -1);
                break;

            case 13: // cariage return (forwards)
            case 70: // F (forwards)
            case 102: // f (forwards)
            case 78: // N (next)
            case 110: // n (next)
                changeObject(slotobj, 1);
                break;

            case 27: // escape (empty the drop box)
                changeObject(slotobj, 0);
            default:
                return true;
            }
            return true;
        } catch (e) {
            alert('ERROR in fun (funCallKeys): ' + e.message);
        }
    }

    function changeObject(slotobj, direction) {
        try {
            // Prevent infinite loop if there are no values for this slot
            if (slotobj.values.length == 0) {
                return;
            }

            var hiddenElement = document.getElementById(slotobj.id + '_hidden');

            if (direction == 0) {
                call_YUI_startDrag_onInvalidDrop(slotobj);
                return;
            }

            // Get current position in values list
            var selectedIndex = -1;
            for(var i=0; i<slotobj.values.length; i++) {
                if (slotobj.values[i] == hiddenElement.value) {
                    selectedIndex = i;
                    break;
                }
            }

            var currentIndex = selectedIndex;
            while(true) {
                // Get new position in values list
                selectedIndex += direction;

                //empty the slot at the beginning or the end of the players list
                if ((selectedIndex == -1) || (selectedIndex == slotobj.values.length)) {
                    call_YUI_startDrag_onInvalidDrop(slotobj);
                    return;
                }

                if (selectedIndex >= slotobj.values.length) {
                    selectedIndex = 0;
                }
                if (selectedIndex < 0) {
                    selectedIndex = slotobj.values.length-1;
                }

                // If we loop round back to the current one then there are
                // no more options, so stop
                if (selectedIndex == currentIndex) {
                    break;
                }

                // Check the item at the new position is not used
                var original = Manager.getDDById(slotobj.values[selectedIndex]);
                var index = original.clones.length-1;            ;
                if (!original.clones[index].slot) {
                    // This one is not in a slot so we can use it
                    original.clones[index].startDrag(0, 0);
                    original.clones[index].onDragDrop(null, slotobj.id);
                    break;
                }
            }
        } catch (e) {
            alert('ERROR in fun (changeObject): ' + e.message);
        }
    }

    function call_YUI_startDrag_onInvalidDrop(slotobj){
        var target = Manager.getDDById(slotobj.id);
        if (!target.player) {
            return;
        }
        var player = target.player;

        // Find player and call YUI methods
        player.startDrag(0, 0);
        player.onInvalidDrop(null);
    }

    function getGroupForThis(str) {
        try {
            var g = str.split("_")[2];
            return g;
        } catch (e) {
            alert('ERROR in fun (getGroupForThis): ' + e.message);
        }
    }

    function mouseDown() {
        //Dom.setStyle(this, 'border', 'thin  solid #0000FF');// blue
    }

    function setFocus() {
        Dom.setStyle(this, 'border-bottom', 'medium  solid #000000');

        // Bug 7674 - Strange drawing in drop box 1 on tabbing to it
        this.hideFocus=true;

        //IE8
        var browser = navigator.appVersion;
        if (browser.indexOf('MSIE 8.0') > -1){
            Dom.setStyle(this, 'border-bottom', 'thick  solid #000000');
        }
    }

    function setBlur() {
        Dom.setStyle(this, 'border-bottom', 'thin  solid #000000');
    }

    function ie7_zoom_message (){
        var browser = navigator.appVersion;
        if (browser.indexOf('MSIE 7') > -1){
            var b = document.body.getBoundingClientRect();
            var magnifactor = (b.right - b.left)/document.body.clientWidth;
            if (magnifactor < 0.9  || magnifactor > 1.1){

                var answers = Dom.getElementsByClassName("answercontainer", "div");
                for(var i=0; i<answers.length; i++) {
                    var block = document.createElement('div');

                    var text = 'This question type is not compatible with the zoom feature in Internet Explorer 7. '+
                                'Please press Ctrl+0 and then click on the question number in the navigation panel '+
                                'on the left to reload this question. Alternatively ';
                    block.appendChild(document.createTextNode(text));

                    //add ie8 link
                    var ie8link = document.createElement('a');
                    ie8link.href = 'http://www.microsoft.com/uk/windows/internet-explorer';
                    ie8link.appendChild(document.createTextNode('upgrade your browser to Internet Explorer 8'));
                    block.appendChild(ie8link);

                    var ie8textsuffix = ' before carrying on.';
                    block.appendChild(document.createTextNode(ie8textsuffix));

                    Dom.setStyle(block, 'margin', '5px 5px 5px 0');
                    Dom.setStyle(block, 'padding', '5px');
                    Dom.setStyle(block, 'border', 'thin  solid #BB0000');
                    Dom.setStyle(block, 'background-color', '#FFFAFA');
                    answers[i].parentNode.insertBefore(block, answers[i]);
                    innerHideAnswers(answers[i]);
                }
            }
        }
    }

    function innerHideAnswers(answers) {
        setTimeout(function() {
            answers.parentNode.removeChild(answers);
        }, 0);
    }

    //TODO: delete this function
    function reloadNow(){
        // Get current browser window width
        // hopefully with yui method
        var currentWidth = YAHOO.util.Dom.getViewportWidth();

        // the current window size different to stored one
        if (ViewportWidth != currentWidth) {
            setTimeout(function(){ location.reload(false); },0);
        }
    }

    // End of functions /////////////////////////////////////////////////////////

})();
