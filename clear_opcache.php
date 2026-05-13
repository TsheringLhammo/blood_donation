<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared successfully!";
} else {
    echo "OPcache not enabled or reset function not available.";
}
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo " APCu cache cleared!";
}
?>
