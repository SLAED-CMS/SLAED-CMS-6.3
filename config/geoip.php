<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

return [
    'geoip' => [
        'enabled' => true,
        'country_database' => 'storage/geoip/country.mmdb',
        'asn_database' => 'storage/geoip/asn.mmdb',
        'cache_ttl' => 86400,
        'anonymize_ip' => true,
        'store_ip' => false,
    ],
];
