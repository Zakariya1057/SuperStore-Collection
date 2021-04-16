# Screen
ulimit -n 999999
php scripts/Collection.php --store="$1" > "$1_output.log" 2>&1 &
tail -f "$1_output.log"
