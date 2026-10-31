<?php

//declare includes
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/dbh_conn.inc.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/config_session.inc.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
    cnrbyte_auth_require_post_csrf();
    require_once "cart.dbs.php";
    require_once "cart.func.php";

//declare global variables
    $show_address = $_POST["show_address"];

//create new sessiions
    if(isset($_SESSION["show_address"])){
        unset($_SESSION["show_address"]);
        header("location: edit.pge.php");
    }else if(!isset($_SESSION["show_address"])){
        $_SESSION["show_address"] = $show_address;
        header("location: edit.pge.php");
    }
