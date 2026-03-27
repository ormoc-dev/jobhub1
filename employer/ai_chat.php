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

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'employer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$context = $input['context'] ?? '';

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

// Get company info for context
$company_name = '';
$company_id = 0;
try {
    $stmt = $pdo->prepare("SELECT id, company_name FROM companies WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company = $stmt->fetch();
    if ($company) {
        $company_id = $company['id'];
        $company_name = $company['company_name'];
    }
} catch (Exception $e) {
    // Continue without company info
}

// Get applicant data for this company
$applicantData = [];
$jobData = [];
$statsData = [];

if ($company_id) {
    try {
        // Get recent applicants with details
        $stmt = $pdo->prepare("
            SELECT ja.id, ja.status, ja.applied_date, ja.interview_status,
                   jp.title as job_title,
                   ep.first_name, ep.last_name, ep.highest_education, ep.sex,
                   u.email
            FROM job_applications ja
            JOIN job_postings jp ON ja.job_id = jp.id
            JOIN employee_profiles ep ON ja.employee_id = ep.id
            JOIN users u ON ep.user_id = u.id
            WHERE jp.company_id = ?
            ORDER BY ja.applied_date DESC
            LIMIT 20
        ");
        $stmt->execute([$company_id]);
        $applicantData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get job postings
        $stmt = $pdo->prepare("
            SELECT id, title, status, posted_date, employees_required
            FROM job_postings
            WHERE company_id = ?
            ORDER BY posted_date DESC
        ");
        $stmt->execute([$company_id]);
        $jobData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_applications,
                SUM(CASE WHEN ja.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN ja.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                SUM(CASE WHEN ja.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN ja.interview_status = 'interviewed' THEN 1 ELSE 0 END) as interviewed_count
            FROM job_applications ja
            JOIN job_postings jp ON ja.job_id = jp.id
            WHERE jp.company_id = ?
        ");
        $stmt->execute([$company_id]);
        $statsData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Continue without applicant data
    }
}

// Build context summary for AI
$contextSummary = "";
if ($company_name) {
    $contextSummary .= "Company: {$company_name}\n";
}

if (!empty($statsData)) {
    $contextSummary .= "Application Statistics:\n";
    $contextSummary .= "- Total Applications: " . ($statsData['total_applications'] ?? 0) . "\n";
    $contextSummary .= "- Pending Review: " . ($statsData['pending_count'] ?? 0) . "\n";
    $contextSummary .= "- Accepted: " . ($statsData['accepted_count'] ?? 0) . "\n";
    $contextSummary .= "- Rejected: " . ($statsData['rejected_count'] ?? 0) . "\n";
    $contextSummary .= "- Interviewed: " . ($statsData['interviewed_count'] ?? 0) . "\n\n";
}

if (!empty($jobData)) {
    $contextSummary .= "Active Job Postings (" . count($jobData) . "):\n";
    foreach (array_slice($jobData, 0, 5) as $job) {
        $contextSummary .= "- {$job['title']} (Status: {$job['status']}, Required: {$job['employees_required']})\n";
    }
    $contextSummary .= "\n";
}

if (!empty($applicantData)) {
    $contextSummary .= "Recent Applicants (" . count($applicantData) . " total):\n";
    foreach (array_slice($applicantData, 0, 10) as $app) {
        $contextSummary .= "- {$app['first_name']} {$app['last_name']} | {$app['job_title']} | Status: {$app['status']}";
        if ($app['highest_education']) {
            $contextSummary .= " | Education: {$app['highest_education']}";
        }
        $contextSummary .= "\n";
    }
    $contextSummary .= "\n";
}

// Build system prompt
$systemPrompt = "You are an AI Hiring Assistant for WORKLINK, a job recruitment platform. ";
$systemPrompt .= "You help employers with hiring-related tasks. ";
if ($company_name) {
    $systemPrompt .= "You are assisting {$company_name}. ";
}
$systemPrompt .= "You have access to the company's applicant data, job postings, and statistics. ";
$systemPrompt .= "When answering questions, reference specific applicant names, job titles, and statistics from the data provided. ";
$systemPrompt .= "Keep responses concise, professional, and helpful. ";
$systemPrompt .= "You can help with: analyzing applicants, writing job descriptions, interview questions, offer letters, rejection messages, and hiring advice. ";
$systemPrompt .= "If asked about non-hiring topics, politely redirect to hiring-related assistance.\n\n";
$systemPrompt .= "CURRENT COMPANY DATA:\n" . $contextSummary;

// Build user prompt with context
$userPrompt = "User question: " . $message;
if (!empty($contextSummary)) {
    $userPrompt .= "\n\nPlease answer based on the company data provided above.";
}

// Check for API key
$apiKey = defined('GROQ_API_KEY') ? GROQ_API_KEY : null;

if (!$apiKey) {
    // Fallback response if no API key
    $fallbackResponses = [
        'job description' => "Here's a template for a job description:\n\n**Job Title:** [Position Name]\n\n**About the Role:**\nWe're looking for a talented [role] to join our team. In this position, you'll be responsible for [key responsibilities].\n\n**Requirements:**\n- [Requirement 1]\n- [Requirement 2]\n- [Requirement 3]\n\n**Benefits:**\n- Competitive salary\n- Health insurance\n- Professional development opportunities\n\n**How to Apply:**\nPlease submit your resume and cover letter.",
        
        'interview question' => "Here are some effective interview questions:\n\n1. **Tell me about yourself.** (Ice breaker)\n2. **Why are you interested in this position?** (Motivation)\n3. **Describe a challenging situation at work and how you handled it.** (Problem-solving)\n4. **What are your greatest strengths and weaknesses?** (Self-awareness)\n5. **Where do you see yourself in 5 years?** (Career goals)\n6. **Why should we hire you?** (Value proposition)\n7. **Do you have any questions for us?** (Engagement)",
        
        'offer letter' => "Here's an offer letter template:\n\nDear [Candidate Name],\n\nWe are pleased to offer you the position of [Job Title] at {$company_name}.\n\n**Position Details:**\n- Start Date: [Date]\n- Salary: [Amount]\n- Employment Type: [Full-time/Part-time]\n- Reporting to: [Manager Name]\n\n**Benefits:**\n- [List benefits]\n\nPlease confirm your acceptance by [Date].\n\nWelcome to the team!\n\nSincerely,\n[Your Name]",
        
        'rejection' => "Here's a polite rejection message:\n\nDear [Candidate Name],\n\nThank you for your interest in the [Position] role at {$company_name} and for taking the time to interview with us.\n\nAfter careful consideration, we have decided to move forward with other candidates whose qualifications more closely match our current needs.\n\nWe appreciate your interest in our company and wish you the best in your job search.\n\nSincerely,\n[Your Name]"
    ];
    
    $response = "I'd be happy to help with that! However, the AI service is currently unavailable.\n\n";
    $message_lower = strtolower($message);
    
    foreach ($fallbackResponses as $key => $template) {
        if (strpos($message_lower, $key) !== false) {
            $response = $template;
            break;
        }
    }
    
    if ($response === "I'd be happy to help with that! However, the AI service is currently unavailable.\n\n") {
        $response .= "Please try asking about:\n- Writing job descriptions\n- Interview questions\n- Offer letters\n- Rejection messages\n- Hiring best practices";
    }
    
    echo json_encode(['success' => true, 'response' => $response]);
    exit;
}

// Call Groq API
$data = [
    'model' => 'llama-3.3-70b-versatile',
    'messages' => [
        [
            'role' => 'system',
            'content' => $systemPrompt
        ],
        [
            'role' => 'user',
            'content' => $userPrompt
        ]
    ],
    'temperature' => 0.7,
    'max_tokens' => 1000
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
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'Connection error: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => 'Failed to get AI response', 'http_code' => $httpCode, 'response' => $response]);
    exit;
}

$result = json_decode($response, true);
if (!isset($result['choices'][0]['message']['content'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid AI response', 'result' => $result]);
    exit;
}

$aiResponse = trim($result['choices'][0]['message']['content']);

echo json_encode([
    'success' => true,
    'response' => $aiResponse
]);
