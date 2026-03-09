<?php
include '../config.php';
include 'env.php';
requireRole('employer');

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$job_id = $data['job_id'] ?? null;
$candidate_user_id = $data['user_id'] ?? null;

if (!$job_id || !$candidate_user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing job_id or user_id']);
    exit;
}

try {
    // 1. Fetch Job Details
    $stmt = $pdo->prepare("SELECT title, description, requirements, experience_level, location, job_type FROM job_postings WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        throw new Exception("Job not found");
    }

    // 2. Fetch Candidate Details
    $stmt = $pdo->prepare("SELECT ep.*, u.email FROM employee_profiles ep JOIN users u ON ep.user_id = u.id WHERE ep.user_id = ?");
    $stmt->execute([$candidate_user_id]);
    $candidate = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$candidate) {
        throw new Exception("Candidate profile not found");
    }

    // 3. Prepare AI Prompt
    $prompt = "You are an expert HR recruiter and technical hiring manager. 
Compare the following Job Posting with the Candidate Profile and provide a professional match analysis.

JOB POSTING:
Title: {$job['title']}
Description: {$job['description']}
Requirements: {$job['requirements']}
Experience Level: {$job['experience_level']}
Location: {$job['location']}
Type: {$job['job_type']}

CANDIDATE PROFILE:
Skills: {$candidate['skills']}
Experience: {$candidate['experience_level']}
Bio: {$candidate['bio']}
Education: {$candidate['highest_education']}

TASK:
1. Provide a 'match_score' from 0 to 100 based on how well they fit the requirements.
2. Provide a 'reasoning' (maximum 2 sentences) explaining why they are or are not a good fit. Focus on technical alignment and experience.

OUTPUT FORMAT (JSON ONLY):
{
    \"match_score\": 85,
    \"reasoning\": \"The candidate has strong technical alignment with the required stack and relevant project work, though they are slightly below the preferred years of experience.\"
}";

    // 4. Call Groq AI
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY
    ]);
    
    $payload = [
        'model' => 'llama-3.3-70b-versatile',
        'messages' => [
            ['role' => 'system', 'content' => 'Return only valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.3,
        'response_format' => ['type' => 'json_object']
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("AI API returned error code $httpCode: $response");
    }

    $result = json_decode($response, true);
    $ai_content = json_decode($result['choices'][0]['message']['content'], true);

    echo json_encode([
        'success' => true,
        'match_score' => $ai_content['match_score'],
        'reasoning' => $ai_content['reasoning']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
