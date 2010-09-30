// This script, and the YUI libraries that it needs, are inluded by
// question_flags::initialise_js in question/engine/lib.php.

question_flag_changer = {
    flag_state_listeners: new Object(),

    init_flag: function(checkboxid, postdata, slot) {
        // Create a hidden input - you can't just repurpose the old checkbox, IE
        // does not cope - and put it in place of the checkbox.
        var checkbox = document.getElementById(checkboxid + 'checkbox');
        var input = document.createElement('input');
        input.type = 'hidden';
        checkbox.parentNode.appendChild(input);
        checkbox.parentNode.removeChild(checkbox);
        input.id = checkbox.id;
        input.name = checkbox.name;
        input.value = checkbox.checked ? 1 : 0;
        input.ajaxpostdata = postdata;
        input.slot = slot;

        // Create an image input to replace the img tag.
        var image = document.createElement('input');
        image.type = 'image';
        image.statestore = input;
        question_flag_changer.update_image(image);
        input.parentNode.appendChild(image);

        // Remove the label.
        var label = document.getElementById(checkboxid + 'label');
        label.parentNode.removeChild(label);

        // Add the event handler.
        YAHOO.util.Event.addListener(image, 'click', this.flag_state_change);
    },

    init_flag_save_form: function(submitbuttonid) {
        // With JS on, we just want to remove all visible traces of the form.
        var button = document.getElementById(submitbuttonid);
        button.parentNode.removeChild(button);
    },

    flag_state_change: function(e) {
        var image = e.target ? e.target : e.srcElement;
        var input = image.statestore;
        input.value = 1 - input.value;
        question_flag_changer.update_image(image);
        var postdata = input.ajaxpostdata;
        if (input.value == 1) {
            postdata += '&newstate=1';
        } else {
            postdata += '&newstate=0';
        }
        YAHOO.util.Connect.asyncRequest('POST', qengine_config.actionurl, null, postdata);
        question_flag_changer.fire_state_changed(input);
        YAHOO.util.Event.preventDefault(e);
    },

    update_image: function(image) {
        if (image.statestore.value == 1) {
            image.src = qengine_config.flagicon;
            image.alt = qengine_config.flaggedalt;
            image.title = qengine_config.unflagtooltip;
        } else {
            image.src = qengine_config.unflagicon;
            image.alt = qengine_config.unflaggedalt;
            image.title = qengine_config.flagtooltip;
        }
    },

    add_flag_state_listener: function(slot, listener) {
        var key = 'q' + slot;
        if (!question_flag_changer.flag_state_listeners.hasOwnProperty(key)) {
            question_flag_changer.flag_state_listeners[key] = [];
        }
        question_flag_changer.flag_state_listeners[key].push(listener);
    },

    fire_state_changed: function(input) {
        var slot = input.slot;
        var key = 'q' + slot;
        if (!question_flag_changer.flag_state_listeners.hasOwnProperty(key)) {
            return;
        }
        var newstate = input.value == 1;
        for (var i = 0; i < question_flag_changer.flag_state_listeners[key].length; i++) {
            question_flag_changer.flag_state_listeners[key][i].flag_state_changed(newstate);
        }
    }
};

/**
 * Initialise a question submit button. This saves the scroll position and
 * sets the fragment on the form submit URL so the page reloads in the right place.
 * @param id the id of the button in the HTML.
 * @param slot the number of the question_attempt within the usage.
 */
function question_init_submit_button(id, slot) {
    var button = document.getElementById(id);
    YAHOO.util.Event.addListener(button, 'click', function(e) {
        var scrollpos = document.getElementById('scrollpos');
        if (scrollpos) {
            scrollpos.value = YAHOO.util.Dom.getDocumentScrollTop();
        }
        button.form.action = button.form.action + '#q' + slot;
    });
}

/**
 * Initialise the question form.
 * @param id the id of the form in the HTML.
 */
function question_init_form(id) {
    var responseform = document.getElementById(id);
    responseform.setAttribute('autocomplete', 'off');

    YAHOO.util.Event.addListener(responseform, 'keypress', question_filter_key_events);
    YAHOO.util.Event.addListener(responseform, 'submit', question_prevent_repeat_submission, document.body);

    var matches = window.location.href.match(/^.*[?&]scrollpos=(\d*)(?:&|$|#).*$/, '$1');
    if (matches) {
        // onDOMReady is the effective one here. I am leaving the immediate call to
        // window.scrollTo in case it reduces flicker.
        window.scrollTo(0, matches[1]);
        YAHOO.util.Event.onDOMReady(function() { window.scrollTo(0, matches[1]); });
        // And the following horror is necessary to make it work in IE 8.
        // Note that the class ie8 on body is only there in Moodle 2.0 and OU Moodle.
        if (YAHOO.util.Dom.hasClass(document.body, 'ie')) {
            question_force_ie_to_scroll(matches[1])
        }
    }
}

/**
 * Event handler to stop the quiz form being submitted more than once.
 * @param e the form submit event.
 * @param form the form element.
 */
function question_prevent_repeat_submission(e, container) {
    if (container.questionformalreadysubmitted) {
        YAHOO.util.Event.stopEvent(e);
        return;
    }

    setTimeout(function() {
        YAHOO.util.Dom.getElementsBy(function(el) {
            return el.type == 'submit';
        }, 'input', container, function(el) {
            el.disabled = true; });
        }, 0);
    container.questionformalreadysubmitted = true;
}

/**
 * Beat IE into submission.
 * @param targetpos the target scroll position.
 */
function question_force_ie_to_scroll(targetpos) {
    var hackcount = 25;
    function do_scroll() {
        window.scrollTo(0, targetpos);
        hackcount -= 1;
        if (hackcount > 0) {
            setTimeout(do_scroll, 10);
        }
    }
    YAHOO.util.Event.addListener(window, 'load', do_scroll);
}

/**
 * Used as an onkeypress handler to stop enter submitting the forum unless you
 * are actually on the submit button. Does not stop the user typing things in
 * text areas, etc.
 */
function question_filter_key_events(e) {
    var target = e.target ? e.target : e.srcElement;
    var keyCode = e.keyCode ? e.keyCode : e.which;
    if (keyCode==13 && target.nodeName.toLowerCase()!='a' &&
            (!target.type || !(target.type=='submit' || target.type=='textarea'))) {
        YAHOO.util.Event.preventDefault(e);
    }
}
