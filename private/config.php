<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

function is_logged_in(): bool {
    return (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']);
}