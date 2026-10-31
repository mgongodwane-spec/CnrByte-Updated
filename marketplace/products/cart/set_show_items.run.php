<?php

//declare includes
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/dbh_conn.inc.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/config_session.inc.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/cnrbyte/includes/auth_helpers.php';
    cnrbyte_auth_require_post_csrf();
    require_once "cart.dbs.php";
    require_once "cart.func.php";

//declare global variables
    $product_name = $_POST["show_items"];

//create new sessiions
    if(isset($_SESSION["show_items"])){
        unset($_SESSION["show_items"]);
        header("location: create.pge.php");
    }else if(!isset($_SESSION["show_items"])){
        $_SESSION["show_items"] = $product_name;
        header("location: create.pge.php");
    }
