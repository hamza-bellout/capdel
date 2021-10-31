<?php

$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = array($_SERVER['REMOTE_ADDR']);

if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&  $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
  $_SERVER['HTTPS'] = 'on';
  // This is hardcoded because there is no header specifying the original port.
  $_SERVER['SERVER_PORT'] = $_SERVER['HTTP_X_FORWARDED_PORT'];
}
