<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Wrap the email content in a professional HTML template
 */
function wrapInTemplate($content, $logo_cid = null) {
    $site_name = SITE_NAME;
    
    // Use the embedded CID if available, otherwise fallback to SITE_URL
    $logo_src = $logo_cid ? "cid:{$logo_cid}" : SITE_URL . 'images/LOGO.png';
    
    return "
    <div style='background-color: #f4f7f9; padding: 40px 0; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
        <table align='center' border='0' cellpadding='0' cellspacing='0' width='600' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
            <!-- Header -->
            <tr style='background-color: #ffffff; border-bottom: 2px solid #f0f4f8;'>
                <td align='center' style='padding: 30px 0;'>
                    <img src='{$logo_src}' alt='{$site_name}' style='max-height: 60px; display: block;'>
                </td>
            </tr>
            <!-- Content -->
            <tr>
                <td style='padding: 40px; line-height: 1.6; color: #334155; font-size: 16px;'>
                    {$content}
                </td>
            </tr>
            <!-- Footer -->
            <tr style='background-color: #f8fafc; border-top: 1px solid #e2e8f0;'>
                <td align='center' style='padding: 20px; color: #94a3b8; font-size: 12px;'>
                    <p style='margin: 0;'>&copy; " . date('Y') . " {$site_name}. All rights reserved.</p>
                    <p style='margin: 5px 0 0;'>You received this email because of your recent activity on our platform.</p>
                </td>
            </tr>
        </table>
    </div>";
}

/**
 * Send an email notification using SMTP settings from env.php
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email content (HTML supported)
 * @param bool $isHtml Whether the body is HTML (default: true)
 * @return array Array with 'status' (success/error) and 'message'
 */
function sendEmailNotification($to, $subject, $body, $isHtml = true) {
    if (empty($to)) {
        return ['status' => 'error', 'message' => 'Recipient email is empty'];
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        
        // Handle Logo Embedding for HTML emails
        $logo_cid = null;
        if ($isHtml) {
            $logo_path = __DIR__ . '/../images/LOGO.png';
            if (file_exists($logo_path)) {
                $logo_cid = 'company_logo';
                $mail->addEmbeddedImage($logo_path, $logo_cid, 'LOGO.png');
            }
        }
        
        // Wrap HTML body in template
        if ($isHtml) {
            $mail->Body = wrapInTemplate($body, $logo_cid);
        } else {
            $mail->Body = $body;
        }
        
        if (!$isHtml) {
            $mail->AltBody = $body;
        }

        $mail->send();
        return ['status' => 'success', 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    }
}
?>
