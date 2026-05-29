<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate an admin CSRF token if one does not exist inside the session
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$jsonFile = __DIR__ . '/../products.json';
$uploadDir = __DIR__ . '/../uploads/';

// Automatically create uploads folder if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// --- SECURE GLOBAL POLICY EDITOR INTERCEPTOR (RUNS OUTSIDE PAGE FILTERS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_restaurant_policies') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'])) {
        die("Security Error: CSRF token validation failed on admin policy update route.");
    }
    $policiesFile = __DIR__ . '/../policies.json';
    if (file_exists($policiesFile)) {
        $currentPolicies = json_decode(file_get_contents($policiesFile), true) ?? [];
        $targetKeys = ['privacy_policy', 'delivery_policy', 'refund_policy', 'terms'];
        foreach ($targetKeys as $key) {
            if (isset($_POST[$key . '_content'])) {
                $currentPolicies[$key]['content'] = trim($_POST[$key . '_content']);
            }
        }
        file_put_contents($policiesFile, json_encode($currentPolicies, JSON_PRETTY_PRINT));
        header("Location: index.php?page=policies&save_success=1");
        exit;
    }
}

// Handle data changes (Create, Update, Delete) when managing the menu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'menu') {
    
    // --- VERIFY BACKEND CSRF TOKEN ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'])) {
        die("Security Error: CSRF token authorization failed on item transaction entry route.");
    }

    $action = $_POST['action'] ?? 'add';
    
    // Read current database state
    $currentData = [];
    if (file_exists($jsonFile)) {
        $currentData = json_decode(file_get_contents($jsonFile), true) ?? [];
    }

    // --- DELETE ITEM FUNCTIONALITY ---
    if ($action === 'delete') {
        $deleteId = (int)($_POST['item_id'] ?? 0);
        $filteredData = [];
        
        foreach ($currentData as $item) {
            if ((int)$item['id'] === $deleteId) {
                // Remove associated image file if it isn't the default placeholder
                if (!empty($item['image']) && $item['image'] !== 'uploads/default.png') {
                    $targetFile = __DIR__ . '/../' . $item['image'];
                    if (file_exists($targetFile)) {
                        @unlink($targetFile);
                    }
                }
                continue; 
            }
            $filteredData[] = $item;
        }
        
        file_put_contents($jsonFile, json_encode($filteredData, JSON_PRETTY_PRINT));
        header("Location: index.php?page=menu");
        exit;
    }

    // --- ADD OR UPDATE ITEM FUNCTIONALITY ---
    $itemName = $_POST['item_name'] ?? '';
    $itemDesc = $_POST['item_desc'] ?? '';
    $itemPrice = $_POST['item_price'] ?? '';
    $itemOldPrice = $_POST['item_old_price'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $itemCategory = $_POST['item_category'] ?? 'Main Course';
    
    // Default placeholder or existing image fallback
    $imagePath = 'uploads/default.png';
    if ($action === 'edit') {
        foreach ($currentData as $item) {
            if ((int)$item['id'] === (int)$itemId) {
                $imagePath = $item['image'];
                break;
            }
        }
    }
    
    // Handle Image Upload
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['item_image']['tmp_name'];
        $fileName = $_FILES['item_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $newFileName = time() . '_' . preg_replace("/[^a-zA-Z0-9]/", "", $itemName) . '.' . $fileExtension;
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                if ($action === 'edit' && !empty($imagePath) && $imagePath !== 'uploads/default.png') {
                    $oldFile = __DIR__ . '/../' . $imagePath;
                    if (file_exists($oldFile)) {
                        @unlink($oldFile);
                    }
                }
                $imagePath = 'uploads/' . $newFileName;
            }
        }
    }
    if (!empty($itemName) && !empty($itemPrice)) {
        $words = explode(' ', $itemDesc);
        if (count($words) > 50) {
            $itemDesc = implode(' ', array_slice($words, 0, 50)) . '...';
        }

        $payload = [
            'id' => ($action === 'edit') ? (int)$itemId : time(),
            'name' => htmlspecialchars($itemName),
            'desc' => htmlspecialchars($itemDesc),
            'price' => number_format((float)$itemPrice, 2, '.', ''),
            'old_price' => !empty($itemOldPrice) ? number_format((float)$itemOldPrice, 2, '.', '') : '',
            'image' => $imagePath,
            'category' => htmlspecialchars($itemCategory)
        ];

        if ($action === 'edit') {
            foreach ($currentData as $index => $item) {
                if ((int)$item['id'] === (int)$itemId) {
                    $currentData[$index] = $payload;
                    break;
                }
            }
        } else {
            $currentData[] = $payload;
        }
        
        file_put_contents($jsonFile, json_encode($currentData, JSON_PRETTY_PRINT));
        header("Location: index.php?page=menu");
        exit;
    }
}

// ==========================================================================
// 🛠️ NEW: REVIEWS SUB-ACTION INTERCEPTORS (REPLY & DELETE HANDLERS)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page === 'reviews') {
    
    // 🔒 Verify Admin CSRF token signature parameters
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['admin_csrf_token'], $_POST['csrf_token'])) {
        die("Security Error: CSRF token authorization failed on review transaction entry route.");
    }

    $reviewsFile = __DIR__ . '/../reviews.json';
    $action = $_POST['action'] ?? '';
    $targetReviewId = trim((string)($_POST['target_review_id'] ?? ''));

    if (!empty($targetReviewId)) {
        // Read existing customer database state
        $allReviews = file_exists($reviewsFile) ? json_decode(file_get_contents($reviewsFile), true) : [];
        if (!is_array($allReviews)) {
            $allReviews = [];
        }

        // ---- BRANCH A: PROCESS MANAGEMENT RESPONSE REPLIES ----
        if ($action === 'submit_admin_review_reply') {
            $adminReplyText = trim($_POST['admin_reply_text'] ?? '');
            
            if (!empty($adminReplyText)) {
                foreach ($allReviews as &$rev) {
                    // Match the alphanumeric REV-XXXXXXXX string identification token exactly
                    if (trim((string)($rev['id'] ?? '')) === $targetReviewId) {
                        $rev['admin_reply'] = htmlspecialchars($adminReplyText, ENT_QUOTES, 'UTF-8');
                        break;
                    }
                }
                file_put_contents($reviewsFile, json_encode($allReviews, JSON_PRETTY_PRINT));
            }
            header("Location: index.php?page=reviews");
            exit;
        }

        // ---- BRANCH B: REMOVE CORRUPTED/SPAM REVIEWS PERMANENTLY ----
        if ($action === 'delete_admin_review_entry') {
            $filteredReviews = array_filter($allReviews, function($rev) use ($targetReviewId) {
                return trim((string)($rev['id'] ?? '')) !== $targetReviewId;
            });
            
            // Re-index array sequence back into clean row items before structural save
            $allReviews = array_values($filteredReviews);
            file_put_contents($reviewsFile, json_encode($allReviews, JSON_PRETTY_PRINT));
            header("Location: index.php?page=reviews");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Custom Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <!-- 🚀 INJECT LOCAL OFFLINE WORD-STYLE TEXT EDITING TOOLBARS INFRASTRUCTURE -->
    <script src="https://cloudflare.com" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
</head>
<body>

    <div class="wp-sidebar">
        <div class="logo">My Restaurant</div>
        <ul>
            <li class="<?php echo $page == 'dashboard' ? 'active' : ''; ?>"><a href="index.php?page=dashboard">Dashboard</a></li>
            <li class="<?php echo $page == 'orders' ? 'active' : ''; ?>"><a href="index.php?page=orders">Orders</a></li>
            <li class="<?php echo $page == 'menu' ? 'active' : ''; ?>"><a href="index.php?page=menu">Manage Menu</a></li>
            <li class="<?php echo $page == 'reviews' ? 'active' : ''; ?>"><a href="index.php?page=reviews">Reviews</a></li>
            <!-- 🛠️ ADDED: Secure Navigation Link to your dynamic Legal Page Editor -->
            <li class="<?php echo $page == 'policies' ? 'active' : ''; ?>"><a href="index.php?page=policies">Manage Policies</a></li>
        </ul>
    </div>

    <div class="wp-main-wrapper">
        <div class="wp-topbar">
            <div><a href="../menu.php" target="_blank" style="color:#fff; text-decoration:none;">Visit Customer Site</a></div>
            <div>Howdy, Admin</div>
        </div>

        <div class="wp-content">
            <?php
            if ($page == 'dashboard') {
                $totalItems = 0;
                if (file_exists($jsonFile)) {
                    $items = json_decode(file_get_contents($jsonFile), true) ?: [];
                    $totalItems = count($items);
                }
                echo "<div><h1>Dashboard Overview</h1></div><br>";
                echo "<div class='card-grid'>
                        <div class='card'><h3>Live Menu Items</h3><p style='font-size:24px; font-weight:bold; color:#2271b1;'>$totalItems</p></div>
                        <div class='card'><h3>Today's Orders</h3><p style='font-size:24px; font-weight:bold; color:#2271b1;'>0</p></div>
                        <div class='card'><h3>Total Earnings</h3><p style='font-size:24px; font-weight:bold; color:#46b450;'>Ksh 0.00</p></div>
                      </div>";
            } elseif ($page == 'orders') {
                echo "<div><h1>Live Kitchen Orders</h1></div><p>Waiting for incoming customer submissions...</p>";
            } 
            
            elseif ($page == 'menu') {
                echo "<div><h1>Menu Item Management</h1></div>";
                ?>
                <form class="wp-form" method="POST" action="index.php?page=menu" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['admin_csrf_token']; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label for="item_name">Food Name</label>
                        <input type="text" id="item_name" name="item_name" required placeholder="e.g., Traditional Ugali & Sukuma">
                    </div>

                    <div class="form-group">
                        <label for="main_item_category">Menu Classification Category</label>
                        <select name="item_category" id="main_item_category" style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 3px; background: #fff;">
                            <option value="Breakfast">Breakfast</option>
                            <option value="Main Course" selected>Main Course</option>
                            <option value="Drinks">Drinks</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="item_desc">Description (Max 50 Words)</label>
                        <textarea id="item_desc" name="item_desc" placeholder="e.g., Nicely simmered home made like Ugali accompanied with fresh greens..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="item_old_price">Old Price (Ksh) <span style="font-weight:normal; color:#8c8f94;">[Optional Sale]</span></label>
                            <input type="number" id="item_old_price" name="item_old_price" step="0.01" placeholder="e.g., 250">
                        </div>
                        <div class="form-group">
                            <label for="item_price">Current New Price (Ksh)</label>
                            <input type="number" id="item_price" name="item_price" step="0.01" required placeholder="e.g., 200">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="item_image">Upload Product Image</label>
                        <input type="file" id="item_image" name="item_image" accept="image/*">
                    </div>

                    <button type="submit" class="wp-btn">Add Item to Menu</button>
                </form>

                <div><h2>Current Restaurant Menu</h2></div>
                <table class="wp-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Food Item Name & Description</th>
                            <th>Category</th>
                            <th>Price Configuration</th>
                            <th style="text-align: right; padding-right: 20px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (file_exists($jsonFile)) {
                            $items = json_decode(file_get_contents($jsonFile), true) ?: [];
                            foreach ($items as $item) {
                                $imgSrc = '../' . $item['image'];
                                $attrId = $item['id'];
                                $attrName = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
                                $attrDesc = htmlspecialchars($item['desc'], ENT_QUOTES, 'UTF-8');
                                $attrCategory = htmlspecialchars($item['category'] ?? 'Main Course', ENT_QUOTES, 'UTF-8');
                                $attrPrice = $item['price'];
                                $attrOldPrice = $item['old_price'] ?? '';

                                echo "<tr>";
                                echo "<td><img src='{$imgSrc}' class='prod-img' alt='Food Pic'></td>";
                                echo "<td>
                                        <strong>{$item['name']}</strong>
                                        <div class='desc-text'>{$item['desc']}</div>
                                      </td>";
                                echo "<td><span style='background:#eaeaea; padding:3px 8px; border-radius:3px; font-size:12px;'>{$attrCategory}</span></td>";
                                echo "<td>";
                                if (!empty($item['old_price'])) {
                                    echo "<span class='old-price'>Ksh {$item['old_price']}</span> ";
                                }
                                echo "<span class='new-price'>Ksh " . htmlspecialchars($item['price']) . "</span>";
                                echo "</td>";
                                echo "<td style='text-align: right; vertical-align: middle; padding-right: 20px;'>
                                        <button class='wp-btn btn-edit' 
                                            data-id='{$attrId}' 
                                            data-name='{$attrName}' 
                                            data-desc='{$attrDesc}' 
                                            data-category='{$attrCategory}'
                                            data-price='{$attrPrice}' 
                                            data-oldprice='{$attrOldPrice}'>Edit</button>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No items added yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <?php
            } 
            
            elseif ($page == 'reviews') {
                echo "<div><h1>Customer Reviews & Comments</h1></div>";
                $reviewsFile = __DIR__ . '/../reviews.json';
                $productsFile = __DIR__ . '/../products.json';
                
                $allReviews = [];
                if (file_exists($reviewsFile)) {
                    $allReviews = json_decode(file_get_contents($reviewsFile), true) ?? [];
                }
                $allProductsList = [];
                if (file_exists($productsFile)) {
                    $allProductsList = json_decode(file_get_contents($productsFile), true) ?? [];
                }

                $allReviews = array_reverse($allReviews);

                if (!empty($allReviews)) {
                    echo "<div style='display:flex; flex-direction:column; gap:16px;'>";
                    foreach ($allReviews as $rev) {
                        $starsDisplay = str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']);
                        $formattedDate = date('M d, Y \a\t g:i a', $rev['timestamp']);
                        
                        $matchedFoodName = "Unknown Dish Profile Ref";
                        $matchedFoodImgPath = "uploads/default.png";
                        foreach ($allProductsList as $prod) {
                            if ((int)$prod['id'] === (int)$rev['dish_id']) {
                                $matchedFoodName = $prod['name'];
                                $matchedFoodImgPath = $prod['image'];
                                break;
                            }
                        }
                        
                        echo "<div class='card' style='padding:16px; background:#fff; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.05); display:flex; flex-direction:column; gap:12px; box-sizing:border-box;'>";
                        echo "<div style='display:flex; flex-direction:row; gap:16px; align-items:flex-start; width:100%; position:relative;'>";
                            echo "<img src='../" . htmlspecialchars($matchedFoodImgPath) . "' style='width:70px; height:70px; object-fit:cover; border-radius:6px; border:1px solid #eee; flex-shrink:0;' alt='Dish Pic'>";
                            echo "<div style='display:flex; flex-direction:column; gap:4px; align-items:flex-start;'>";
                                echo "<strong style='font-size:14px; color:#000; margin:0; line-height:1.2;'>" . htmlspecialchars($rev['user_name']) . " <span style='font-weight:normal; color:#646970;'>on</span> " . htmlspecialchars($matchedFoodName) . "</strong>";
                                echo "<div style='color:#ffcc33; font-size:14px; line-height:1.2; letter-spacing:0.5px;'>{$starsDisplay}</div>";
                                echo "<p style='margin:0; color:#333; line-height:1.4; font-size:14px; font-family:inherit; text-align:left;'>" . htmlspecialchars($rev['comment']) . "</p>";
                            echo "</div>"; 
                            
                            echo "<div style='position:absolute; top:0; right:0; display:flex; gap:12px; align-items:center;'>";
                                echo "<span style='color:#8c8f94; font-size:12px;'>{$formattedDate}</span>";
                                
                                // FIX: Isolated explicitly named identification scopes to prevent variable bleed
                                echo "<form method='POST' action='index.php?page=reviews' onsubmit='return confirm(\"Are you sure you want to permanently delete this customer review?\");' style='margin:0; padding:0; display:inline;'>";
                                    echo "<input type='hidden' name='csrf_token' value='" . $_SESSION['admin_csrf_token'] . "'>";
                                    echo "<input type='hidden' name='action' value='delete_admin_review_entry'>";
                                    echo "<input type='hidden' name='target_review_id' value='" . trim((string)$rev['id']) . "'>";
                                    echo "<button type='submit' style='background:none; border:none; color:tomato; cursor:pointer; font-size:13px; font-weight:bold; padding:0; text-decoration:none;'>Delete</button>";
                                echo "</form>";
                            echo "</div>"; 
                        echo "</div>"; 
                        
                        echo "<div style='width:100%; margin-top:4px;'>";
                            if (!empty($rev['admin_reply'])) {
                                echo "<div style='background:#f6f7f7; padding:10px 15px; border-radius:4px; border-left:3px solid #2271b1; font-size:13px; width:100%; box-sizing:border-box;'>";
                                    echo "<strong>Your Response:</strong> " . htmlspecialchars($rev['admin_reply']);
                                echo "</div>";
                            } else {
                                // FIX: Enforced unique target identity bindings for reviews separate from textboxes
                                echo "<form method='POST' action='index.php?page=reviews' style='display:flex; gap:10px; width:100%; max-width:600px; margin:0;'>";
                                    echo "<input type='hidden' name='csrf_token' value='" . $_SESSION['admin_csrf_token'] . "'>";
                                    echo "<input type='hidden' name='action' value='submit_admin_review_reply'>";
                                    echo "<input type='hidden' name='target_review_id' value='" . trim((string)$rev['id']) . "'>";
                                    echo "<input type='text' name='admin_reply_text' required placeholder='Type your official chef response...' style='flex-grow:1; padding:8px 12px; border:1px solid #8c8f94; border-radius:4px; font-size:13px; outline:none; font-family:inherit;'>";
                                    echo "<button type='submit' class='wp-btn' style='padding:8px 16px; font-size:12px; background:#2271b1; border-radius:4px; border:none; color:#fff; font-weight:bold; cursor:pointer; flex-shrink:0;'>Reply</button>";
                                echo "</form>";
                            }
                        echo "</div>"; 
                        echo "</div>"; 
                    }
                    echo "</div>";
                } else {
                    echo "<div class='card' style='padding:20px; background:#fff; border-radius:5px;'>";
                    echo "<h3>Recent Storefront Ratings</h3>";
                    echo "<p style='color:#646970; font-style:italic;'>No reviews have been posted by customers yet.</p>";
                    echo "</div>";
                }
            }

            // ==================================================================
            // 🛠️ DYNAMIC LEGAL POLICIES COMPONENT SYSTEM TEXT EDITOR
            // ==================================================================
            elseif ($page == 'policies') {
                $policiesFile = __DIR__ . '/../policies.json';
                $policiesData = [];
                if (file_exists($policiesFile)) {
                    $policiesData = json_decode(file_get_contents($policiesFile), true) ?? [];
                }
                echo "<div><h1>Manage Site Policies & Legal Files</h1></div>";

                if (isset($_GET['save_success'])) {
                    echo "<div class='notice notice-success' style='padding:12px; background:#d4edda; color:#155724; border-left:4px solid #28a745; margin-bottom:20px; border-radius:4px; font-weight:bold;'>";
                    echo "🎉 All policy updates have been published successfully to the live storefront!";
                    echo "</div>";
                }

                echo "<form method='POST' action='index.php?page=policies' style='display:flex; flex-direction:column; gap:24px; max-width:900px;'>";
                echo "<input type='hidden' name='csrf_token' value='" . $_SESSION['admin_csrf_token'] . "'>";
                echo "<input type='hidden' name='action' value='save_restaurant_policies'>";

                $keysToRender = [
                    'privacy_policy'  => '🔒 Privacy Policy File Content',
                    'delivery_policy' => '📦 Delivery Policy File Content',
                    'refund_policy'   => '💰 Refund & Cancellation Guidelines',
                    'terms'           => '📋 General Terms & Conditions'
                ];

                foreach ($keysToRender as $key => $labelTitle) {
                    $contentValue = $policiesData[$key]['content'] ?? '';
                    echo "<div class='card' style='padding:20px; background:#fff; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.05); display:flex; flex-direction:column; gap:10px;'>";
                    echo "<h3 style='margin:0; color:#222; font-size:16px; font-weight:bold; border-bottom:1px dashed #ddd; padding-bottom:8px;'>{$labelTitle}</h3>";
                    
                    // FIX: Removed the conflicting required field parameters that lock execution on review form submission
                    echo "<textarea id='{$key}_content' name='{$key}_content' rows='10' style='width:100%; padding:12px; box-sizing:border-box;'> " . htmlspecialchars($contentValue) . "</textarea>";
                    echo "</div>";
                }

                echo "<div style='display:flex; justify-content:flex-start;'>";
                echo "<button type='submit' class='wp-btn' style='padding:12px 30px; font-size:14px; background:#2271b1; border:none; border-radius:6px; color:#fff; font-weight:bold; cursor:pointer; box-shadow:0 2px 4px rgba(0,0,0,0.1); text-transform:uppercase;'>Publish All Policy Files</button>";
                echo "</div>";
                echo "</form>";
            }
            ?>
        </div>
    </div>

    <!-- Edit Window Popup Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-window">
            <div class="modal-header">
                <h2>Modify Menu Item Content</h2>
                <span class="modal-close" id="closeModalBtn">&times;</span>
            </div>
            
            <form id="editForm" method="POST" action="index.php?page=menu" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['admin_csrf_token']; ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="item_id" id="modal_item_id">

                <div class="form-group">
                    <label for="modal_item_name">Food Name</label>
                    <input type="text" id="modal_item_name" name="item_name" required>
                </div>
                <div class="form-group">
                    <label for="modal_item_category">Menu Classification Category</label>
                    <select name="item_category" id="modal_item_category" style="width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 3px; background: #fff;">
                        <option value="Breakfast">Breakfast</option>
                        <option value="Main Course">Main Course</option>
                        <option value="Drinks">Drinks</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modal_item_desc">Description (Max 50 Words)</label>
                    <textarea id="modal_item_desc" name="item_desc"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="modal_item_old_price">Old Price (Ksh) <span style="font-weight:normal; color:#8c8f94;">[Optional]</span></label>
                        <input type="number" id="modal_item_old_price" name="item_old_price" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="modal_item_price">Current Price (Ksh)</label>
                        <input type="number" id="modal_item_price" name="item_price" step="0.01" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="modal_item_image">Replace Product Image <span style="font-weight:normal; color:#8c8f94;">[Leave empty to keep current]</span></label>
                    <input type="file" id="modal_item_image" name="item_image" accept="image/*">
                </div>

                <div class="modal-actions-wrapper">
                    <button type="submit" class="wp-btn btn-save">Save System Changes</button>
                </div>
            </form>

            <div class="modal-delete-section">
                <div id="deleteInitialState" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <p class="warning-text"><strong>Warning:</strong> This action cannot be undone.</p>
                    <button type="button" id="triggerDeleteConfirm" class="wp-btn btn-delete-confirm">Delete Item Completely</button>
                </div>

                <div id="deleteConfirmState" style="display: none; justify-content: space-between; align-items: center; width: 100%;">
                    <p class="warning-text" style="font-weight: bold;">Are you absolutely sure?</p>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" id="cancelDeleteBtn" class="wp-btn" style="background: #646970; padding: 8px 14px; font-size: 12px;">No, Cancel</button>
                        <form method="POST" action="index.php?page=menu">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['admin_csrf_token']; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="item_id" id="modal_delete_id">
                            <button type="submit" class="wp-btn btn-delete-confirm">Yes, Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ==================================================================
        // 🍔 1. MENU MANAGEMENT MODAL WORKFLOW HANDLERS (PRESERVED)
        // ==================================================================
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('editModal');
            const closeBtn = document.getElementById('closeModalBtn');
            const editButtons = document.querySelectorAll('.btn-edit');

            const modalId = document.getElementById('modal_item_id');
            const modalDeleteId = document.getElementById('modal_delete_id');
            const modalName = document.getElementById('modal_item_name');
            const modalDesc = document.getElementById('modal_item_desc');
            const modalCategory = document.getElementById('modal_item_category');
            const modalPrice = document.getElementById('modal_item_price');
            const modalOldPrice = document.getElementById('modal_item_old_price');

            const deleteInitialState = document.getElementById('deleteInitialState');
            const deleteConfirmState = document.getElementById('deleteConfirmState');
            const triggerDeleteConfirm = document.getElementById('triggerDeleteConfirm');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

            function resetDeleteUI() {
                deleteInitialState.style.display = 'flex';
                deleteConfirmState.style.display = 'none';
            }

            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modalId.value = this.dataset.id;
                    modalDeleteId.value = this.dataset.id;
                    modalName.value = this.dataset.name;
                    modalDesc.value = this.dataset.desc;
                    modalPrice.value = this.dataset.price;
                    modalOldPrice.value = this.dataset.oldprice;
                    
                    if (this.dataset.category && modalCategory) {
                        modalCategory.value = this.dataset.category;
                    }
                    
                    resetDeleteUI();
                    modal.classList.add('active');
                });
            });

            triggerDeleteConfirm.addEventListener('click', function() {
                deleteInitialState.style.display = 'none';
                deleteConfirmState.style.display = 'flex';
            });

            cancelDeleteBtn.addEventListener('click', resetDeleteUI);
            closeBtn.addEventListener('click', () => modal.classList.remove('active'));
            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // ==================================================================
        // 🎯 2. MICROSOFT WORD-STYLE POLICY EDITOR INITIALIZATION
        // ==================================================================
        if (typeof CKEDITOR !== 'undefined') {
            const editorConfig = {
                height: 280,
                removeButtons: 'About,Maximize,Source',
                toolbarGroups: [
                    { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                    { name: 'paragraph', groups: [ 'list', 'align' ] },
                    { name: 'styles', groups: [ 'styles' ] },
                    { name: 'colors', groups: [ 'colors' ] }
                ]
            };

            // Bind Word editing controls to your 4 unique content panels instantly
            CKEDITOR.replace('privacy_policy_content', editorConfig);
            CKEDITOR.replace('delivery_policy_content', editorConfig);
            CKEDITOR.replace('refund_policy_content', editorConfig);
            CKEDITOR.replace('terms_content', editorConfig);
        }
    </script>
</body>
</html>
