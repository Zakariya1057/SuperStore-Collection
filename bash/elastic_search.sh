# Screen
ulimit -n 999999
php scripts/ElasticSearch.php > elastic_search_output.log 2>&1 &
tail -f elastic_search_output.log
