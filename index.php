<?php
session_start();
//ini_set("display_errors", 'off'); //remove when not live
set_time_limit(120);
require_once('APIAccessExample.php');
?>
 
<!doctypehtml>
<html lang="en">
<head>
<meta charset="ISO-8859-8">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
<link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css"/>
<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
<title>Playlist Example</title>
</head>
<body>
<script>
    $(function() {
        $(".modal").modal("show");
 
    })
</script>
</body>
</html>