<?php
// This version saves messages to a file - no mail() needed!
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$name = isset($_POST['name']) ? strip_tags(trim($_POST['name'])) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$message = isset($_POST['message']) ? strip_tags(trim($_POST['message'])) : '';

// Validate inputs
$errors = [];
if (empty($name)) $errors[] = 'Name is required';
if (empty($email)) $errors[] = 'Email is required';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
if (empty($message)) $errors[] = 'Message is required';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Create messages directory if it doesn't exist
$messages_dir = __DIR__ . '/contact_messages';
if (!file_exists($messages_dir)) {
    mkdir($messages_dir, 0700, true);
}

// Create .htaccess to protect the messages directory
$htaccess_file = $messages_dir . '/.htaccess';
if (!file_exists($htaccess_file)) {
    file_put_contents($htaccess_file, "Deny from all");
}

// Generate unique filename
$timestamp = date('Y-m-d_H-i-s');
$random = substr(md5(uniqid(rand(), true)), 0, 8);
$filename = $messages_dir . '/message_' . $timestamp . '_' . $random . '.txt';

// Prepare message content
$content = "=================================\n";
$content .= "NEW CONTACT FORM SUBMISSION\n";
$content .= "=================================\n\n";
$content .= "Date: " . date('Y-m-d H:i:s') . "\n";
$content .= "Name: " . $name . "\n";
$content .= "Email: " . $email . "\n";
$content .= "IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n\n";
$content .= "Message:\n";
$content .= str_repeat("-", 40) . "\n";
$content .= $message . "\n";
$content .= str_repeat("-", 40) . "\n";

// Save to file
if (file_put_contents($filename, $content) !== false) {
    // Also append to a master log file
    $log_file = $messages_dir . '/all_messages.log';
    file_put_contents($log_file, "\n\n" . $content, FILE_APPEND);
    
    // Send notification email (optional - will fail silently if mail() doesn't work)
    @mail('support@pipchell.com', 
          'New Contact Message from ' . $name,
          $content,
          "From: support@pipchell.com\r\nReply-To: $email");
    
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your message! We will get back to you soon.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to save message. Please email support@pipchell.com directly.'
    ]);
}
?>
