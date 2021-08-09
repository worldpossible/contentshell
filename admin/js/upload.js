
var reader      = null; // File Reader 
var uploadFile  = {}; // The upload file

// Set via server response for use and details
var partSize   = 0;     // Size of upload parts
var partsTotal = 0;     // Total number of parts
var partCount  = 0;     // Number of completed Parts
var token      = "";    // Token for this upload
var loghash    = "";    // Hash for this upload 
var request    = null;  // Upload ajax for cancel 
var progUpdate = null;  // Progress ajax for cancel  

// Client actions 
const actions = {
    none:     0,  // No action 
    validate: 1,  // Validation
    process: 2,   // Process a part
    cancel: 3,    // Cancel the upload 
    install: 4,   // Install the upload 
    pause: 5,     // Pause the upload 
    progress: 6,  // Poll for Progress
    uploads: 7,   // Get existing uploads
}

// Response status codes from server
const statusCode = {
    none:       0,
    uploading:  1, // Uploading file
    paused:     2, // Successfully paused the upload
    installing: 3, // Upload is being installed 
    errored:    4, // An error occurred 
    canceled:   5, // Cancelled the upload 
    processed:  6, // Successfully processed the part file
    uploads:    7, // Code that response contains uploads 
    complete:   8, // Upload complete
    script:     9, // Running Finish script
    verifying:  10 // Verifying the zip 
}

// UI states 
const UIState = {
    selection: "upload_select",
    complete:  "upload_complete",
    details:   "upload_details",  
    error:     "upload_error",    
    install:   "upload_install",  
    paused:    "upload_paused", 
    uploading: "upload_progress",
    canceled:  "upload_canceled",
    canceling: "upload_canceling"
}

var curState   = UIState.selection;
var curStatus  = statusCode.none;
var uploadSize = null;  // Size of the upload for the UI

$( document ).ready(function() {
    // Display any existing uploads
    showUploads();

    $( "#file_form" ).submit(function(e){
        $("#file_form").prop('disabled', true);
        e.preventDefault();
        validate();
    });
    
    $( "#cancelInstall" ).click(function(e) {
        $("#cancelInstall").prop('disabled', true);
        e.preventDefault();
        cancel();
    });
    
    $( "#cancel" ).click(function(e) {
        $("#cancel").prop('disabled', true);
        e.preventDefault();
        cancel();
    });
    
    $( "#pause" ).click(function(e) {
        $("#pause").prop('disabled', true);
        e.preventDefault();
        pause();
    });
    
    $( "#details" ).click(function(e) {
        e.preventDefault();
        toggleDetails();
    });
    
    $( "#showInstallDetails" ).click(function(e) {
        e.preventDefault();
        toggleInstallDetails();
    });   
    
    $( "#openLog" ).click(function(e) {
        e.preventDefault();
        openLog(loghash);
    });
});

// Validate a file. Entry point from form submit  
function validate(){
    var file = document.querySelector( '#upload_file' ).files[0];

    if(file === null){
        alert("Please select a zip file to upload.");
        return false;
    }
    
    if(typeof(file) === "undefined"){
        alert("Please select a zip file to upload.");
        return false;        
    }
    
    if(file.type !== "application/x-zip-compressed" && file.type !== "application/zip"){
        alert("The selected file is not a zip. Please select another.")
        return false;
    }

    var ext = file.name.split('.').pop();

    if(ext !== "zip"){
        alert("The provided file is not a zip");
        return false;
    }

    if (/\s/.test(file.name)){
        alert("The zip name must not contain spaces");
        return;
    }

    if(file.size < 20 ){
        alert("This file is too small to be uploaded.")
        return false;
    }

    if (file.name.length <= 3) {
        alert("The provided file name is too short. ")
        return false;
    }

    if (file.name.length >= 255) {
        alert("The provided file name is too long. ")
        return false;
    }

    // 256KB for the central directory
    var checkSize = 256000;

    if(file.size < 256000){
        checkSize = file.size;
    }
    
    var checkStart = file.size - checkSize;
    reader         = new FileReader(); 
    var blob       = file.slice( checkStart, file.size );
    var formData   = new FormData();
    formData.append('file_cd', blob);
    formData.append('action', actions.validate);
    formData.append('file_name', file.name);
    formData.append('file_size', file.size);
    formData.append('file_type', file.type);

    reader.onloadend = function( event ) {
        if ( event.target.readyState !== FileReader.DONE ) {
            return; 
        }

        request = $.ajax({
                    url:      'upload.php',
                    type:     'POST',
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    timeout: 45000, // 45 second wait for validation.
                    data: formData,
                    error: function( response , textStatus) {
                        // If we hit the timeout we error 
                        if(textStatus === 'timeout'){     
                            showError("The request has timed out during file validation. Please check your connection to the server and try again.");         
                            return;
                        }

                        if(response === null){
                            showError("No response received from server during file validation");
                            return;
                        }

                        if(typeof(response.responseJSON) === "undefined"){
                            showError("Failed to get a JSON response from the server during file validation");
                            return;
                        }
                        
                        if(response.responseJSON === null){
                            showError("Failed to get a JSON response from the server during file validation");
                            return;
                        }

                        data = response.responseJSON;

                        if(data.code == statusCode.paused){
                            return;
                        }

                        if(data.error){
                          showError(data.error);
                          return;  
                        }

                        showError("An unknown error has occurred during file validation");
                        return;
                    },
                    success: function( response ) {
                        if(response === null){
                            showError("Response from server was null during file validation");
                            return;
                        }

                        var code = response.code;

                        if(code === null){
                            showError("Failed to get response code from server during file validation");
                            return;
                        }

                        token = response.token;

                        if(token === "undefined"){
                            showError("Failed to get a proper upload token during file validation.");
                            return;
                        }       
                        
                        loghash    = response.hash;
                        partsDone  = response.parts_done;
                        partsTotal = response.part_total;
                        lastUpdate = response.last_update;
                        partSize   = parseInt(response.part_size);

                        if(partSize === 0 || partSize === null ){
                            showError("Failed to get part size from server during file validation")
                            return;                
                        }

                        start = parseInt(response.start);

                        if(start === null){
                            showError("Failed to get start offset from server during file validation");
                            return;
                        }

                        reader     = new FileReader();
                        uploadFile = file;
                        
                        // Set the size for the UI
                        uploadSize = bytesToSize(file.size); 
                        
                        switchUIState(UIState.uploading);
                        var progaction       = document.getElementById("progaction");
                        progaction.innerHTML = "Uploading " + file.name;
                        showUploads();
                        upload(start, partsDone, lastUpdate);
                        return;
                    }
                });  
    }

    reader.readAsArrayBuffer( blob );
}

// Main upload loop. Start is start offset
function upload( start, partsDone, lastUpdate ){
    // Calculate the next part end point  
    var nextPart = start + partSize;
    
    // Check that the end point isn't larger than the file size 
    if(nextPart > uploadFile.size){
        var remaining = uploadFile.size - start;
        
        if(remaining > uploadFile.size){
            showError("Upload: The server provided an invalid start point");
            return;
        }
        
        nextPart = start + remaining;
    }

    // Data to upload     
    var blob     = uploadFile.slice( start, nextPart );
    var formData = new FormData();

    // Add the data to formData
    formData.append('token', token);
    formData.append('file', blob);
    formData.append('action', actions.process);
    formData.append('parts_done', partsDone);
    formData.append('last_update', lastUpdate);    

    // Post when file read complete 
    reader.onload = function( event ) {
        if ( event.target.readyState !== FileReader.DONE ) {
            return; 
        }

        request = $.ajax( {
            url:         'process.php',
            type:        'POST',
            data:        formData,
            dataType:    'json',
            processData: false,
            contentType: false,
            timeout: 600000, // Ten minutes to upload a part. 
            error: function( response, textStatus ) {
                if(textStatus === 'timeout'){     
                    showError("Upload: The request has timed out. Please check your connection to the server and try again.");         
                    return;
                }

                if (response === null ){
                    showError("Upload: Failed to get an upload response from the server");
                    return;                    
                }

                code = response.code;

                if(code === statusCode.canceled){
                    return;
                }

                if(code === statusCode.paused){
                    return;
                }
                
                if(response.statusText == "abort"){
                    return;
                }
                
                showError("An unknown error has occurred.");
                return;
            },
            success: function( response ) { 
                if(response.code === null){
                    showError("Upload: Failed to get response code from the server");
                    return;
                }

                var code = response.code;

                if(code === statusCode.canceled || code == statusCode.paused){
                    return;
                }

                if(code === statusCode.processed){
                    lastUpdate = response.last_update;
                    partsDone  = parseInt(response.parts_done);
                    start      = parseInt(response.start);

                    if(start === null){
                        showError("Upload: Failed to get start offset from server ");
                        return;
                    }
 
                    setUploadUI(response.percent, 
                                response.speed, 
                                false);

                    setUploadDetails(response);                     
                    upload(start, partsDone, lastUpdate);
                    return;
                }

                if(code === statusCode.complete){
                    setUploadUI(0, 0, true);
                    setTimeout(install, 1000);
                    return;                    
                }

                showError("An unknown error occurred during upload. Please check your connection to the server.");
            }
        });
    };

    reader.readAsArrayBuffer( blob );
}

// Start the installation 
function install(){
    switchUIState(UIState.install);
    curStatus = statusCode.installing;

    request = $.ajax( {
        url:      'upload.php',
        type:     'POST',
        dataType: 'json',
        cache: false,
        timeout: 10000000, // 160 minutes ( Full Wikipedia ) 
        data: { action: actions.install,
                token: token,
        },
        error: function( response, textStatus ) {
            if(curStatus == statusCode.canceled){
                return;
            }

            // the error is too fast 
            if(curState == UIState.canceled){
                return;
            }          
            
            if(textStatus == 'timeout'){
                showError("The install process has timed out ");
                return;
            }
            
            if(response === null){
                showError("No response from server during install");
                return;
            }

            if(typeof(response.responseJSON) === "undefined"){
                showError("Install: Failed to get a json response from the server");
                return;
            }
            
            if(response.responseJSON === null){
                showError("Install: Failed to get a json response from the server");
                return;
            }

            showError(response.responseJSON.error);
        },
        success: function( response ) {
            // Handle late cancelations
            if(response.code == statusCode.none){
                console.log(response.message);
                switchUIState(UIState.canceled);
                progUpdate.abort();
                curStatus = statusCode.canceled;
                return;
            }
            
            progUpdate.abort();
            curStatus = statusCode.complete;
            switchUIState(UIState.complete);
        }
    });

    setInstallMessage("Preparing " + uploadFile.name);   
    installProgress();
}

// Install progress polling
function installProgress(){
    // Check install didn't complete already
    if(curStatus == statusCode.complete){
        return;
    }
    
    if(curState == UIState.complete){
        return;
    }
    
    var name = getFileName(uploadFile.name);

    progUpdate = $.ajax({
            url:      'process.php',
            type:     'GET',
            dataType: 'json',
            data: { name: name,
                    hash: loghash },
            error: function( response, textStatus) {
                if(textStatus === "timeout"){
                    return;
                }
                
                if(response == null){
                    return;
                }
                
                if(response.error === "undefined"){
                    return;
                }         
            },
            success: function( response ) {               
                if(response == null){
                    return;
                }
                
                if(curState == UIState.error){
                    return;
                }

                if(response.code == statusCode.none){
                    // Wait 2 seconds, check again
                    setTimeout(installProgress, 1500);
                    return;
                }
                
                // In case it was not set properly
                if(response.progress == ""){
                    // Wait 2 seconds, check again
                    setTimeout(installProgress, 1500);
                    return;
                }
                
                // To avoid confusion
                // response.code is the code returned by process.php
                // progress.code is set by UpdateProgress during install
                
                var progress = JSON.parse(response.progress);

                // Only update if there's an update 
                setInstallMessage(progress.message);

                var installStatus = document.getElementById("install_status");
                var installInfo   = document.getElementById("install_info");
                
                installStatus.innerHTML = progress.message;
                installInfo.innerHTML   = response.details;

                // Wait 2 seconds, check again
                setTimeout(installProgress, 1500);
            }
        }); 
}

// upload dir until validation 
function openLog(hash){
    var log = "/modules/upload_tmp/logs/" + hash + ".log";
    var win = window.open(log, '_blank');
    win.focus();
}

//  Pause the current upload 
function pause(){
    // Too late to pause 
    if(partCount == partsTotal){
        return;
    }

    // Abort the current request
    request.abort();
    
    $.ajax({
        url: 'upload.php',
        type: 'POST',
        dataType: 'json',
        cache: false,
        timeout: 12000, 
        data: {
            action: actions.pause, 
            token:  token
        },
        error: function( response, textStatus ) {
            if(textStatus === 'timeout'){
                showError("Pause: request has timed out. Please check your connection to the server.");
            }
            
            data = response.responseJSON;

            if(data === null){
                showError("Pause: Failed to get a JSON response from the server");
                return;
            }

            showError(data.error);
            return;
        },
        success: function( response ) {
            switchUIState(UIState.paused);
        }
    });         
}

// Clear a failed upload from showUploads
function clearUpload(up_token){
    $.ajax( {
        url: "upload.php",
        type: 'POST',
        dataType: 'json',
        cache: false,
        timeout: 12000,
        data: { action: actions.cancel,
                token: up_token },
        error: function( response, textStatus ) {
            if(textStatus === 'timeout'){
                showError("Cancel: Request timed out. Please check your connection to the server.");
                return;                
            }

            if(typeof(response.error) === "function"){
                showError("An unknown error has occurred while cancelling the upload. Please check the server logs for more information");
                return;
            }

            if(typeof(response.error) != "undefined"){
               showError(response.error);
               return;               
            }
            
            showError("Cancel: Failed for an unknown reason");
        },
        success: function( ) {
            // Refresh the uploads table          
            showUploads();
        }
    });       
}

// Cancel the current upload
function cancel(){
    // Set to the install ajax error function knows it was canceled
    curStatus = statusCode.canceled;
    
    // Stop the current request if it exists
    if(request){
        request.abort();  
    }
    
    // Stop progress polling if exists
    if(progUpdate){
        progUpdate.abort();
    }

    $.ajax( {
        url: "upload.php",
        type: 'POST',
        dataType: 'json',
        cache: false,
        timeout: 12000,
        data: { action: actions.cancel,
                token: token },
        error: function( response, textStatus ) {
            if(textStatus === 'timeout'){
                showError("Cancel: Request timed out. Please check your connection to the server.");    
            }

            if(typeof(response.error) != "undefined"){
               showError(response.error);
               return;               
            }
            
            showError("Cancel: Failed for an unknown reason");
        },
        success: function( ) {
            switchUIState(UIState.canceled);
        }
    });          
}

function setUploadUI(percent, speed, complete){
    var progbar     = document.getElementById("progbar");
    var progpercent = document.getElementById("progpercent");    

    if(complete){
        progpercent.innerHTML = "100%";                    
        progbar.style.width   = "100%";
        progpercent.style.transition = "none";
        return;
    } 

    progspeed.innerHTML   = speed;
    progpercent.innerHTML = percent + "%"; 
    progbar.style.width   = percent + "%";      
}

// Update the UI with response info  
function setUploadDetails(response){
    var panel = document.getElementById("upload_details");
    
    if(panel.display == "none"){
        return;
    }
    
    var details = "<table><tr><td>Elapsed: </td>";
    details += "<td>" + response.elapsed + "</td></tr>";
    details += "<tr><td>ETA: </td>";
    details += "<td>" + response.time_left + "</td></tr>";
    details += "<tr><td>Transferred: </td>";
    details += "<td>" + bytesToSize(response.transferred) + " of " + uploadSize + "</td></tr>"; 
    details += "<tr><td>Parts Done: </td>";
    details += "<td>" + response.parts_done + " / " + partsTotal + "</td></tr>";
    details += "<tr><td>Part Size: </td>";
    details += "<td>" + bytesToSize(partSize) + "</td></tr>";
    details += "<tr><td>Speed: </td>";
    details += "<td>" + response.speed + "</td></tr></table>";

    panel.innerHTML = details; 
}

function setInstallMessage(msg){
    var uploadInstall = document.getElementById("install_message");
    uploadInstall.innerHTML = msg;     
}

// Show existing uploads for resume/clear 
function showUploads(){

    $.ajax({
        url:      'upload.php',
        type:     'POST',
        dataType: 'json',
        cache: false,
        data: { action: actions.uploads },
        error: function( response ) {
            if(response === null){
                showUploadsError("showUploads: No response received from server");
                return;
            }
            
            if(typeof(response.responseJSON) === "undefined"){
                showUploadsError("showUploads: Failed to get valid response from server");
                return;
            }

            data = response.responseJSON;
            showUploadsError("showUploads: " + data.error);
        },
        success: function( response ) {
            if(response === null){
                showUploadsError("showUploads: Response from server was null")
                return;
            }

            if(typeof(response.uploads) === "undefined"){
                return;
            }
            
            var uploads     = response.uploads;
            var showUploads = document.getElementById("show_uploads");
            
            if (uploads.length == 0){
                showUploads.innerHTML = "";
                return;
            }
            
            if(uploads.length == 1 && uploads[0]['name'] == uploadFile.name){
                showUploads.innerHTML = "";
                return;                
            }
 
            var uploadsHTML  = "<table>";
            uploadsHTML     += "<th>Name</th><th>Size</th><th>Status</th>";
            uploadsHTML     += "<th>Message</th><th>Log</th>";

            for(var i = 0; i < uploads.length; i++ ){

                var up_name = uploads[i]['name'];
                
                if(up_name == uploadFile.name){
                    continue;
                }
                
                var up_size   = uploads[i]['size'];
                var up_status = statusToName(uploads[i]['status']);
                var up_token  = uploads[i]['token'];
                var up_error  = uploads[i]['message'];
                var up_hash   = uploads[i]['hash'];
                uploadsHTML += "<tr><td>" + up_name + "</td>";
                uploadsHTML += "<td>" + bytesToSize(up_size) + "</td>";
                uploadsHTML += "<td>" + up_status + "</td>";              
                
                if(up_error != null){
                    uploadsHTML += "<td><button class=\"upButton\" onclick='alert(\"" + up_error +"\");'>Show Error</button></td>";                    
                } else {
                    uploadsHTML += "<td></td>";
                }

                uploadsHTML += "<td><button class=\"upButton\" onclick='openLog(\"" + up_hash +"\");'>View Log</button></td>";

                uploadsHTML += "<td><button class=\"upButton\"  onclick='clearUpload(\""+up_token+"\");'>Cancel</button></td>";
                uploadsHTML += "</tr>";
            }
            
            uploadsHTML += "</table>";
            showUploads.innerHTML = uploadsHTML;
        }
    });       
}

// Show the error message 
function showError(msg){
    switchUIState(UIState.error);

    var panel  = document.getElementById("upload_info");
    var html   = "<table><tr><td>" + msg + "</td><td>";
    html      += "<td><button class=\"upButton\" onClick=\"window.location.reload();\">Upload Again</button></td><tr></table>";   
    panel.innerHTML = html;
}

// ShowUploads error message 
function showUploadsError(msg){
    document.getElementById("show_uploads").innerHTML = msg;
}

// Toggle the upload details panel 
function toggleInstallDetails(){
    installDetails = document.getElementById("install_details");
    
    if(installDetails.style.display === "none" || installDetails.style.display == ""){
        installDetails.style.display = "block";
        return;
    }
    
    installDetails.style.display = "none";
}

// Toggle the upload details panel 
function toggleDetails(){
    uploadDetails = document.getElementById(UIState.details);
    
    if(uploadDetails.style.display === "none" || uploadDetails.style.display == ""){
        uploadDetails.style.display = "block";
        return;
    }
    
    uploadDetails.style.display = "none";
}

// Switch the user interface state 
function switchUIState(state){

    switch(state){
        case UIState.complete:
            var panel   = document.getElementById("install_message");
            var spinner = document.getElementById("install_spinner");
            spinner.style.display = "none";
            var html  = "<table><tr>";
            name      = getFileName(uploadFile.name);
            html     += "<td style=\"color: green;\">&#10004; " +
                        name + " installed</td>";
            html     += "<td><button class=\"upButton\" onClick=\"window.location.reload(true);\">Upload Again</button></td>"
            html     += "</tr></table>";
            panel.innerHTML = html;
            curState = state;
            curStatus = statusCode.None;
            break;
        case UIState.install:
            var panel = document.getElementById("upload_progress");
            
            if(panel){
                panel.style.display = "none";             
            }    
            
            var newPanel           = document.getElementById("upload_install");
            newPanel.style.display = "block";
        
            var upload_details = document.getElementById("upload_details");

            if(upload_details){
                if(upload_details.style.display == "block"){
                    var install_details = document.getElementById("install_details");
                    install_details.style.display = "block";
                }
            }
            
            curState = state;

            break; 
        case UIState.paused:
            var panel = document.getElementById("upload_progress");
            var html  = "<table><tr><td>Your upload has been paused</td>";
            html     += "<td><button class=\"upButton\" onClick=\"window.location.reload(true);\">Upload Again</button>";
            html     += "</td></tr></table>";
            panel.innerHTML = html;
            curState = state; 
            break;        
        case UIState.canceled:
            // Handle double setting due to late cancelation        
            var uploadPanel  = document.getElementById("upload_progress");
            var installPanel = document.getElementById("upload_install");
            
            if(uploadPanel){
                uploadPanel.style.display = "none";
            }

            if(installPanel){
                installPanel.style.display = "none";
            }  

            var panel = document.getElementById("upload_info");
            
            // break if it's already set 
            if(panel.style.display == "block"){
                break;
            }
            
            panel.style.display = "block";
            
            var html  = "<table><tr><td>Your upload has been canceled</td>";
            html     += "<td><button class=\"upButton\" onClick=\"window.location.reload(true);\">Upload Again</button>";
            html     += "</td></tr></table>";
            panel.innerHTML = html;
            curState        = state; 
            break;
        case UIState.error:
            var panel = document.getElementById(curState);
            
            if(panel){
                panel.style.display = "none";             
            }
            
            var newPanel           = document.getElementById("upload_info");
            newPanel.style.display = "block";
            curState               = state;
            break;
        case UIState.uploading:
            var panel              = document.getElementById("upload_select");
            panel.style.display    = "none";   
            var newPanel           = document.getElementById("upload_progress");
            newPanel.style.display = "block";
            curState               = state; 
            break;       
    }
}

// Convert an UploadStatus to it's name
function statusToName(statusCode){
    switch(statusCode){
        case 0:
            return "none";
        case 1:
            return "uploading";
        case 2:
            return "paused";
        case 3:
            return "installing";
        case 4:
            return "errored";
        case 5:
            return "canceled";
        case 6:
            return "processed";
        case 7:
            return "uploads";
        case 8:
            return "complete";
        case 9:
            return "script";
        case 10:
            return "verifying";          
        default:
            return "unknown";        
        }
}

function getFileName(name){
    return name.split("/").pop().split(".")[0];
}

function bytesToSize(a,b=2){if(0===a)return"0 Bytes";const c=0>b?0:b,d=Math.floor(Math.log(a)/Math.log(1024));return parseFloat((a/Math.pow(1024,d)).toFixed(c))+" "+["Bytes","KB","MB","GB","TB","PB","EB","ZB","YB"][d]}

