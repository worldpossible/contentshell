<!DOCTYPE html>
<html lang="<?php echo $lang['langcode']; ?>">
  <head>
    <meta charset="utf-8">
    <title>RACHEL<?php if ($page_title) { echo " $page_title"; } ?></title>
    <link rel="stylesheet" href="../css/normalize-1.1.3.css">
    <link rel="stylesheet" href="../css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="../js/jquery-1.10.2.min.js"></script>
    <script src="../js/jquery-ui-1.10.4.custom.min.js"></script>
    <!-- the page script needs to be included via PHP not HTML because
         there are $lang substitutions inside the js code -->
    <script>
<?php if ($page_script) { include "$page_script"; } ?>
    </script>
  </head>
<body>

<?php include "nav.php" ?>

<div id="content">
