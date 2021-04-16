#Screen
ulimit -n 999999
output_file="$1_$2_output.log"
php scripts/Monitor.php --store="$1" --type="$2" > output_file 2>&1 &
tail -f output_file