// This script, and the YUI libraries that it needs, are inluded by
// question_flags::initialise_js in question/engine/lib.php.

question_flag_changer = {
    flag_state_listeners: new Object(),

    init_flag: function(checkboxid, postdata, qnumber) {
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
        input.qnumber = qnumber;

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
        var postdata = input.ajaxpostdata
        if (input.value == 1) {
            postdata += '&newstate=1'
        } else {
            postdata += '&newstate=0'
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

    add_flag_state_listener: function(qnumber, listener) {
        var key = 'q' + qnumber;
        if (!question_flag_changer.flag_state_listeners.hasOwnProperty(key)) {
            question_flag_changer.flag_state_listeners[key] = [];
        }
        question_flag_changer.flag_state_listeners[key].push(listener);
    },

    fire_state_changed: function(input) {
        var qnumber = input.qnumber;
        var key = 'q' + qnumber;
        if (!question_flag_changer.flag_state_listeners.hasOwnProperty(key)) {
            return;
        }
        var newstate = input.value == 1;
        for (var i = 0; i < question_flag_changer.flag_state_listeners[key].length; i++) {
            question_flag_changer.flag_state_listeners[key][i].flag_state_changed(newstate);
        }
    }
};

function question_init_submit_button(id, qnumber) {
    var button = document.getElementById(id);
    YAHOO.util.Event.addListener(button, 'click', function(e) {
        var scrollpos = document.getElementById('scrollpos');
        if (scrollpos) {
            scrollpos.value = YAHOO.util.Dom.getDocumentScrollTop();
        }
        button.form.action = button.form.action + '#q' + qnumber;
    });
}

function question_init_form(id) {
    var responseform = document.getElementById(id);
    responseform.setAttribute('autocomplete', 'off');
    YAHOO.util.Event.addListener(responseform, 'keypress', question_filter_key_events);
    var matches = window.location.href.match(/^.*[?&]scrollpos=(\d*)(?:&|$|#).*$/, '$1');
    if (matches) {
        // onDOMReady is the effective one here. I am leaving the immediate call to
        // window.scrollTo in case it reduces flicker.
        window.scrollTo(0, matches[1]);
        YAHOO.util.Event.onDOMReady(function() { window.scrollTo(0, matches[1]); });
    }
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
