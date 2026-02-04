<?php
header('Content-Type: text/plain; charset=utf-8');
echo "CF-IPCountry: ".($_SERVER['HTTP_CF_IPCOUNTRY'] ?? '(missing)')."\n";
echo "CF-Connecting-IP: ".($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '(missing)')."\n";
echo "X-Forwarded-For: ".($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '(missing)')."\n";
echo "Remote-Addr: ".($_SERVER['REMOTE_ADDR'] ?? '(missing)')."\n";