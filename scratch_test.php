<?php
$opts = get_option('wc_cegid_sync_settings', []);
echo "Ambiente Sandbox: " . ($opts['sandbox'] ?? 'N/A') . "\n";
echo "Base URL: " . ($opts['api_base_url'] ?? 'N/A') . "\n";
echo "OAuth URL: " . ($opts['oauth_base_url'] ?? 'N/A') . "\n";
echo "NIF: " . ($opts['company_tax_id'] ?? 'N/A') . "\n";
echo "Username: " . ($opts['username'] ?? 'N/A') . "\n";
echo "Tem Client ID: " . (!empty($opts['client_id']) ? 'SIM' : 'NAO') . "\n";
echo "Tem Client Secret: " . (!empty($opts['client_secret']) ? 'SIM' : 'NAO') . "\n";
echo "Tem Password: " . (!empty($opts['password']) ? 'SIM' : 'NAO') . "\n";
