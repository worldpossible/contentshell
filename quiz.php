<?php
/**
 * Student Quiz Interface
 * Allows students to take quizzes created in the admin panel
 */
require_once("admin/common.php");
?>
<!DOCTYPE html>
<html lang="<?php echo $lang['langcode'] ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RACHEL Quizzes</title>
<link rel="stylesheet" href="css/normalize-1.1.3.css">
<link rel="stylesheet" href="css/style.css">
<script src="js/jquery-1.10.2.min.js"></script>
<style>
body {
    background: #f1f5f9;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    margin: 0;
    padding: 20px;
}
.container {
    max-width: 800px;
    margin: 0 auto;
}
.header {
    text-align: center;
    margin-bottom: 30px;
}
.header h1 {
    color: #1e293b;
    margin: 0 0 10px 0;
}
.header p {
    color: #64748b;
    margin: 0;
}
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    padding: 25px;
    margin-bottom: 20px;
}
.quiz-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: all 0.2s;
}
.quiz-list-item:hover {
    border-color: #3b82f6;
    background: #f8fafc;
}
.quiz-list-item .info h3 {
    margin: 0 0 5px 0;
    color: #1e293b;
}
.quiz-list-item .info p {
    margin: 0;
    color: #64748b;
    font-size: 0.9em;
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.2s;
}
.btn-primary {
    background: #3b82f6;
    color: white;
}
.btn-primary:hover {
    background: #2563eb;
}
.btn-secondary {
    background: #64748b;
    color: white;
}
.btn-secondary:hover {
    background: #475569;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #334155;
}
.form-group input[type="text"] {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 16px;
    box-sizing: border-box;
}
.form-group input[type="text"]:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.question-card {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}
.question-card h4 {
    margin: 0 0 15px 0;
    color: #1e293b;
}
.question-card .points {
    color: #64748b;
    font-size: 0.85em;
    font-weight: normal;
}
.option-label {
    display: flex;
    align-items: center;
    padding: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
}
.option-label:hover {
    border-color: #3b82f6;
    background: white;
}
.option-label input[type="radio"] {
    margin-right: 12px;
}
.option-label.selected {
    border-color: #3b82f6;
    background: #eff6ff;
}
textarea.answer-input {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 14px;
    min-height: 100px;
    resize: vertical;
    box-sizing: border-box;
}
.file-upload {
    border: 2px dashed #cbd5e1;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.file-upload:hover {
    border-color: #3b82f6;
    background: #f8fafc;
}
.file-upload input[type="file"] {
    display: none;
}
.file-upload .selected-file {
    margin-top: 10px;
    color: #10b981;
    font-weight: 500;
}
.timer {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #1e293b;
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 1.2em;
}
.timer.warning {
    background: #f59e0b;
}
.timer.danger {
    background: #dc2626;
    animation: pulse 1s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
.result-card {
    text-align: center;
    padding: 40px;
}
.result-card .score {
    font-size: 4em;
    font-weight: 700;
    color: #3b82f6;
    margin-bottom: 10px;
}
.result-card .percentage {
    font-size: 1.5em;
    color: #64748b;
}
.result-card.excellent .score { color: #10b981; }
.result-card.good .score { color: #3b82f6; }
.result-card.needs-work .score { color: #f59e0b; }
.home-link {
    text-align: center;
    margin-top: 30px;
}
.home-link a {
    color: #3b82f6;
    text-decoration: none;
}
.home-link a:hover {
    text-decoration: underline;
}
.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid #e5e7eb;
    border-top-color: #3b82f6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>RACHEL Quizzes</h1>
        <p>Select a quiz to begin</p>
    </div>
    
    <!-- Quiz List View -->
    <div id="quizListView" class="card">
        <div id="quizList">
            <p style="text-align:center;"><span class="spinner"></span> Loading quizzes...</p>
        </div>
    </div>
    
    <!-- Login View -->
    <div id="loginView" class="card" style="display:none;">
        <h2 style="margin-top:0;" id="quizTitleLogin">Quiz</h2>
        <p id="quizDescLogin" style="color:#64748b;"></p>
        
        <form id="loginForm" onsubmit="verifyAndStart(); return false;">
            <div class="form-group">
                <label for="studentName">Your Name *</label>
                <input type="text" id="studentName" required placeholder="Enter your full name">
            </div>
            <div class="form-group">
                <label for="secretKey">Secret Key (optional)</label>
                <input type="text" id="secretKey" placeholder="Enter your assigned secret key if given one">
                <p style="color:#64748b; font-size:0.85em; margin:5px 0 0 0;">If your teacher assigned you a secret key, enter it here to verify your identity.</p>
            </div>
            <div class="form-group" id="quizPasswordGroup" style="display:none;">
                <label for="quizPassword">Quiz Password *</label>
                <input type="password" id="quizPassword" placeholder="Enter the quiz password">
                <p style="color:#64748b; font-size:0.85em; margin:5px 0 0 0;">This quiz is password protected. Ask your teacher for the password.</p>
            </div>
            <div id="passwordError" style="color:#dc2626; margin-bottom:15px; display:none;">Incorrect password. Please try again.</div>
            <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="backToList();">Back</button>
                <button type="submit" class="btn btn-primary">Start Quiz</button>
            </div>
        </form>
    </div>
    
    <!-- Quiz Taking View -->
    <div id="quizView" style="display:none;">
        <div class="card">
            <h2 style="margin-top:0;" id="quizTitleTaking">Quiz</h2>
            <p id="quizDescTaking" style="color:#64748b;"></p>
        </div>
        
        <form id="quizForm" onsubmit="submitQuiz(); return false;" enctype="multipart/form-data">
            <div id="questionsContainer"></div>
            
            <div class="card" style="text-align:center;">
                <button type="submit" class="btn btn-primary" style="padding:15px 40px; font-size:16px;">Submit Quiz</button>
            </div>
        </form>
    </div>
    
    <!-- Results View -->
    <div id="resultsView" class="card" style="display:none;">
        <div id="resultsContent"></div>
    </div>
    
    <div class="home-link">
        <a href="index.php">Back to RACHEL Home</a>
    </div>
</div>

<div id="timerDisplay" class="timer" style="display:none;"></div>

<script>
var currentQuiz = null;
var quizTimer = null;
var timeRemaining = 0;

$(function() {
    loadQuizList();
});

function loadQuizList() {
    $.ajax({
        url: 'admin/background.php?getQuizzes=1',
        dataType: 'json',
        success: function(quizzes) {
            if (!quizzes || quizzes.length === 0) {
                $('#quizList').html('<p style="text-align:center; color:#64748b;">No quizzes available at this time.</p>');
                return;
            }
            
            var html = '';
            var activeCount = 0;
            quizzes.forEach(function(quiz) {
                if (quiz.is_active != 1) return;
                activeCount++;
                
                var timeInfo = quiz.time_limit > 0 ? quiz.time_limit + ' min time limit' : 'No time limit';
                html += '<div class="quiz-list-item">';
                html += '<div class="info">';
                html += '<h3>' + escapeHtml(quiz.title) + '</h3>';
                html += '<p>' + quiz.question_count + ' questions | ' + timeInfo + '</p>';
                if (quiz.description) {
                    html += '<p style="margin-top:5px;">' + escapeHtml(quiz.description) + '</p>';
                }
                html += '</div>';
                html += '<button class="btn btn-primary" onclick="selectQuiz(' + quiz.quiz_id + ')">Take Quiz</button>';
                html += '</div>';
            });
            
            if (activeCount === 0) {
                html = '<p style="text-align:center; color:#64748b;">No quizzes available at this time.</p>';
            }
            
            $('#quizList').html(html);
        },
        error: function() {
            $('#quizList').html('<p style="text-align:center; color:#dc2626;">Failed to load quizzes. Please try again.</p>');
        }
    });
}

function selectQuiz(quizId) {
    $.ajax({
        url: 'admin/background.php?startQuiz=' + quizId,
        dataType: 'json',
        success: function(quiz) {
            currentQuiz = quiz;
            $('#quizTitleLogin').text(quiz.title);
            $('#quizDescLogin').text(quiz.description || '');
            $('#passwordError').hide();
            $('#quizPassword').val('');
            $('#secretKey').val('');
            
            // Show password field if quiz is password protected
            if (quiz.has_password) {
                $('#quizPasswordGroup').show();
                $('#quizPassword').prop('required', true);
            } else {
                $('#quizPasswordGroup').hide();
                $('#quizPassword').prop('required', false);
            }
            
            $('#quizListView').hide();
            $('#loginView').show();
        },
        error: function() {
            alert('Failed to load quiz. Please try again.');
        }
    });
}

function verifyAndStart() {
    if (!currentQuiz) return;
    
    // If quiz has password, verify it first
    if (currentQuiz.has_password) {
        var password = $('#quizPassword').val();
        $.ajax({
            url: 'admin/background.php?checkQuizPassword=' + currentQuiz.quiz_id + '&password=' + encodeURIComponent(password),
            dataType: 'json',
            success: function(response) {
                if (response.valid) {
                    $('#passwordError').hide();
                    startQuiz();
                } else {
                    $('#passwordError').show();
                }
            },
            error: function() {
                alert('Failed to verify password. Please try again.');
            }
        });
    } else {
        startQuiz();
    }
}

function backToList() {
    currentQuiz = null;
    $('#loginView').hide();
    $('#quizView').hide();
    $('#resultsView').hide();
    $('#quizListView').show();
    $('#timerDisplay').hide();
    if (quizTimer) {
        clearInterval(quizTimer);
        quizTimer = null;
    }
}

function startQuiz() {
    if (!currentQuiz) return;
    
    $('#quizTitleTaking').text(currentQuiz.title);
    $('#quizDescTaking').text(currentQuiz.description || '');
    
    // Render questions
    var html = '';
    currentQuiz.questions.forEach(function(q, i) {
        html += '<div class="card question-card" data-question-id="' + q.question_id + '">';
        html += '<h4>Question ' + (i+1) + ' <span class="points">(' + q.points + ' point' + (q.points > 1 ? 's' : '') + ')</span></h4>';
        html += '<p style="margin-bottom:15px;">' + escapeHtml(q.question_text) + '</p>';
        
        // Display attached media if present
        if (q.media_url && q.media_type) {
            html += '<div class="question-media" style="margin-bottom:20px; text-align:center;">';
            if (q.media_type === 'image') {
                html += '<img src="' + escapeHtml(q.media_url) + '" alt="Question media" style="max-width:100%; max-height:400px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
            } else if (q.media_type === 'video') {
                html += '<video controls style="max-width:100%; max-height:400px; border-radius:8px;"><source src="' + escapeHtml(q.media_url) + '">Your browser does not support video.</video>';
            } else if (q.media_type === 'audio') {
                html += '<audio controls style="width:100%; max-width:400px;"><source src="' + escapeHtml(q.media_url) + '">Your browser does not support audio.</audio>';
            } else if (q.media_type === 'pdf') {
                html += '<div style="background:#f1f5f9; padding:15px; border-radius:8px;">';
                html += '<a href="' + escapeHtml(q.media_url) + '" target="_blank" style="color:#2563eb; text-decoration:none; font-weight:500;">📄 View PDF Document</a>';
                html += '<iframe src="' + escapeHtml(q.media_url) + '" style="width:100%; height:400px; border:1px solid #e2e8f0; border-radius:4px; margin-top:10px;"></iframe>';
                html += '</div>';
            } else {
                html += '<a href="' + escapeHtml(q.media_url) + '" target="_blank" class="btn" style="display:inline-block;">📎 View Attached File</a>';
            }
            html += '</div>';
        }
        
        if (q.question_type === 'multiple_choice' && q.options) {
            q.options.forEach(function(opt, j) {
                if (!opt) return;
                html += '<label class="option-label" onclick="selectOption(this)">';
                html += '<input type="radio" name="q_' + q.question_id + '" value="' + escapeHtml(opt) + '">';
                html += '<span>' + escapeHtml(opt) + '</span>';
                html += '</label>';
            });
        } else if (q.question_type === 'short_answer') {
            html += '<textarea class="answer-input" name="q_' + q.question_id + '" placeholder="Type your answer here..."></textarea>';
        } else if (q.question_type === 'file_upload') {
            html += '<div class="file-upload" onclick="$(this).find(\'input\').click();">';
            html += '<input type="file" name="file_' + q.question_id + '" onchange="showFileName(this)">';
            html += '<p style="margin:0;">Click to upload a file</p>';
            html += '<p class="selected-file"></p>';
            html += '</div>';
        }
        
        html += '</div>';
    });
    
    $('#questionsContainer').html(html);
    
    // Start timer if needed
    if (currentQuiz.time_limit > 0) {
        timeRemaining = currentQuiz.time_limit * 60;
        updateTimerDisplay();
        $('#timerDisplay').show();
        quizTimer = setInterval(function() {
            timeRemaining--;
            updateTimerDisplay();
            if (timeRemaining <= 0) {
                clearInterval(quizTimer);
                alert('Time is up! Your quiz will be submitted now.');
                submitQuiz();
            }
        }, 1000);
    }
    
    $('#loginView').hide();
    $('#quizView').show();
}

function updateTimerDisplay() {
    var mins = Math.floor(timeRemaining / 60);
    var secs = timeRemaining % 60;
    var display = mins + ':' + (secs < 10 ? '0' : '') + secs;
    
    $('#timerDisplay').text(display);
    
    if (timeRemaining <= 60) {
        $('#timerDisplay').removeClass('warning').addClass('danger');
    } else if (timeRemaining <= 300) {
        $('#timerDisplay').removeClass('danger').addClass('warning');
    } else {
        $('#timerDisplay').removeClass('warning danger');
    }
}

function selectOption(label) {
    $(label).siblings('.option-label').removeClass('selected');
    $(label).addClass('selected');
}

function showFileName(input) {
    var fileName = input.files[0] ? input.files[0].name : '';
    $(input).siblings('.selected-file').text(fileName);
}

function submitQuiz() {
    if (quizTimer) {
        clearInterval(quizTimer);
        quizTimer = null;
    }
    $('#timerDisplay').hide();
    
    if (!confirm('Are you sure you want to submit your quiz?')) {
        return false;
    }
    
    // Collect answers
    var answers = [];
    currentQuiz.questions.forEach(function(q) {
        var answer = {
            question_id: q.question_id,
            answer: ''
        };
        
        if (q.question_type === 'multiple_choice') {
            answer.answer = $('input[name="q_' + q.question_id + '"]:checked').val() || '';
        } else if (q.question_type === 'short_answer') {
            answer.answer = $('textarea[name="q_' + q.question_id + '"]').val() || '';
        }
        // File uploads handled separately
        
        answers.push(answer);
    });
    
    // Build form data
    var formData = new FormData();
    formData.append('quiz_id', currentQuiz.quiz_id);
    formData.append('student_name', $('#studentName').val());
    formData.append('secret_key', $('#secretKey').val());
    formData.append('answers', JSON.stringify(answers));
    
    // Add file uploads
    currentQuiz.questions.forEach(function(q) {
        if (q.question_type === 'file_upload') {
            var fileInput = $('input[name="file_' + q.question_id + '"]')[0];
            if (fileInput && fileInput.files[0]) {
                formData.append('file_' + q.question_id, fileInput.files[0]);
            }
        }
    });
    
    // Submit
    $('#quizView').html('<div class="card" style="text-align:center;"><p><span class="spinner"></span> Submitting your quiz...</p></div>');
    
    $.ajax({
        url: 'admin/background.php?submitQuiz=1',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            showResults(response);
        },
        error: function() {
            $('#quizView').html('<div class="card" style="text-align:center;"><p style="color:#dc2626;">Failed to submit quiz. Please try again.</p><button class="btn btn-secondary" onclick="backToList()">Back to Quizzes</button></div>');
        }
    });
    
    return false;
}

function showResults(response) {
    var html = '<div class="result-card';
    
    // Check if there are multiple choice questions that were auto-graded
    if (response.mc_max_score !== undefined && response.mc_max_score > 0) {
        var pct = response.mc_percentage || 0;
        if (pct >= 80) html += ' excellent';
        else if (pct >= 60) html += ' good';
        else html += ' needs-work';
        
        html += '">';
        html += '<h2 style="margin-top:0;">Quiz Submitted!</h2>';
        
        if (response.has_manual_questions) {
            // Mixed quiz - show MC score and note about manual grading
            html += '<p style="font-size:1.1em; margin-bottom:15px;">Multiple Choice Score:</p>';
            html += '<div class="score">' + response.mc_score + '/' + response.mc_max_score + '</div>';
            html += '<div class="percentage">' + pct + '%</div>';
            html += '<p style="margin-top:20px; color:#64748b; background:#fef3c7; padding:12px; border-radius:8px;">';
            html += '<strong>Note:</strong> Your short answer and file upload responses will be reviewed and graded by your teacher.</p>';
        } else {
            // All MC quiz - show full score
            html += '<div class="score">' + response.mc_score + '/' + response.mc_max_score + '</div>';
            html += '<div class="percentage">' + pct + '%</div>';
            html += '<p style="margin-top:20px; color:#64748b;">Your responses have been recorded.</p>';
        }
    } else if (response.has_manual_questions) {
        // No MC questions, all manual grading needed
        html += '">';
        html += '<h2 style="margin-top:0;">Quiz Submitted!</h2>';
        html += '<p style="font-size:1.2em;">Thank you for completing the quiz.</p>';
        html += '<p style="color:#64748b;">Your responses have been recorded and will be graded by your teacher.</p>';
    } else {
        // Fallback
        html += '">';
        html += '<h2 style="margin-top:0;">Quiz Submitted!</h2>';
        html += '<p style="font-size:1.2em;">Thank you for completing the quiz.</p>';
        html += '<p style="color:#64748b;">Your responses have been recorded.</p>';
    }
    
    html += '<button class="btn btn-primary" onclick="backToList();" style="margin-top:20px;">Back to Quizzes</button>';
    html += '</div>';
    
    $('#quizView').hide();
    $('#resultsContent').html(html);
    $('#resultsView').show();
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

</body>
</html>
