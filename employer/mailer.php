<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Wrap the email content in a professional HTML template
 */
function wrapInTemplate($content, $logo_cid = null) {
    $site_name = SITE_NAME;
    $current_year = date('Y');
    
    // Use the embedded CID if available, otherwise fallback to SITE_URL
    $logo_src = $logo_cid ? "cid:{$logo_cid}" : SITE_URL . 'images/LOGO.png';
    
    return "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$site_name} Notification</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
    <table role='presentation' cellpadding='0' cellspacing='0' width='100%' style='border-collapse: collapse;'>
        <tr>
            <td align='center' style='padding: 40px 20px;'>
                <!-- Main Container -->
                <table role='presentation' cellpadding='0' cellspacing='0' width='600' style='border-collapse: collapse; max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);'>
                    <!-- Header with Gradient -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); padding: 30px; text-align: center;'>
                            <img src='{$logo_src}' alt='{$site_name}' style='max-height: 50px; display: inline-block; filter: brightness(0) invert(1);'>
                        </td>
                    </tr>
                    <!-- Decorative Line -->
                    <tr>
                        <td style='height: 4px; background: linear-gradient(90deg, #10b981, #3b82f6, #f59e0b);'></td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style='padding: 40px;'>
                            {$content}
                        </td>
                    </tr>
                    <!-- Call to Action Area -->
                    <tr>
                        <td style='padding: 0 40px 30px; text-align: center;'>
                            <div style='background: #f8fafc; border-radius: 8px; padding: 20px; margin-top: 10px;'>
                                <p style='margin: 0 0 15px 0; color: #64748b; font-size: 14px;'>Access your account for more details</p>
                                <a href='" . SITE_URL . "login.php' style='display: inline-block; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 6px; font-weight: 600; font-size: 14px;'>Log In to Your Account</a>
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style='background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 30px 40px; text-align: center;'>
                            <!-- Social Links Placeholder -->
                            <p style='margin: 0 0 15px 0;'>
                                <a href='#' style='display: inline-block; margin: 0 8px; color: #94a3b8; text-decoration: none;'><span style='font-size: 18px;'>📧</span></a>
                                <a href='#' style='display: inline-block; margin: 0 8px; color: #94a3b8; text-decoration: none;'><span style='font-size: 18px;'>🌐</span></a>
                                <a href='#' style='display: inline-block; margin: 0 8px; color: #94a3b8; text-decoration: none;'><span style='font-size: 18px;'>💼</span></a>
                            </p>
                            <p style='margin: 0; color: #94a3b8; font-size: 13px; line-height: 1.5;'>
                                &copy; {$current_year} {$site_name}. All rights reserved.
                            </p>
                            <p style='margin: 8px 0 0 0; color: #cbd5e1; font-size: 12px;'>
                                You received this email because of your activity on our platform.
                            </p>
                        </td>
                    </tr>
                </table>
                <!-- Bottom Spacing -->
                <table role='presentation' cellpadding='0' cellspacing='0' width='600' style='border-collapse: collapse; max-width: 600px; width: 100%; margin-top: 20px;'>
                    <tr>
                        <td style='text-align: center; color: #94a3b8; font-size: 12px;'>
                            <p style='margin: 0;'>Need help? Contact our support team</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
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
