$(function() { // onload
    wifiShowWait();
    wifiStatus("check");
});

// interact with background.php to check, turn on, or turn off WIFI
function wifiStatus(status) {
    wifiShowWait();
    $.ajax({
        url: "background.php?wifistat=" + status,
        success: function(results) {
            if (results.wifistat == 1) {
                wifiShowOn();
            } else {
                wifiShowOff();
            }
        },
        error: function() { wifiShowError(); }
    });
}

// the following functions update the display
function wifiShowWait() {
    $("#wifistat").html("<img src='../art/spinner.gif' style='margin-top: -3px;'>");
}

function wifiShowOn() {
    $("#wifistat").html(
        "<span style='color: #3a3; font-weight: bold;'><?php echo $lang['is_on'] ?></span>"
    );
}

function wifiShowOff() {
    $("#wifistat").html(
        "<span style='color: #a33; font-weight: bold;'><?php echo $lang['is_off'] ?></span>"
    );
}

function wifiShowError() {
    $("#wifistat").html("<?php echo $lang['an_error_occurred'] ?>");
}
