<?php

use App\Support\CspNonce;

/*
 * Get or generate the CSP nonce for the current request.
 *
 * Usage in Blade views:
 *   <script nonce="{{ csp_nonce() }}">console.log('Secure!');</script>
 *
 * @return string
 */
if (! function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        return CspNonce::generate();
    }
}
