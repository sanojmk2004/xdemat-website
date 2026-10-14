<?php
// XDemat contact form handler. Creates a lead in the CRM (which emails a copy to the address below);
// if the CRM is unavailable, emails the form directly.
$TO   = 'info@xdemat.com';
$FROM = 'info@xdemat.com'; // must be a real mailbox on this hosting account

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
  exit;
}

// Spam trap: real visitors never fill this hidden field
if (!empty($_POST['website'])) {
  echo json_encode(['ok' => true]);
  exit;
}

function clean($v, $max) {
  $v = trim((string)$v);
  $v = str_replace(["\r", "\0"], '', $v);
  return mb_substr($v, 0, $max);
}

$topics = [
  'account'   => 'Account opening',
  'education' => 'Education and training',
  'xrover'    => 'XRover 5 product support / demo',
  'other'     => 'Something else',
];

$name    = str_replace("\n", ' ', clean($_POST['name'] ?? '', 100));
$mobile  = str_replace("\n", ' ', clean($_POST['mobile'] ?? '', 30));
$email   = str_replace("\n", ' ', clean($_POST['email'] ?? '', 150));
$topic   = $topics[$_POST['subject'] ?? ''] ?? 'Something else';
$text    = clean($_POST['message'] ?? '', 5000);

if ($name === '' || strlen(preg_replace('/\D/', '', $mobile)) < 10) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Please fill in your name and a 10-digit mobile number.']);
  exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Please check your email address.']);
  exit;
}

// What the sales team sees in the CRM
$message  = "[XDemat website] Enquiry about: $topic\n";
if ($email !== '') { $message .= "Email: $email\n"; }
$message .= $text !== '' ? "\n$text" : "\n(No message added)";

// Keep a copy of every enquiry in a private file outside the website folder, so no lead is lost
$leadFile = dirname(__DIR__) . '/xdemat-leads.csv';
$isNew = !file_exists($leadFile);
if ($fh = @fopen($leadFile, 'a')) {
  if ($isNew) { fputcsv($fh, ['Date', 'Name', 'Mobile', 'Email', 'Topic', 'Message']); }
  fputcsv($fh, [date('Y-m-d H:i:s'), $name, $mobile, $email, $topic, str_replace("\n", ' / ', $text)]);
  fclose($fh);
}

// 1) Send the enquiry into the CRM (crm.xrover5.com on this same hosting account).
$crmFile = dirname(__DIR__) . '/crm.xrover5.com/inc/website_lead.php';
if (is_file($crmFile)) {
  try {
    require $crmFile;
    $r = crm_website_enquiry([
      'name' => $name, 'mobile' => $mobile, 'message' => $message, 'notify' => $TO,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'page' => $_SERVER['HTTP_REFERER'] ?? '',
    ]);
    if (!empty($r['ok'])) {
      echo json_encode(['ok' => true]);
      exit;
    }
    if (!empty($r['error'])) {
      http_response_code(422);
      echo json_encode(['ok' => false, 'error' => $r['error']]);
      exit;
    }
  } catch (Throwable $e) {
    error_log('XDemat contact form: CRM not reachable, emailing instead: ' . $e->getMessage());
  }
}

// 2) Fallback if the CRM is unavailable: plain email from the hosting server.
$subject = 'XDemat website enquiry from ' . $name;
$body  = "New enquiry from the XDemat website\n\n";
$body .= "Name:    $name\n";
$body .= "Mobile:  $mobile\n";
$body .= "Email:   " . ($email !== '' ? $email : '-') . "\n";
$body .= "Topic:   $topic\n\n";
$body .= "Message:\n" . ($text !== '' ? $text : '-') . "\n\n";
$body .= "Sent: " . date('d M Y, H:i') . " (server time)\n";

$headers  = "From: XDemat Website <$FROM>\r\n";
if ($email !== '') { $headers .= "Reply-To: $email\r\n"; }
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$sent = @mail($TO, $encSubject, $body, $headers, '-f' . $FROM);
if (!$sent) { $sent = @mail($TO, $encSubject, $body, $headers); }
if (!$sent) { error_log('XDemat contact form: mail() failed sending to ' . $TO); }

if ($sent) {
  echo json_encode(['ok' => true]);
} else {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Your message could not be sent. Please try again.']);
}
