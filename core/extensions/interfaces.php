<?php

namespace SMSPortalExtensions;

interface IDatabase
{
    public static function createConnection();
    public static function sqlSelect($conn, $query, $format = false, ...$args);
    public static function sqlInsert($conn, $query, $format = false, ...$args);
    public static function sqlUpdate($conn, $query, $format = false, ...$vars);
}

interface IDateFormatter
{
    public static function formatDate($dateString);
}

interface IAuthentication
{
    public static function createToken();
    public static function validateToken($token);
}

interface IValidator
{
    public static function validateUserInput($data);
    public static function validateLoginCredentials($conn, $username, $password);
}

interface IUIActions
{
    public static function loadSpinner();
    public static function showAlert($title, $message, $icon, ...$params);
}

interface ISMSClient
{
    public static function sendSMS($numbersArray, $message);
    public static function checkSMSBalance();
}
