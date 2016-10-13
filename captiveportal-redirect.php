<?php
    # for multi-lingual support
    require_once("admin/common.php");
?>
<!DOCTYPE html>
<html lang="<?php echo $preflang ?>">
<head>
    <meta charset="utf-8">
    <title><?php echo $lang['welcome_to_rachel'] ?></title>
    <style type="text/css">
        body {
            font-family: sans-serif;
            width: 600px;
            margin: 0 auto;
            text-align: center;
        }
        h2 { margin: 40px; }
        #btn {
            background: #eee;
            border: 1px solid #ccc;
            color: #149;
            padding: 10px;
            border-radius: 5px;
            text-decoration: none;
        }
        #btn:hover {
            background: #fff;
            color: #37c;
        }

    </style>
</head>
<body>

    <h1><a href="http://<?php echo $_SERVER["SERVER_ADDR"]; ?>/index.php" target="_blank"><img src="http://<?php echo $_SERVER["SERVER_ADDR"]; ?>/art/RACHELbrandLogo-captive.png" width="419" height="138"></a></h1>
    <h3><?php echo $lang['worlds_best_edu_cont'] ?><br><?php echo $lang['for_people_wo_int'] ?></h3>

    <h2><a href="http://<?php echo $_SERVER["SERVER_ADDR"]; ?>/index.php" target="_blank" id="btn"><?php echo $lang['click_here_to_start'] ?></a></h2>

    <h4 style="margin-bottom: 0;"><?php echo $lang['brought_to_you_by'] ?>:</h4>
    <a href="http://worldpossible.org/" target="_blank" style="float: left;"><img src="http://<?php echo $_SERVER["SERVER_ADDR"]; ?>/art/World-Possible-Logo-300x120.png" width="300" height="120"></a>
    <a href="http://hackersforcharity.org/" target="_blank" style="float: right; margin-top: 30px;"><img src="http://<?php echo $_SERVER["SERVER_ADDR"]; ?>/art/HFCbrandLogo-captive.jpg" width="286" height="54"></a>

</body>
</html>
