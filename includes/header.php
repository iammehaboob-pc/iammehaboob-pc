<?php
/**
 * SmartFix AI - Page Header Template
 */
if (count(get_included_files()) === 1) { http_response_code(403); exit(); }
$csrfMeta = Security::generateCsrfToken();
$notifCount = Session::isLoggedIn() ? NotificationHelper::getUnreadCount(Session::get('user_id')) : 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfMeta); ?>">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - SmartFix AI' : 'SmartFix AI - Campus Complaint System'; ?></title>
    <meta name="description" content="SmartFix AI: AI-powered campus maintenance and complaint management system.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/main.css">
</head>
<body>
<div class="app-layout">
