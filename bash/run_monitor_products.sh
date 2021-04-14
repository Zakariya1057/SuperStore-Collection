# Screen
ulimit -n 999999
php scripts/Monitor.php products > output.log 2>&1 &
tail -f output.log
