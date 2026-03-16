<?php
$config_path = __DIR__ . '/../config.php';
if (file_exists($config_path)) {
    include $config_path;
}

$env_path = __DIR__ . '/../bootstrap/config/env.php';
if (file_exists($env_path)) {
    include $env_path;
}

// Ensure no previous output breaks JSON response
if (ob_get_length()) {
    ob_clean();
}

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'employer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$application_id = $input['application_id'] ?? 0;
$jobseeker_name = $input['jobseeker_name'] ?? '';
$job_title = $input['job_title'] ?? '';
$company_name = $input['company_name'] ?? '';
$interview_date = $input['interview_date'] ?? '';
$interview_time = $input['interview_time'] ?? '';
$interview_location = $input['interview_location'] ?? '';
$contact_person = $input['contact_person'] ?? '';
$contact_number = $input['contact_number'] ?? '';

if (!$application_id || !$jobseeker_name || !$job_title) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Build the prompt for Groq AI
$prompt = "Write a warm, professional SMS message to a job applicant named {$jobseeker_name} for a {$job_title} position at {$company_name}. ";
$prompt .= "The interview is scheduled for {$interview_date} at {$interview_time}. ";
$prompt .= "Location: {$interview_location}. ";
$prompt .= "Contact person: {$contact_person} ({$contact_number}). ";
$prompt .= "Keep it concise (under 300 characters), friendly, and professional. ";
$prompt .= "Include all necessary details they need for the interview.";

$systemPrompt = "You are a helpful HR assistant writing SMS interview invitations. Be warm, professional, and concise. Include all key details: job title, company, date, time, location, and contact info. Keep messages under 300 characters.";

$apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : null;
    
if (!$apiKey) {
    echo json_encode(['success' => false, 'error' => 'GROQ_API_KEY not configured']);
    exit;
}

$data = [
    'model' => 'llama-3.3-70b-versatile',
    'messages' => [
        [
            'role' => 'system',
            'content' => $systemPrompt
        ],
        [
            'role' => 'user',
            'content' => $prompt
        ]
    ],
    'temperature' => 0.7,
    'max_tokens' => 200
];

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'Failed to generate SMS. HTTP Code: ' . $httpCode]);
    exit;
}

$result = json_decode($response, true);
if (!isset($result['choices'][0]['message']['content'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid response from AI service']);
    exit;
}

$generatedMessage = trim($result['choices'][0]['message']['content']);

// Store the generated message in session for later use
$_SESSION['generated_sms_' . $application_id] = $generatedMessage;

echo json_encode([
    'success' => true,
    'message' => $generatedMessage
]);
