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
$status = $input['status'] ?? '';
$is_email = $input['is_email'] ?? false;

if (!$application_id) {
    echo json_encode(['success' => false, 'error' => 'Missing application ID']);
    exit;
}

// If name and job title not provided, fetch from database
if (empty($jobseeker_name) || empty($job_title)) {
    $stmt = $pdo->prepare("SELECT ep.first_name, ep.last_name, jp.title as job_title, c.company_name 
                           FROM job_applications ja 
                           JOIN employee_profiles ep ON ja.employee_id = ep.id 
                           JOIN job_postings jp ON ja.job_id = jp.id 
                           JOIN companies c ON jp.company_id = c.id 
                           WHERE ja.id = ? AND c.user_id = ?");
    $stmt->execute([$application_id, $_SESSION['user_id']]);
    $app_data = $stmt->fetch();
    
    if ($app_data) {
        $jobseeker_name = trim($app_data['first_name'] . ' ' . $app_data['last_name']);
        $job_title = $app_data['job_title'];
        if (empty($company_name)) {
            $company_name = $app_data['company_name'];
        }
    }
}

if (empty($jobseeker_name) || empty($job_title)) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Build the prompt based on whether it's email or SMS
if ($is_email) {
    // Email generation prompt
    $prompt = "Write a professional email message to a job applicant named {$jobseeker_name} ";
    
    if ($status === 'accepted') {
        $prompt .= "informing them that their application for the {$job_title} position at {$company_name} has been APPROVED. ";
        $prompt .= "Congratulate them and mention next steps. ";
    } elseif ($status === 'rejected') {
        $prompt .= "informing them that their application for the {$job_title} position at {$company_name} was not successful. ";
        $prompt .= "Be polite, professional, and encouraging. Thank them for their interest. ";
    } elseif ($status === 'reviewed') {
        $prompt .= "informing them that their application for the {$job_title} position at {$company_name} has been reviewed. ";
        $prompt .= "Let them know they will be contacted soon about next steps. ";
    } elseif ($status === 'interviewed') {
        $prompt .= "inviting them for an interview for the {$job_title} position at {$company_name}. ";
        if ($interview_date && $interview_time) {
            $prompt .= "The interview is scheduled for {$interview_date} at {$interview_time}. ";
        }
        $prompt .= "Be warm, professional, and include a call to action to confirm their availability. ";
    } else {
        $prompt .= "regarding their application for the {$job_title} position at {$company_name}. ";
    }
    
    $prompt .= "Write a complete email body (2-4 paragraphs) that is professional, warm, and engaging. ";
    $prompt .= "Do not include subject line or signature - just the main message content.";
    
    $systemPrompt = "You are a professional HR assistant writing email notifications to job applicants. Write clear, professional, and warm emails. Use proper paragraphs. Be encouraging and maintain a positive tone even when delivering rejection news.";
} else {
    // SMS generation prompt
    $prompt = "Write a warm, professional SMS message to a job applicant named {$jobseeker_name} for a {$job_title} position at {$company_name}. ";
    
    if ($interview_date && $interview_time) {
        $prompt .= "The interview is scheduled for {$interview_date} at {$interview_time}. ";
        $prompt .= "Location: {$interview_location}. ";
        $prompt .= "Contact person: {$contact_person} ({$contact_number}). ";
    }
    
    $prompt .= "Keep it concise (under 300 characters), friendly, and professional. ";
    $prompt .= "Include all necessary details they need.";
    
    $systemPrompt = "You are a helpful HR assistant writing SMS interview invitations. Be warm, professional, and concise. Include all key details: job title, company, date, time, location, and contact info. Keep messages under 300 characters.";
}

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
    'max_tokens' => $is_email ? 800 : 200
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
