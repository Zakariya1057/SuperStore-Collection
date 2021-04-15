# Screen
ulimit -n 999999
php scripts/Monitor.php stores > monitor_stores_output.log 2>&1 &
tail -f output.log
