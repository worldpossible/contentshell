<?php 
require_once("admin/common.php"); 

// Check World Possible attribution for enhanced features
$wp_features_enabled = wp_verify_attribution();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang['langcode'] ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>RACHEL - <?php echo $lang['home'] ?></title>
<link rel="stylesheet" href="css/normalize-1.1.3.css">
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<script src="js/jquery-1.10.2.min.js"></script>
<script src="js/jquery-ui-1.10.4.custom.min.js"></script>
<!-- I know this is not ideal UI, but it is based on real-world issues:
     because we can't provide navigation back to the home page (like on
     kalite and kiwix), it is difficult for users to find the front page
     again. This keeps it open in the background. We tried opening all
     content in a *single* seperate window named "content" but then
     going back to the main tab and clicking a second subject without
     closing the first tab resulted in no apparent action (though the
     "content" tab did in fact load the requested info in the background).
     The end result of all this is that we decided the best choice of
     lousy choices was to open everything in a new window/tab. Thus:
-->
<base target="_blank">
<style>
/* Sidebar Styles */
#sidebarToggle {
    position: fixed;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1001;
    background: #1e40af;
    color: white;
    border: none;
    padding: 15px 8px;
    border-radius: 0 8px 8px 0;
    cursor: pointer;
    font-size: 18px;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    transition: all 0.3s;
}
#sidebarToggle:hover {
    background: #1e3a8a;
    padding-left: 12px;
}
#sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 280px;
    height: 100vh;
    background: #1e293b;
    z-index: 1000;
    transform: translateX(-100%);
    transition: transform 0.3s ease;
    overflow-y: auto;
    box-shadow: 4px 0 20px rgba(0,0,0,0.15);
}
#sidebar.open {
    transform: translateX(0);
}
#sidebar.open + #sidebarToggle {
    left: 280px;
}
.sidebar-header {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    color: white;
    padding: 20px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.sidebar-header h2 {
    margin: 0;
    font-size: 1.1em;
    font-weight: 600;
}
.sidebar-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.sidebar-close:hover {
    background: rgba(255,255,255,0.3);
}
.sidebar-section {
    padding: 10px 0;
    border-bottom: 1px solid #334155;
}
.sidebar-section-title {
    color: #94a3b8;
    font-size: 0.75em;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 8px 15px;
    margin: 0;
}
.sidebar-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    color: #e2e8f0;
    text-decoration: none;
    transition: all 0.2s;
    gap: 12px;
    font-size: 0.95em;
}
.sidebar-item:hover {
    background: #334155;
    color: white;
}
.sidebar-item.active {
    background: #3b82f6;
    color: white;
}
.sidebar-cat-btn.active {
    background: #3b82f6;
    color: white;
    border-radius: 6px;
}
.sidebar-item .icon {
    font-size: 1.2em;
    width: 24px;
    text-align: center;
}
.sidebar-submenu {
    background: #0f172a;
}
.sidebar-submenu .sidebar-item {
    padding-left: 51px;
    font-size: 0.9em;
    color: #94a3b8;
}
.sidebar-submenu .sidebar-item:hover {
    color: white;
    background: #1e293b;
}
.sidebar-expandable > .sidebar-item {
    cursor: pointer;
}
.sidebar-expandable > .sidebar-item::after {
    content: '▶';
    margin-left: auto;
    font-size: 0.7em;
    transition: transform 0.2s;
}
.sidebar-expandable.expanded > .sidebar-item::after {
    transform: rotate(90deg);
}
.sidebar-expandable .sidebar-submenu {
    display: none;
}
.sidebar-expandable.expanded .sidebar-submenu {
    display: block;
}
.sidebar-footer {
    padding: 15px;
    border-top: 1px solid #334155;
    margin-top: auto;
}
.sidebar-pref {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    font-size: 0.85em;
    cursor: pointer;
}
.sidebar-pref input {
    cursor: pointer;
}
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 999;
    display: none;
}
.sidebar-overlay.show {
    display: block;
}
/* Adjust main content when sidebar preferences change */
body.sidebar-always-open {
    margin-left: 280px;
}
body.sidebar-always-open #sidebarToggle {
    display: none;
}
body.sidebar-always-open #sidebar {
    transform: translateX(0);
}
body.sidebar-always-open .sidebar-overlay {
    display: none !important;
}
@media (max-width: 768px) {
    body.sidebar-always-open {
        margin-left: 0;
    }
    body.sidebar-always-open #sidebarToggle {
        display: block;
    }
    body.sidebar-always-open #sidebar {
        transform: translateX(-100%);
    }
}
</style>
</head>

<body>
<?php include_once("panic-banner.php"); ?>

<?php $categoryData = array('categories' => array(), 'modules' => array()); ?>

<?php if ($wp_features_enabled): ?>
<!-- Sidebar Overlay - World Possible Enhanced Feature -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar();"></div>

<!-- Sidebar Toggle Button -->
<button id="sidebarToggle" onclick="toggleSidebar();" title="Open Menu">☰</button>

<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-header">
        <h2>⚙️ RACHEL Menu</h2>
        <button class="sidebar-close" onclick="toggleSidebar();">×</button>
    </div>
    
    <div class="sidebar-section">
        <p class="sidebar-section-title">Navigation</p>
        <a href="index.php" target="_self" class="sidebar-item active">
            <span class="icon">🏠</span> Home
        </a>
        <a href="about.html" target="_self" class="sidebar-item">
            <span class="icon">ℹ️</span> About RACHEL
        </a>
        <a href="http://<?php echo $_SERVER['SERVER_ADDR']; ?>:8002/roundcube" target="_blank" class="sidebar-item">
            <span class="icon">📧</span> Datapost Email
        </a>
        <a href="quiz.php" target="_self" class="sidebar-item">
            <span class="icon">📝</span> Take Quizzes
        </a>
    </div>
    
    <?php
    // Load category data for sidebar
    $categoryData = array('categories' => array(), 'modules' => array());
    $categoryMapFile = __DIR__ . '/admin/category-map.json';
    if (file_exists($categoryMapFile)) {
        $mapJson = file_get_contents($categoryMapFile);
        $mapData = json_decode($mapJson, true);
        if ($mapData) {
            $categoryData['categories'] = isset($mapData['categories']) ? $mapData['categories'] : array();
            $categoryData['modules'] = isset($mapData['modules']) ? $mapData['modules'] : array();
        }
    }
    $db = getdb();
    if ($db) {
        try {
            $result = $db->query("SELECT moddir, categories FROM module_categories");
            if ($result) {
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $cats = json_decode($row['categories'], true);
                    if ($cats) {
                        $categoryData['modules'][$row['moddir']] = $cats;
                    }
                }
            }
        } catch (Exception $e) {}
    }
    // Only show categories that have installed modules
    $installedMods = array();
    $modDir = __DIR__ . '/modules';
    if (is_dir($modDir)) {
        foreach (scandir($modDir) as $d) {
            if ($d !== '.' && $d !== '..') $installedMods[] = $d;
        }
    }
    $activeCategories = array();
    foreach ($installedMods as $mod) {
        if (isset($categoryData['modules'][$mod])) {
            foreach ($categoryData['modules'][$mod] as $cat) {
                $activeCategories[$cat] = true;
            }
        }
    }
    ?>
    <?php if (!empty($categoryData['categories']) && !empty($activeCategories)): ?>
    <div class="sidebar-section">
        <p class="sidebar-section-title">Filter by Category</p>
        <a href="#" class="sidebar-item sidebar-cat-btn active" data-category="all" onclick="filterByCategory('all'); return false;">
            <span class="icon">📚</span> All Modules
        </a>
        <?php foreach ($categoryData['categories'] as $catId => $catInfo):
            if (!isset($activeCategories[$catId])) continue;
            $catIcon = is_array($catInfo) ? ($catInfo['icon'] ?? '') : '';
            $catLabel = is_array($catInfo) ? ($catInfo['label'] ?? $catId) : $catInfo;
        ?>
        <a href="#" class="sidebar-item sidebar-cat-btn" data-category="<?php echo htmlspecialchars($catId); ?>" onclick="filterByCategory('<?php echo htmlspecialchars($catId); ?>'); return false;">
            <span class="icon"><?php echo htmlspecialchars($catIcon); ?></span> <?php echo htmlspecialchars($catLabel); ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="sidebar-section">
        <p class="sidebar-section-title">Administration</p>
        <div class="sidebar-expandable" id="contentMenu">
            <div class="sidebar-item" onclick="toggleSubmenu('contentMenu');">
                <span class="icon">📚</span> Content Management
            </div>
            <div class="sidebar-submenu">
                <a href="admin/storage.php#upload" target="_self" class="sidebar-item">📤 Upload Content</a>
                <a href="admin/storage.php#usb" target="_self" class="sidebar-item">💾 USB Install</a>
                <a href="admin/storage.php#builder" target="_self" class="sidebar-item">🔨 Build Module</a>
                <a href="admin/storage.php#worldpossible" target="_self" class="sidebar-item">🌍 World Possible</a>
                <a href="admin/storage.php#delete" target="_self" class="sidebar-item">🗑️ Delete Modules</a>
                <a href="admin/storage.php#rearrange" target="_self" class="sidebar-item">↕️ Rearrange</a>
                <a href="admin/storage.php#hide" target="_self" class="sidebar-item">👁️ Visibility</a>
                <a href="admin/storage.php#categories" target="_self" class="sidebar-item">🏷️ Categories</a>
                <a href="admin/storage.php#quizzes" target="_self" class="sidebar-item">📋 Manage Quizzes</a>
                <a href="admin/storage.php#results" target="_self" class="sidebar-item">📊 Quiz Results</a>
            </div>
        </div>
    </div>
    
    <div class="sidebar-section">
        <div class="sidebar-expandable" id="deviceMenu">
            <div class="sidebar-item" onclick="toggleSubmenu('deviceMenu');">
                <span class="icon">🔧</span> Device Settings
            </div>
            <div class="sidebar-submenu">
                <a href="admin/device.php#password" target="_self" class="sidebar-item">🔐 Admin Password</a>
                <a href="admin/device.php#system" target="_self" class="sidebar-item">💻 System Info</a>
                <a href="admin/device.php#storage" target="_self" class="sidebar-item">💽 Storage</a>
                <?php if (is_rachelplus()) { ?>
                <a href="admin/device.php#wifi" target="_self" class="sidebar-item">📶 WiFi Settings</a>
                <?php } ?>
                <a href="admin/device.php#power" target="_self" class="sidebar-item">⚡ Power Options</a>
                <a href="admin/device.php#sponsor" target="_self" class="sidebar-item">🎗️ Sponsorship</a>
                <a href="admin/device.php#services" target="_self" class="sidebar-item">🔗 Other Services</a>
                <?php if (is_rachelplus()) { ?>
                <a href="admin/device.php#advanced" target="_self" class="sidebar-item">🛠️ Advanced</a>
                <?php } ?>
            </div>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <label class="sidebar-pref">
            <input type="checkbox" id="alwaysShowSidebar" onchange="toggleAlwaysShow();">
            Keep menu always visible
        </label>
    </div>
</nav>
<?php endif; // End World Possible enhanced features (sidebar) ?>

<header id="rachel">
    <a href="index.php" target="_self">
        <img src="art/rachel_banner.jpg" alt="RACHEL - Remote Area Community Hotspot for Education and Learning" class="rachel-banner">
    </a>
    
    <!-- Why show IP here? Some installations have WiFi and Ethernet, and
         maybe you're on one but need to know the other. Also helps if my.content
         isn't working on some client devices. Also nice for when you need to ssh
         or rsync. It's visible in the Admin panel too, but it's more convenient here. -->
    <div id="ip">
        <?php showip();
        # on the RACHEL-Plus we also show a battery meter
        # XXX abstract this and the admin one into one piece of code
        if (is_rachelplus()) {
            echo '
                <script>
                    refreshRate = 1000 * 60 * 10; // ten minutes on front page, be very conservative
                    function getBatteryInfo() {
                        $.ajax({
                            url: "admin/background.php?getBatteryInfo=1",
                            success: function(results) {
                                //console.log(results);
                                var vert = 0; // shows full charge (each icon down 12px)
                                if      (results.level < 20) { vert = -48; }
                                else if (results.level < 40) { vert = -36; }
                                else if (results.level < 60) { vert = -24; }
                                else if (results.level < 80) { vert = -12; }
                                var horz = 0; // 0 shows discharging, 40px offset shows charging
                                if (results.status == "charging" ) { horz = 40 }
                                $("#battery").css({
                                    background: "url(\'art/battery-level-sprite-light.png\')",
                                    backgroundPosition: horz+"px "+vert+"px",
                                });
                                $("#battery").prop("title", results.level + "%");
                            },
                            complete: function() {
                                setTimeout(getBatteryInfo, refreshRate);
                            }
                        });
                    }
                    $(getBatteryInfo); // onload
                </script>
                <br><b>Battery</b>: <div id="battery"></div><span id="perc"></span>
            ';
        }
        ?>
    </div>
</header>

<?php if (!$wp_features_enabled): ?>
<!-- Show old menubar only if sidebar is not available -->
<nav class="menubar cf">
    <ul>
    <li><a href="index.php" target="_self" class="active"><?php echo strtoupper($lang['home']) ?></a></li>
    <li><a href="about.html" target="_self"><?php echo strtoupper($lang['about']) ?></a></li>
    <?php
echo "<li><a href=\"http://$_SERVER[SERVER_ADDR]:8002/roundcube\" target=\"_blank\">DATAPOST</a></li>";
echo "<li><a href=\"admin/storage.php\" target=\"_self\">CONTENT MANAGEMENT</a></li>";
    ?>
    </ul>
</nav>
<?php endif; ?>

<main id="content">

<?php

    $modcount = 0;

    $fsmods = getmods_fs();

    # if there were any modules found in the filesystem
    if ($fsmods) {

        # get a list from the databases (where the sorting
        # and visibility is stored)
        $dbmods = getmods_db();

        # populate the module list from the filesystem 
        # with the visibility/sorting info from the database
        foreach (array_keys($dbmods) as $moddir) {
            if (isset($fsmods[$moddir])) {
                $fsmods[$moddir]['position'] = $dbmods[$moddir]['position'];
                $fsmods[$moddir]['hidden'] = $dbmods[$moddir]['hidden'];
            }
        }

        # custom sorting function in common.php
        uasort($fsmods, 'bypos');

        # whether or not we were able to get anything
        # from the DB, we show what we found in the filesystem
        foreach (array_values($fsmods) as $mod) {
            if ($mod['hidden'] || !$mod['fragment']) { continue; }
            $dir  = $mod['dir'];
            $moddir  = $mod['moddir'];
            
            // Get categories for this module
            $modCategories = isset($categoryData['modules'][$moddir]) ? $categoryData['modules'][$moddir] : array();
            $categoryAttr = htmlspecialchars(json_encode($modCategories));
            
            // Wrap module in a filterable container
            echo "<div class=\"module-wrapper\" data-moddir=\"" . htmlspecialchars($moddir) . "\" data-categories='{$categoryAttr}'>";
            include $mod['fragment'];
            echo "</div>";
            ++$modcount;
        }

    }

    if ($modcount == 0) {
        echo '<div class="alert alert-info">' . $lang['no_mods_error'] . '</div>';
    }

?>

</main>

<footer class="menubar cf" style="margin-bottom: 80px;">
    <ul>
    <li><a href="index.php" target="_self"><?php echo strtoupper($lang['home']) ?></a></li>
    <li><a href="about.html" target="_self"><?php echo strtoupper($lang['about']) ?></a></li>
    </ul>
</footer>

<?php if ($wp_features_enabled): ?>
<script>
// Sidebar and Category functionality - World Possible Enhanced Features
var PREF_KEY = 'rachel_sidebar_always_open';
var CATEGORY_PREF = 'rachel_category_filter';

function toggleSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (!sidebar || !overlay) return;
    var isOpen = sidebar.classList.contains('open');
    
    if (isOpen) {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
    } else {
        sidebar.classList.add('open');
        overlay.classList.add('show');
    }
}

function toggleSubmenu(menuId) {
    var menu = document.getElementById(menuId);
    if (menu) menu.classList.toggle('expanded');
}

function toggleAlwaysShow() {
    var checkbox = document.getElementById('alwaysShowSidebar');
    if (!checkbox) return;
    var checked = checkbox.checked;
    localStorage.setItem(PREF_KEY, checked ? 'true' : 'false');
    
    if (checked) {
        document.body.classList.add('sidebar-always-open');
        document.getElementById('sidebar').classList.add('open');
        document.getElementById('sidebarOverlay').classList.remove('show');
    } else {
        document.body.classList.remove('sidebar-always-open');
    }
}

// Category filter functionality
function filterByCategory(category) {
    // Update active button
    document.querySelectorAll('.sidebar-cat-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    var activeBtn = document.querySelector('.sidebar-cat-btn[data-category="' + category + '"]');
    if (activeBtn) activeBtn.classList.add('active');
    
    // Save preference
    localStorage.setItem(CATEGORY_PREF, category);
    
    // Filter modules
    var modules = document.querySelectorAll('.module-wrapper');
    var visibleCount = 0;
    
    modules.forEach(function(wrapper) {
        if (category === 'all') {
            wrapper.style.display = '';
            visibleCount++;
        } else {
            try {
                var cats = JSON.parse(wrapper.getAttribute('data-categories') || '[]');
                if (cats.indexOf(category) !== -1) {
                    wrapper.style.display = '';
                    visibleCount++;
                } else {
                    wrapper.style.display = 'none';
                }
            } catch(e) {
                wrapper.style.display = 'none';
            }
        }
    });
    
    // Show message if no modules match
    var noModsMsg = document.getElementById('noModulesMessage');
    if (visibleCount === 0 && category !== 'all') {
        if (!noModsMsg) {
            noModsMsg = document.createElement('div');
            noModsMsg.id = 'noModulesMessage';
            noModsMsg.className = 'alert alert-info';
            noModsMsg.style.textAlign = 'center';
            noModsMsg.style.padding = '40px';
            noModsMsg.innerHTML = 'No modules found in this category. <a href="#" onclick="filterByCategory(\'all\'); return false;">Show all modules</a>';
            document.getElementById('content').appendChild(noModsMsg);
        }
        noModsMsg.style.display = 'block';
    } else if (noModsMsg) {
        noModsMsg.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    var checkbox = document.getElementById('alwaysShowSidebar');
    var sidebar = document.getElementById('sidebar');
    
    if (checkbox && sidebar) {
        var alwaysOpen = localStorage.getItem(PREF_KEY) === 'true';
        checkbox.checked = alwaysOpen;
        
        if (alwaysOpen) {
            document.body.classList.add('sidebar-always-open');
            sidebar.classList.add('open');
        }
    }
    
    // Restore category filter preference
    var savedCategory = localStorage.getItem(CATEGORY_PREF);
    if (savedCategory && savedCategory !== 'all') {
        var btn = document.querySelector('.sidebar-cat-btn[data-category="' + savedCategory + '"]');
        if (btn) {
            filterByCategory(savedCategory);
        }
    }
});

// Close sidebar with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('open') && !document.body.classList.contains('sidebar-always-open')) {
            toggleSidebar();
        }
    }
});
</script>
<?php endif; // End World Possible enhanced features JS ?>

</body>
</html>
