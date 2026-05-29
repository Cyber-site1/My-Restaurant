<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageKey = $_GET['page'] ?? '';
$policiesFile = __DIR__ . '/../policies.json';
$pageData = null;

if (file_exists($policiesFile)) {
    $allPolicies = json_decode(file_get_contents($policiesFile), true) ?? [];
    if (isset($allPolicies[$pageKey])) {
        $pageData = $allPolicies[$pageKey];
    }
}

if (!$pageData) {
    die("Policy document not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageData['title']); ?></title>
    <style>
        body {
            background-color: #ffffff !important;
            color: #111111 !important; 
            font-family: Arial, sans-serif;
            padding: 50px 24px;
            margin: 0;
            line-height: 1.6;
        }
        .policy-container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
        }
        h1 {
            color: tomato;
            font-size: 28px;
            text-transform: uppercase;
            border-bottom: 2px solid #eaeaea;
            padding-bottom: 12px;
            margin-bottom: 30px;
        }
        .policy-content-box {
            font-size: 18px !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            color: #111111 !important;
        }
        /* Style configurations for elements coming out of CKEditor */
        .policy-content-box p { margin-bottom: 1.2em; }
        .policy-content-box ul, .policy-content-box ol { padding-left: 24px; margin-bottom: 1.2em; }
        .policy-content-box strong { font-weight: bold; }
    </style>
</head>
<body>
    <div class="policy-container">
        <h1><?php echo htmlspecialchars($pageData['title']); ?></h1>
        <div class="policy-content-box">
            <?php 
                // Allow safe rich text tags from CKEditor, convert plain string line breaks if editor isn't loaded yet
                $cleanHTML = strip_tags($pageData['content'], '<p><br><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6>');
                echo (strpos($cleanHTML, '<p>') === false) ? nl2br($cleanHTML) : $cleanHTML; 
            ?>
        </div>
    </div>
</body>
</html>
