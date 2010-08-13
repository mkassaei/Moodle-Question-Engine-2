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


/*
 * JavaScript library for the quiz module.
 *
 * @package mod_quiz
 * @copyright 2007 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function quiz_init_form() {
    question_init_form('responseform');
    YAHOO.util.Event.addListener('responseform', 'submit', quiz_timer.stop);
}

// Code for updating the countdown timer that is used on timed quizzes.
quiz_timer = {
    // The outer div, so we can get at it to move it when the page scrolls.
    timerouter: null,

    // The element that the time should be displayed in.
    timerdisplay: null,

    // The main quiz for, which we will need to submit when the time expires.
    quizform: null,

    // String that is displayed after the time has run out.
    strtimeup: '',

    // How long is left, in seconds.
    endtime: 0,

    // How often we update the clock display. Delay in milliseconds.
    updatedelay: 500,

    // This records the id of the timeout that updates the clock periodically, so we can cancel it
    // Once time has run out.
    timeoutid: null,

    // Colours used to change the timer bacground colour when time had nearly run out.
    // This array is indexed by number of seconds left.
    finalcolours: [
        '#ff0000',
        '#ff1111',
        '#ff2222',
        '#ff3333',
        '#ff4444',
        '#ff5555',
        '#ff6666',
        '#ff7777',
        '#ff8888',
        '#ff9999',
        '#ffaaaa',
        '#ffbbbb',
        '#ffcccc',
        '#ffdddd',
        '#ffeeee',
        '#ffffff',
    ],

    // Initialise method.
    initialise: function(strtimeup, timeleft) {
        // Set some fields.
        quiz_timer.strtimeup = strtimeup;
        quiz_timer.endtime = new Date().getTime() + timeleft*1000;

        // Get references to some bits of the DOM we need.
        quiz_timer.timerouter = document.getElementById('quiz-timer');
        quiz_timer.timerdisplay = document.getElementById('quiz-time-left');
        quiz_timer.quizform = document.getElementById('responseform');

        // Make the timer visible.
        quiz_timer.timerouter.style.display = 'block';

        // Get things started.
        quiz_timer.update_time();
    },

    // Stop method. Stops the timer if it is running.
    stop: function() {
        if (quiz_timer.timeoutid) {
            clearTimeout(quiz_timer.timeoutid);
        }
    },

    // Function that updates the text displayed in element timer_display.
    set_displayed_time: function(str) {
        var display = quiz_timer.timerdisplay;
        if (!display.firstChild) {
            display.appendChild(document.createTextNode(str));
        } else if (display.firstChild.nodeType == 3) {
            display.firstChild.replaceData(0, display.firstChild.length, str);
        } else {
            display.replaceChild(document.createTextNode(str), display.firstChild);
        }
    },

    // Function to convert a number between 0 and 99 to a two-digit string.
    two_digit: function(num) {
        if (num < 10) {
            return '0' + num;
        } else {
            return num;
        }
    },

    // Function to update the clock with the current time left, and submit the quiz if necessary.
    update_time: function() {
        var secondsleft = Math.floor((quiz_timer.endtime - new Date().getTime())/1000);

        // If time has expired, Set the hidden form field that says time has expired.
        if (secondsleft < 0) {
            quiz_timer.stop();
            quiz_timer.set_displayed_time(quiz_timer.strtimeup);
            quiz_timer.quizform.elements.timeup.value = 1;
            if (quiz_timer.quizform.onsubmit) {
                quiz_timer.quizform.onsubmit();
            }
            quiz_timer.quizform.submit();
            return;
        }

        // If time has nearly expired, change the colour.
        if (secondsleft < quiz_timer.finalcolours.length) {
            quiz_timer.timerouter.style.backgroundColor = quiz_timer.finalcolours[secondsleft];
        }

        // Update the time display.
        var hours = Math.floor(secondsleft/3600);
        secondsleft -= hours*3600;
        var minutes = Math.floor(secondsleft/60);
        secondsleft -= minutes*60;
        var seconds = secondsleft;
        quiz_timer.set_displayed_time('' + hours + ':' + quiz_timer.two_digit(minutes) + ':' +
                quiz_timer.two_digit(seconds));

        // Arrange for this method to be called again soon.
        quiz_timer.timeoutid = setTimeout(quiz_timer.update_time, quiz_timer.updatedelay);
    }
};

// Initialise a button on the navigation panel.
function quiz_init_nav_button(buttonid, slot, strflagged) {
    var button = document.getElementById(buttonid);
    button.stateupdater = new quiz_nav_updater(button, slot, strflagged);
    if (document.getElementById('responseform')) {
        YAHOO.util.Event.addListener(button, 'click', quiz_nav_button_clicked);
    }
}

function quiz_init_end_link() {
    var link = document.getElementById('endtestlink');
    YAHOO.util.Event.addListener(link, 'click', function(e) {
        YAHOO.util.Event.preventDefault(e);
        quiz_navigate_to(-1);
    });
}

function quiz_hide_nav_warning() {
    var warning = document.getElementById('quiznojswarning');
    warning.parentNode.removeChild(warning);
}

function quiz_nav_button_clicked(e) {
    if (YAHOO.util.Dom.hasClass(this, 'thispage')) {
        return;
    }

    YAHOO.util.Event.preventDefault(e);

    var pageidmatch = this.href.match(/page=(\d+)/);
    var pageno;
    if (pageidmatch) {
        pageno = pageidmatch[1];
    } else {
        pageno = 0;
    }

    var form = document.getElementById('responseform');
    var questionidmatch = this.href.match(/#q(\d+)/);
    if (questionidmatch) {
        form.action = form.action + '#q' + questionidmatch[1];
    }

    quiz_navigate_to(pageno);
}

var quiz_form_submitted = false;
function quiz_navigate_to(pageno) {
    if (quiz_form_submitted) {
        return;
    }
    quiz_form_submitted = true;
    document.getElementById('nextpagehiddeninput').value = pageno;

    var form = document.getElementById('responseform');
    if (form.onsubmit) {
        form.onsubmit();
    }
    form.submit();
}

function quiz_nav_updater(element, slot, strflagged) {
    this.element = element;
    this.strflagged = strflagged;
    question_flag_changer.add_flag_state_listener(slot, this);
};

quiz_nav_updater.prototype.flag_state_changed = function(newstate) {
    this.element.className = this.element.className.replace(/\s*\bflagged\b\s*/, ' ');
    var flagstatediv = YAHOO.util.Dom.getElementsByClassName(
            'flagstate', 'span', this.element)[0];
    if (newstate) {
        YAHOO.util.Dom.addClass(this.element, 'flagged');
        flagstatediv.innerHTML = this.strflagged;
    } else {
        YAHOO.util.Dom.removeClass(this.element, 'flagged');
        flagstatediv.innerHTML = '';
    }
};

quiz_secure_window = {
    // The message displayed when the secure window interferes with the user.
    protection_message: null,

    // Used by close. The URL to redirect to, if we find we are not acutally in a pop-up window.
    close_next_url: '',

    // Code for secure window. This used to be in protect_js.php. I don't understand it,
    // I have just moved it for clenliness reasons.
    initialise: function(strmessage) {
        quiz_secure_window.protection_message = strmessage;
        if (document.layers) {
            document.captureEvents(Event.MOUSEDOWN);
        }
        document.onmousedown = quiz_secure_window.intercept_click;
        document.oncontextmenu = function() {alert(quiz_secure_window.protection_message); return false;};
    },

    // Code for secure window. This used to be in protect_js.php. I don't understand it,
    // I have just moved it for clenliness reasons.
    intercept_click: function(e) {
        if (document.all) {
            if (event.button==1) {
               return false;
            }
            if (event.button==2) {
               alert(quiz_securewindow_message);
               return false;
            }
        }
        if (document.layers) {
            if (e.which > 1) {
               alert(quiz_securewindow_message);
               return false;
            }
        }
    },

    close: function(url, delay) {
        if (url != '') {
            quiz_secure_window.close_next_url = url;
        }
        if (delay > 0) {
            setTimeout(function() {quiz_secure_window.close('eval (x)', 0);}, delay*1000);
        } else {
            if (window.opener) {
                window.opener.document.location.reload();
                window.close();
            } else if (quiz_secure_window.close_next_url != '') {
                window.location.href = quiz_secure_window.close_next_url;
            }
        }
    }
};