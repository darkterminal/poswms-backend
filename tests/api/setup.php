#!/usr/bin/env php
<?php

declare(strict_types = 1);

/**
 * REST Client Setup Script.
 *
 * This script helps you set up the REST client for testing by:
 * 1. Checking if the API is accessible
 * 2. Listing available test users
 * 3. Creating a .rest-client-config.php file
 * 4. Running a quick authentication test
 */

require __DIR__ . '/../../vendor/autoload.php';

echo "===========================================\n";
echo "POS WMS REST Client Setup\n";
echo "===========================================\n\n";

$configFile = __DIR__ . '/.rest-client-config.php';
$configExample = __DIR__ . '/.rest-client-config.example.php';

// Step 1: Check database for users
echo "Step 1: Checking available test users...\n";
echo "-------------------------------------------\n";

try {
    $users = DB::select('SELECT id, name, email, tenant_id, role FROM users LIMIT 10');

    if (empty($users)) {
        echo "⚠ No users found in database.\n";
        echo "  Please seed your database first:\n";
        echo "  php artisan db:seed\n\n";
    } else {
        echo '✓ Found ' . count($users) . " user(s):\n\n";
        echo "  | ID | Email                    | Tenant | Role  |\n";
        echo "  |----|--------------------------|--------|-------|\n";
        foreach ($users as $user) {
            printf(
                "  | %d  | %-24s | %d      | %-5s |\n",
                $user->id,
                $user->email,
                $user->tenant_id,
                $user->role
            );
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo '⚠ Could not query database: ' . $e->getMessage() . "\n";
    echo "  Make sure your database is configured and migrated.\n\n";
}

// Step 2: Check if config file exists
echo "Step 2: Checking configuration file...\n";
echo "-------------------------------------------\n";

if (file_exists($configFile)) {
    echo "✓ Configuration file exists: {$configFile}\n\n";
    $config = require $configFile;
    echo "  Current configuration:\n";
    echo "  - Base URL: {$config['base_url']}\n";
    echo "  - Tenant ID: {$config['tenant_id']}\n";
    echo "  - Email: {$config['email']}\n\n";
} else {
    echo "⚠ Configuration file not found.\n\n";

    if (file_exists($configExample)) {
        echo "  Would you like to create it from the example?\n";
        echo "  Copy: {$configExample}\n";
        echo "  To:   {$configFile}\n\n";

        echo "  Run: cp {$configExample} {$configFile}\n\n";

        // Auto-create with defaults
        $createConfig = readline('  Auto-create with defaults? (y/n): ');
        if (strtolower(trim($createConfig)) === 'y') {
            copy($configExample, $configFile);
            echo "✓ Configuration file created.\n";
            echo "  Please edit it with your correct credentials.\n\n";
        }
    }
}

// Step 3: Test API connectivity
echo "Step 3: Testing API connectivity...\n";
echo "-------------------------------------------\n";

$baseUrl = 'http://localhost:8000';
if (file_exists($configFile)) {
    $config = require $configFile;
    $baseUrl = $config['base_url'];
}

echo "  Testing: {$baseUrl}\n";

$ch = curl_init($baseUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_NOBODY => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if (PHP_VERSION_ID < 80500) {
    curl_close($ch);
}

if ($error) {
    echo "✗ Cannot connect to API: {$error}\n";
    echo "  Make sure the Laravel server is running:\n";
    echo "  php artisan serve\n\n";
} else {
    echo "✓ API is accessible (HTTP {$httpCode})\n\n";
}

// Step 4: Show usage instructions
echo "===========================================\n";
echo "Setup Complete!\n";
echo "===========================================\n\n";

echo "Next steps:\n\n";

echo "1. Create configuration file (if not exists):\n";
echo "   cp tests/api/.rest-client-config.example.php tests/api/.rest-client-config.php\n\n";

echo "2. Edit the configuration with correct credentials:\n";
echo "   nano tests/api/.rest-client-config.php\n\n";

echo "3. Or use command line options:\n";
echo "   php tests/api/RestClient.php --email=admin@demo.com --password=password\n\n";

echo "4. Run the tests:\n";
echo "   php tests/api/RestClient.php --all\n";
echo "   php tests/api/RestClient.php --endpoint=products\n";
echo "   php tests/api/RestClient.php --verbose\n\n";

echo "Available test users (default password: 'password'):\n";
if (! empty($users)) {
    foreach ($users as $user) {
        echo "  - {$user->email} (Tenant: {$user->tenant_id}, Role: {$user->role})\n";
    }
}
echo "\n";

echo "===========================================\n";
