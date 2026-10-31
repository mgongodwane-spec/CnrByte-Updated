<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/global.shw.php';

unset($_SESSION['current_dashboard'], $_SESSION["selected_pge"]);
$_SESSION['current_dashboard'] = 'messages_dashboard';
$_SESSION["selected_pge"] = "create_pge";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Message</title>

    <link rel="stylesheet" href="/cnrbyte/css/header.css">
    <link rel="stylesheet" href="/cnrbyte/css/forms.css">
    <link rel="stylesheet" href="/cnrbyte/css/menu.css">
    <link rel="stylesheet" href="/cnrbyte/css/main.css">
</head>
<body>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/header.php'; ?>

    <?php  ?>
    <main style="max-width:1100px;margin:20px auto;">
        <?php show_menu_btns($conn); ?>
        <div class="create_box">
            <?php show_selected_form($conn); ?>
        </div>
    </main>
</body>
</html>