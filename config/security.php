<?php

return [
    /*
     * Empty: trust no proxy, use the real REMOTE_ADDR — correct when nginx is
     * the outermost edge, as in the current Docker stack. Set to '*' only if
     * a real load balancer/ingress sits in front of nginx, or to a
     * comma-separated list of specific proxy IPs/CIDRs — otherwise the login
     * throttle and IP-based rate limit collapse into one shared bucket for
     * every client behind that proxy.
     */
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),
];
