<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>title</title>
  </head>
  <body>

<h1>RACHEL Version Info</h1>
<ul>
<li> <?php echo exec("uname -a") ?>
<li> <?php echo passthru("cat /etc/os-release") ?>
<li> RACHEL Install Script Version (pios or cap)
<li> ka-lite version (installed with script)
<li> kiwix version (installed with script)
<li> Contentshell version
<li> list each module and its version
</ul>

  </body>
</html>
