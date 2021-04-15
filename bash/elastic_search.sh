# Screen
ulimit -n 999999
php scripts/ElasticSearch.php > output.log 2>&1 &
tail -f output.log
