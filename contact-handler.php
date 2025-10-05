<?php
header('Content-Type: application/json');

// Configuration
$to_email = "your-email@example.com"; // Change this to your email
$from_name = "ImageConverter Contact Form";

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and sanitize form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validate required fields
if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

// Sanitize inputs
$name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// Subject mapping
$subject_map = [
    'general' => 'General Inquiry',
    'support' => 'Technical Support',
    'feedback' => 'Feedback',
    'business' => 'Business Partnership',
    'other' => 'Other'
];

$subject_text = isset($subject_map[$subject]) ? $subject_map[$subject] : 'Contact Form';

// Prepare email
$email_subject = "Contact Form: " . $subject_text;

$email_body = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #333; }
        .value { color: #555; margin-left: 10px; }
        .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>New Contact Form Submission</h2>
        </div>
        <div class='content'>
            <div class='field'>
                <span class='label'>Name:</span>
                <span class='value'>" . $name . "</span>
            </div>
            <div class='field'>
                <span class='label'>Email:</span>
                <span class='value'>" . $email . "</span>
            </div>
            <div class='field'>
                <span class='label'>Phone:</span>
                <span class='value'>" . ($phone ? $phone : 'Not provided') . "</span>
            </div>
            <div class='field'>
                <span class='label'>Subject:</span>
                <span class='value'>" . $subject_text . "</span>
            </div>
            <div class='field'>
                <span class='label'>Message:</span>
                <div class='value' style='margin-top: 10px; padding: 10px; background: white; border-left: 3px solid #667eea;'>
                    " . nl2br($message) . "
                </div>
            </div>
        </div>
        <div class='footer'>
            <p>This email was sent from ImageConverter contact form</p>
            <p>Submitted on: " . date('F j, Y, g:i a') . "</p>
        </div>
    </div>
</body>
</html>
";

// Email headers
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= "From: " . $from_name . " <noreply@imageconverter.com>" . "\r\n";
$headers .= "Reply-To: " . $email . "\r\n";

// Try to send email
if (mail($to_email, $email_subject, $email_body, $headers)) {
    // Optional: Save to database
    saveToDatabase($name, $email, $phone, $subject, $message);
    
    // Send auto-reply to user
    sendAutoReply($email, $name);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Thank you! Your message has been sent successfully. We will get back to you soon.'
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Sorry, there was an error sending your message. Please try again later or contact us directly.'
    ]);
}

// Function to send auto-reply
function sendAutoReply($user_email, $user_name) {
    $subject = "Thank you for contacting ImageConverter";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🖼️ ImageConverter</h2>
            </div>
            <div class='content'>
                <h3>Hi " . htmlspecialchars($user_name) . ",</h3>
                <p>Thank you for contacting us! We have received your message and our team will review it shortly.</p>
                <p>We typically respond within 24-48 hours during business days.</p>
                <p>If your inquiry is urgent, please feel free to call us at +1 (234) 567-890.</p>
                <p>Best regards,<br>ImageConverter Team</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: ImageConverter <noreply@imageconverter.com>" . "\r\n";
    
    mail($user_email, $subject, $message, $headers);
}

// Function to save to database (optional)
function saveToDatabase($name, $email, $phone, $subject, $message) {
    // Uncomment and configure if you want to save messages to database
    /*
    try {
        $db = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->prepare("
            INSERT INTO contact_messages (name, email, phone, subject, message, created_at) 
            VALUES (:name, :email, :phone, :subject, :message, NOW())
        ");
        
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':subject' => $subject,
            ':message' => $message
        ]);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
    */
}
?>