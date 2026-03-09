<?php
include '../config.php';
requireRole('employer');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
    $document_type = sanitizeInput($_POST['document_type']);
    $verification_status = sanitizeInput($_POST['verification_status']);
    $verification_notes = sanitizeInput($_POST['verification_notes']);
    $verified_by = $_SESSION['user_id'];
    
    $allowed_doc_types = ['resume', 'id_document', 'document1', 'document2', 'tor_document', 'employment_certificate', 'seminar_certificate', 'cover_letter', 'certificate_of_attachment', 'certificate_of_reports', 'certificate_of_good_standing'];
    
    if (!$application_id || !$document_type || !$verification_status || !in_array($document_type, $allowed_doc_types)) {
        $error = 'Invalid parameters.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get application details
            $stmt = $pdo->prepare("SELECT ja.*, ep.user_id as employee_user_id, jp.title as job_title, c.company_name 
                                   FROM job_applications ja
                                   JOIN employee_profiles ep ON ja.employee_id = ep.id
                                   JOIN job_postings jp ON ja.job_id = jp.id
                                   JOIN companies c ON jp.company_id = c.id
                                   WHERE ja.id = ?");
            $stmt->execute([$application_id]);
            $application = $stmt->fetch();
            
            if (!$application) {
                throw new Exception('Application not found.');
            }
            
            // Insert verification record
            $stmt = $pdo->prepare("INSERT INTO document_verifications (application_id, document_type, verified_by, verification_status, verification_notes, verified_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$application_id, $document_type, $verified_by, $verification_status, $verification_notes]);
            
            // Create notification for the employee (if notifications table exists)
            try {
                $status_text = $verification_status === 'verified' ? 'verified' : 'rejected';
                $doc_name = match($document_type) {
                    'resume' => 'Resume',
                    'id_document' => 'ID Document',
                    'document1' => 'Document 1 (Resume/CV)',
                    'document2' => 'Document 2 (Other)',
                    'tor_document' => 'TOR Document',
                    'employment_certificate' => 'Employment Certificate',
                    'seminar_certificate' => 'Seminar Certificate',
                    'cover_letter' => 'Cover Letter',
                    'certificate_of_attachment' => 'Certificate of Attachment',
                    'certificate_of_reports' => 'Certificate of Reports',
                    'certificate_of_good_standing' => 'Certificate of Good Standing',
                    default => ucfirst($document_type)
                };
                
                $notification_message = "Your {$doc_name} for {$application['job_title']} at {$application['company_name']} has been {$status_text}.";
                
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, related_id) 
                                       VALUES (?, 'document_verification', ?, ?)");
                $stmt->execute([$application['employee_user_id'], $notification_message, $application_id]);
            } catch (Exception $e) {
                // Notifications table doesn't exist yet, skip notification creation
                // This is optional functionality
            }
            
            $pdo->commit();
            $_SESSION['message'] = 'Document verification recorded successfully!';
            redirect('view-application.php?id=' . $application_id);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to record verification: ' . $e->getMessage();
        }
    }
}

// If we reach here, there was an error
if ($error) {
    $_SESSION['error'] = $error;
    // If we have an application_id, redirect back to view-application.php
    if (isset($_POST['application_id']) && $_POST['application_id']) {
        redirect('view-application.php?id=' . (int)$_POST['application_id']);
    }
}
redirect('employee-documents.php');
