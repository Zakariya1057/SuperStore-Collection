# Screen
ulimit -n 999999
php scripts/Collection.php --store_type=$1 > output.log 2>&1 &
tail -f output.log
