// set the save and reset buttons to active and original color/text
function setButtonsActive () {
    $("#savebut").css("color", "");
    $("#savebut").html("<?php echo $lang['save_changes'] ?>");
    $("#savebut").prop("disabled", false);
    $("#resetbut").prop("disabled", false);
}

// this will contain all languages represented - populated in onload below
var langs = [];

// onload
$(function() {

    // detect changes to sorting and hiding
    $("#sortable").sortable({ change: setButtonsActive });
    $("#sortable :checkbox").change( setButtonsActive );
    $("#localcontent").change(function() {
        $.ajax({
            url: "background.php?setLocalContent=" + (this.checked ? 1 : 0)
        });
    });

    // create language filtering options XXX optimize by using native JS?
    // first we grab each language code by going through the modules
    var langhash = {};
    $("#sortable :checkbox").each(function () {
        match = $(this).attr('id').match(/^(..)-/);
        if (match && match[0]) { langhash[ match[1] ] = true; }
    });
    // then we convert it to an array
    for (var lang in langhash) {
        if(langhash.hasOwnProperty(lang)) {
            langs.push(lang);
        }
    }
    // then we create a button for each
    langs.sort();
    langs.reverse();
    var langsLength = langs.length
    for (var i = 0; i < langsLength; ++i) {
        $("#controls").prepend(
            "<button onclick=\"changelang(this, '"+langs[i]+"', true);\"><?php echo $lang['hide'] ?> "+langs[i]+"</button>"
        );
    }

    $("#controls").prepend("<br>");

    // add a sort button
    for (var i = 0; i < langsLength; ++i) {
        $("#controls").prepend(
            "<button onclick=\"sortlang(this, '"+langs[i]+"');\"><?php echo $lang['sort'] ?> "+langs[i]+"</button>"
        );
    }

    // add a warning when leaving page with unsaved changes
    // (is this more helpful or annoying?)
    $(window).bind('beforeunload', function () {
        if (!$("#savebut").prop("disabled")) {
            // any string is fine - each browser has it's own output
            return "Changes Not Saved";
        }
    });

});

// save button calls this to save the module order & hiding via AJAX
function saveModState() {
    $("#savebut").html("Saving...");
    $("#savebut").prop("disabled", true);
    $("#resetbut").prop("disabled", true);
    var ordered = $("#sortable").sortable("toArray");
    var hidden = [];
    var orderedLength = ordered.length;
    for (var i = 0; i < orderedLength; ++i) {
        if ($("#"+ordered[i]+"-hidden").prop("checked")) {
            hidden.push(ordered[i]);
        }
    }

    $.ajax({
        url: "modules.php?moddirs=" + ordered.join(",")
            + "&hidden=" + hidden.join(","),
        success: function() {
            $("#savebut").css("color", "green");
            $("#savebut").html("&#10004; <?php echo $lang['saved'] ?>");
        },
        error: function() {
            $("#savebut").css("color", "#c00");
            $("#savebut").html("X <?php echo $lang['not_saved_error'] ?>");
            $("#resetbut").prop("disabled", false);
        }
    });
}

// mark all hide boxes on or off
function changeall(myself, value) {
    $("input[type=checkbox]").each(function () {
        $(this).prop("checked", value);
    });
    setButtonsActive();
    $(myself).blur();
}

// mark hide boxes for a particular language only 
function changelang(myself, lang, value) {
    var regex = new RegExp("^"+lang+"-");
    $("input[type=checkbox]").each(function () {
        if ($(this).attr('id').match(regex)) {
            $(this).prop("checked", value);
        }
    });
    setButtonsActive();
    $(myself).blur();
}

// sort the given language to the top of the list,
// alphabetically beyond that
function sortlang(myself, lang) {
    var items = $("#sortable").children("li");
    var regex = new RegExp("^"+lang+"-");
    items.sort(function(a,b) {
        if (a.id.match(regex) && b.id.match(regex)) {
            // both our top language? direct compare.
            if (a.id.toLowerCase() < b.id.toLowerCase()) { return -1; }
            if (a.id.toLowerCase() > b.id.toLowerCase()) { return 1; }
            return 0;
        } else {
            // one or the other our top laguage? special treatment.
            if (a.id.match(regex)) { return -1; }
            if (b.id.match(regex)) { return 1; }
            // both not our top language? direct compare.
            if (a.id.toLowerCase() < b.id.toLowerCase()) { return -1; }
            if (a.id.toLowerCase() > b.id.toLowerCase()) { return 1; }
            return 0;
        }
    });
    $("#sortable").empty().html(items);
    setButtonsActive();
    $(myself).blur();
}
