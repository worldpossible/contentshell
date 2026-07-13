<!-- version="0.2.1" -->

<?php

$api    = "//$_SERVER[HTTP_HOST]:3000";
$admin  = "//$_SERVER[HTTP_HOST]:3001";
$mail   = "//$_SERVER[HTTP_HOST]:8002/roundcube/";
$config = file_get_contents("http:" . $api);
$up     = false;

if($config){
    if($config != "000000"){
        $up = true;
    }
}

?>

<style>

#datapost_list {
  font-size:16px;
  padding-top:8px;
  margin-top:10px;
}

#datapost_list tr{
  width:100%;
}

#datapost_list td {
  text-align:left;
}

i {
  color:#666;
}
</style>

<div class="indexmodule">
  <!-- logo -->
  <a href="<?php echo $mail; ?>">
    <img src="<?php echo $dir ?>/logo.png" alt="DataPost Webmail">
  </a>

  <!-- Main link -->
  <h2>
    <a href="<?php echo $mail; ?>">DataPost Webmail</a>
  </h2>

  <!-- description -->
  <p>DataPost is a webmail interface for accessing your email account.</p>

</div>
