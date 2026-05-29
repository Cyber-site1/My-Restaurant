<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Return data formatted as json so our javascript fetch requests can read it
header('Content-Type: application/json');

$reviewsFile = __DIR__ . '/../reviews.json';

// ==========================================================================
// 1. HANDLE FETCHING REVIEWS (GET REQUEST)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $dishId = isset($_GET['dish_id']) ? (int)$_GET['dish_id'] : 0;
    
    // Read the array file if it exists, otherwise initialize an empty list
    $allReviews = file_exists($reviewsFile) ? json_decode(file_get_contents($reviewsFile), true) : [];
    if (!is_array($allReviews)) {
        $allReviews = [];
    }
    
    // Filter out only the reviews that match our clicked item card ID
    $filteredReviews = array_filter($allReviews, function($r) use ($dishId) {
        return isset($r['dish_id']) && (int)$r['dish_id'] === $dishId;
    });
    
    // Reset array indexes and sort so that the newest comments show first
    $sortedOutput = array_values($filteredReviews);
    
    echo json_encode(array_reverse($sortedOutput));
    exit();
}

// ==========================================================================
// 2. HANDLE SUBMITTING A NEW REVIEW (POST REQUEST)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Security check: Customer must be logged in to post
    if (!isset($_SESSION['user'])) {
        echo json_encode(['error' => 'Please log in to your account first to post a review.']);
        exit();
    }

    // Capture the JSON payload sent by our submitModalReviewForm() javascript function
    $rawInputData = file_get_contents('php://input');
    $parsedJson = json_decode($rawInputData, true);

    $dishId  = isset($parsedJson['dish_id']) ? (int)$parsedJson['dish_id'] : 0;
    $rating  = isset($parsedJson['rating']) ? (int)$parsedJson['rating'] : 5;
    $comment = isset($parsedJson['comment']) ? trim($parsedJson['comment']) : '';

    // Validation checks
    if ($dishId <= 0 || empty($comment)) {
        echo json_encode(['error' => 'Please type a comment before submitting your review.']);
        exit();
    }
    
    if ($rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'Invalid star rating selected.']);
        exit();
    }

    $allReviews = file_exists($reviewsFile) ? json_decode(file_get_contents($reviewsFile), true) : [];
    if (!is_array($allReviews)) {
        $allReviews = [];
    }
    
    // Construct the structured review record database entry row
    $newReviewRecord = [
        "id"          => "REV-" . strtoupper(substr(md5(time() . $_SESSION['user']['id']), 0, 8)),
        "dish_id"     => $dishId,
        "user_id"     => $_SESSION['user']['id'],
        "user_name"   => $_SESSION['user']['name'] ?? 'Valued Customer',
        "rating"      => $rating,
        "comment"     => htmlspecialchars($comment, ENT_QUOTES, 'UTF-8'),
        "timestamp"   => time(),
        "admin_reply" => "" // Initially blank until the admin replies from admin/index.php
    ];

    // Push new entry to our local array storage list and write it out
    $allReviews[] = $newReviewRecord;
    file_put_contents($reviewsFile, json_encode($allReviews, JSON_PRETTY_PRINT));
    
    echo json_encode(['success' => true]);
    exit();
}
