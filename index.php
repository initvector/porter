#!/usr/bin/env php
<?php
/**
 * If log_errors and display_errors are both enabled, you'll see duplicated
 * error messages in the console.  To avoid this, log_error is disabled and
 * display_errors is explicitly set to stderr.
 */
ini_set('log_errors', 0);
ini_set('display_errors', 'stderr');

require ('./vendor/autoload.php');

$cli = new Garden\Porter\Frontend\Cli();
$cli->exec();
