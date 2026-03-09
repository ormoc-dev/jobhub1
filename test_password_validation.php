<?php
/**
 * Password Validation Test Script
 * Tests the password validation rules for registration
 */

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Password Validation Test</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .test-pass { color: green; }
        .test-fail { color: red; }
        .test-info { color: blue; }
    </style>
</head>
<body class='container mt-5'>
    <h1 class='mb-4'>Password Validation Test</h1>
    <p class='text-muted'>Testing password validation with special characters: @ # $ % &</p>
    
    <div class='table-responsive'>
        <table class='table table-bordered'>
            <thead class='table-dark'>
                <tr>
                    <th>Test #</th>
                    <th>Password</th>
                    <th>Expected</th>
                    <th>PHP Regex</th>
                    <th>Has Uppercase</th>
                    <th>Has Lowercase</th>
                    <th>Has Special</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>";

// Test cases
$testCases = [
    // Valid passwords
    ['juan#123', true, 'Valid - has #'],
    ['juan123$', true, 'Valid - has $'],
    ['Juan@456', true, 'Valid - has @'],
    ['password%789', true, 'Valid - has %'],
    ['test&123', true, 'Valid - has &'],
    ['Juan#123', true, 'Valid - has uppercase, lowercase, number, and #'],
    ['MyPass$123', true, 'Valid - has uppercase, lowercase, number, and $'],
    ['Secure@Pass1', true, 'Valid - has uppercase, lowercase, number, and @'],
    
    // Invalid passwords
    ['juan123', false, 'Invalid - no special character'],
    ['JUAN#123', false, 'Invalid - no lowercase'],
    ['juan#123', false, 'Invalid - no uppercase'],
    ['Juan#', false, 'Invalid - no number'],
    ['Juan1', false, 'Invalid - too short (< 8 chars)'],
    ['Juan#12', false, 'Invalid - too short (< 8 chars)'],
    ['Juan!123', false, 'Invalid - ! not allowed (only @ # $ % &)'],
    ['Juan*123', false, 'Invalid - * not allowed (only @ # $ % &)'],
    ['Juan^123', false, 'Invalid - ^ not allowed (only @ # $ % &)'],
];

$passCount = 0;
$failCount = 0;

foreach ($testCases as $index => $test) {
    $password = $test[0];
    $expected = $test[1];
    $description = $test[2];
    
    // PHP validation checks (same as register.php)
    $hasLength = strlen($password) >= 8;
    $matchesPattern = preg_match('/^[a-zA-Z0-9@#$%&]{8,}$/', $password);
    $hasUppercase = preg_match('/[A-Z]/', $password);
    $hasLowercase = preg_match('/[a-z]/', $password);
    $hasSpecial = preg_match('/[@#$%&]/', $password);
    
    // Overall validation result
    $isValid = $hasLength && $matchesPattern && $hasUppercase && $hasLowercase && $hasSpecial;
    
    // Check if result matches expected
    $testPassed = ($isValid === $expected);
    
    if ($testPassed) {
        $passCount++;
        $resultClass = 'test-pass';
        $resultText = '✓ PASS';
    } else {
        $failCount++;
        $resultClass = 'test-fail';
        $resultText = '✗ FAIL';
    }
    
    echo "<tr class='$resultClass'>
            <td>" . ($index + 1) . "</td>
            <td><code>$password</code></td>
            <td>$description</td>
            <td>" . ($matchesPattern ? '✓' : '✗') . "</td>
            <td>" . ($hasUppercase ? '✓' : '✗') . "</td>
            <td>" . ($hasLowercase ? '✓' : '✗') . "</td>
            <td>" . ($hasSpecial ? '✓' : '✗') . "</td>
            <td><strong>$resultText</strong></td>
          </tr>";
}

echo "</tbody>
    </table>
</div>

<div class='alert alert-info mt-4'>
    <h5>Test Summary</h5>
    <p><strong>Total Tests:</strong> " . count($testCases) . "</p>
    <p class='test-pass'><strong>Passed:</strong> $passCount</p>
    <p class='test-fail'><strong>Failed:</strong> $failCount</p>
    <p><strong>Success Rate:</strong> " . round(($passCount / count($testCases)) * 100, 2) . "%</p>
</div>

<div class='alert alert-warning mt-3'>
    <h5>Allowed Special Characters</h5>
    <p>The following 5 special characters are allowed in passwords:</p>
    <ul>
        <li><code>@</code> - At symbol</li>
        <li><code>#</code> - Hash/Pound</li>
        <li><code>$</code> - Dollar sign</li>
        <li><code>%</code> - Percent</li>
        <li><code>&</code> - Ampersand</li>
    </ul>
    <p><strong>Password Requirements:</strong></p>
    <ul>
        <li>At least 8 characters long</li>
        <li>At least one uppercase letter (A-Z)</li>
        <li>At least one lowercase letter (a-z)</li>
        <li>At least one number (0-9)</li>
        <li>At least one special character from: @ # $ % &</li>
    </ul>
</div>

<div class='alert alert-success mt-3'>
    <h5>Example Valid Passwords</h5>
    <ul>
        <li><code>juan#123</code> - Has #</li>
        <li><code>juan123$</code> - Has $</li>
        <li><code>Juan@456</code> - Has @</li>
        <li><code>MyPass%789</code> - Has %</li>
        <li><code>Secure&123</code> - Has &</li>
    </ul>
</div>

</body>
</html>";
?>

