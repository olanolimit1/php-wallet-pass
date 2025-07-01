<?php
// Enhanced Error Handling
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'error' => 'A fatal error occurred.',
            'details' => [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ],
        ]);
    }
});

// Include the Composer autoloader
require 'vendor/autoload.php';

// Use the PKPass class from the library
use PKPass\PKPass;

// --- Configuration ---
$certPath = __DIR__ . '/certificates';
$templatePath = $certPath . '/pass-template';
// IMPORTANT: Set the password you used when exporting the .p12 file from Keychain Access.
// If you did not set a password, leave this as an empty string.
$p12Password = 'superSweetGummy12'; 

// --- Start of Server Logic ---
try {
    // Get the JSON data from the incoming request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Basic validation
    if (empty($data['profileId']) || empty($data['email']) || empty($data['name'])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    // --- Create the Pass Object ---
    // Pass the .p12 file, its password, and the WWDR cert directly to the constructor.
    $p12CertPath = $certPath . '/signerCert.p12';
    $wwdrCertPath = $certPath . '/wwdr.pem';
    $pass = new PKPass($p12CertPath, $p12Password, $wwdrCertPath);

    // --- Add Pass Data ---
    $profile = $data['profile'] ?? [];
    $passData = [
        'description'      => 'Professional Rate Card',
        'formatVersion'    => 1,
        'organizationName' => 'RateCard',
        'passTypeIdentifier' => 'pass.com.adspaceng.ratecardapp',
        'serialNumber'     => 'ratecard-' . $data['profileId'] . '-' . time(),
        'teamIdentifier'   => 'Q3YGQ4925G',
        'backgroundColor'  => 'rgb(24, 76, 116)',
        'foregroundColor'  => 'rgb(255, 255, 255)',
        'relevantDate'     => date('Y-m-d\TH:i:sP'),
        'webServiceURL'      => 'https://ratecard.app',
        'authenticationToken' => bin2hex(random_bytes(16)),
        'barcode'          => [
            'format'          => 'PKBarcodeFormatQR',
            'message'         => 'https://ratecard.app/u/' . ($profile['username'] ?? 'demo'),
            'messageEncoding' => 'iso-8859-1',
        ],
        'generic' => [
            'primaryFields' => [['key' => 'name', 'label' => 'Professional', 'value' => $profile['name'] ?? $data['name']]],
            'secondaryFields' => [
                ['key' => 'industry', 'label' => 'Industry', 'value' => $profile['industry'] ?? 'Professional Services'],
                ['key' => 'username', 'label' => 'Username', 'value' => '@' . ($profile['username'] ?? 'demo')]
            ],
            'auxiliaryFields' => [['key' => 'contact', 'label' => 'Contact', 'value' => 'Scan QR Code']],
            'backFields' => [
                ['key' => 'bio', 'label' => 'About', 'value' => $profile['bio'] ?? 'Visit my rate card for professional services and pricing information.'],
                ['key' => 'instructions', 'label' => 'Instructions', 'value' => 'Scan the QR code to view detailed pricing and contact information.']
            ]
        ]
    ];
    $pass->setData($passData);

    // --- Add Image Files ---
    $pass->addFile($templatePath . '/icon.png');
    $pass->addFile($templatePath . '/icon@2x.png');
    $pass->addFile($templatePath . '/logo.png');

    // --- Create and Output the Pass ---
    // Generate a unique filename using the profileId and current timestamp
    $filename = 'ratecard-' . $data['profileId'] . '-' . time() . '.pkpass';
    header('Content-Type: application/vnd.apple.pkpass');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $pass->create(true); // The 'true' argument outputs the pass directly
    exit;

} catch (Exception $e) {
    http_response_code(500);
    // Provide a detailed error message for debugging
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to generate pass', 'details' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit;
}