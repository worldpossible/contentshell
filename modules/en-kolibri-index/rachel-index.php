<!-- version="2021.01" -->

<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
?>

<style>

    .channel-list-item {
        position: relative;
        min-height: 85px;
    }

    .indexmodule img.channel-thumbnail {
        position: absolute;
        left: 0px;
        top: 5px;
        width: 50px;
    }

    .channel-title {
        font-weight: bold;
        font-size: 120%;
        margin: 5px 0 2px 62px;
        display: inline-block;

    }

    .channel-description {
        padding: 2px 22px 15px 0px;
        display: inline-block;
        margin-left: 62px;
    }

    .kolibri-intro {
        padding: 5px 0;
    }

    .kolibri-error {
        padding: 8px 0;
        color: darkred;
        font-style: italic;
        margin-left: 130px;
    }

</style>

<div class="indexmodule">

    <?php $host = "//$_SERVER[HTTP_HOST]:9090"; ?>
    <?php
        $available = true;
        $provisioned = true;
        $channels = [];
        $channeldata = file_get_contents('http://127.0.0.1:9090/api/content/channel/?available=true&format=json');
        if($channeldata === FALSE) {
            $error = error_get_last()['message'];
            if (strpos($error, "Connection refused") !== false) {
                $available = false;
            }
        } elseif (strpos($channeldata, '<meta http-equiv="refresh"') !== false) {
            $available = false;
            $provisioned = false;
        } else {
            $channels = json_decode($channeldata, true);
        }
    ?>


    <form onsubmit="window.open('<?php echo $host ?>/learn/#/search?query=' + this.query.value, '_blank'); event.preventDefault();">
        <div>
        <input <?php if (!$available) { echo 'disabled'; } ?> name="query">
        <input <?php if (!$available) { echo 'disabled'; } ?> type="submit" value="Search">
        </div>
    </form>

    <?php if ($available) { echo "<a href='$host/'>"; } ?>
        <img src="<?php echo $dir ?>/kolibri-logo.svg" alt="Kolibri">
    <?php if ($available) { echo '</a>'; } ?>
    <h2>
        <?php if ($available) { echo "<a href='$host/'>"; } ?>
            Kolibri
        <?php if ($available) { echo '</a>'; } ?>
    </h2>

    <p class="kolibri-intro">
        Kolibri is an open-source offline ed-tech platform developed by Learning Equality, focused on increasing the availability of relevant
        and aligned learning materials, fostering innovative pedagogy, and boosting learning outcomes.
        <?php
            if (!empty($channels)) {
                echo 'Content is organized into "channels", and the following channels are currently available on this device:';
            }
        ?>
    </p>

        <?php


            if (!$provisioned) {
                echo "<div class='kolibri-error'>Kolibri is not yet configured. Please <a href='$host'>set up an admin account here</a>.</div>";
            } elseif (!$available) {
                echo "<div class='kolibri-error'>Sorry, Kolibri is currently unavailable. Please try again later, or restart the RACHEL.</div>";
            } else {

                echo '<ul class="double">';

                foreach($channels as $channel) {
                    $name = $channel["name"];
                    $description = $channel["description"];
                    $channel_root_topic_id = $channel["root"];
                    $thumbnail = $channel["thumbnail"];
                    echo "
                        <li class='channel-list-item'>
                            <a href='$host/learn/#/topics/$channel_root_topic_id'><img class='channel-thumbnail' src='$thumbnail'/></a>
                            <a class='channel-title' href='$host/learn/#/topics/$channel_root_topic_id'>$name</a>
                            <div class='channel-description'>$description &nbsp;</div>
                        </li>
                    ";
                }

                echo '</ul>';

                echo "
                    <p style='margin-left: 130px;'>
                        Create an <a href=\"$host\"/user/#/Create_account'>account</a>
                        or, <a href=\"$host\">login</a> and continue where you left off.
                    <!-- You can <a href=\"$host\"/user/#/create_account'>create an account to track progress</a>. -->
                    <!-- Later, you can <a href=\"$host\">login again and continue where you left off</a>. -->
                    </p>
                ";

            }

        ?>

</div>
