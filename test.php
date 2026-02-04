<?php
header('Content-Type: text/plain; charset=utf-8');
echo "CF-IPCountry: ".((isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : '(missing))')."\n";
echo "CF-Connecting-IP: ".((isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : '(missing))')."\n";
echo "X-Forwarded-For: ".((isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '(missing))')."\n";
echo "Remote-Addr: ".((isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '(missing))')."\n";