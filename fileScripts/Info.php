<?php

print_r( $version = curl_version());
print $ssl_supported= ($version['features'] & CURL_VERSION_SSL);

?>