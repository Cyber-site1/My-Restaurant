<?php
// Pull loop parameters from master array definitions
$dish_id = $item['id'];
$price = !empty($item['price']) ? (float)$item['price'] : 0.00;
// 🚀 FIX 1: Extract the old price parameter safely if it exists
$old_price = !empty($item['old_price']) ? (float)$item['old_price'] : null;
$category_tag = !empty($item['category']) ? $item['category'] : 'Main Course';

$image_url = '../' . $item['image'];
if (empty($item['image']) || !file_exists(__DIR__ . '/../' . $item['image'])) {
    $image_url = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23eaeaea"/></svg>';
}

$is_fav = in_array((int)$dish_id, array_map('intval', $userWishlist));
?>

<!-- Live Dynamic Card Container Block with Required Javascript Hooks -->
<div class="menu-card-item menu-card-item-node" 
     data-id="<?php echo htmlspecialchars($dish_id); ?>" 
     data-name="<?php echo htmlspecialchars($item['name']); ?>" 
     data-price="<?php echo htmlspecialchars($price); ?>" 
     data-category="<?php echo htmlspecialchars($category_tag); ?>">
    
    <!-- Card Header Image Element Container -->
    <div class="menu-card-image-box" style="background-image: url('<?php echo htmlspecialchars($image_url); ?>');">
        
        <!-- 🚀 FIX 2: Dynamic Conditional Price Tray to render strikethroughs -->
        <span class="menu-card-price-tag" style="position: absolute; bottom: 12px; left: 12px; background: rgba(51,51,51,0.85); padding: 4px 10px; font-weight: bold; border-radius: 6px; font-size: 12px; font-family: Arial; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: auto; right: auto; display: flex; align-items: center; gap: 8px;">
            <?php if ($old_price && $old_price > $price): ?>
                <!-- Strikethrough Layout Element -->
                <span style="text-decoration: line-through; color: #b2bec3; font-size: 11px;">
                    KSh <?php echo number_format($old_price, 2); ?>
                </span>
            <?php endif; ?>
            <span style="color: #ffcc33;">
                KSh <?php echo number_format($price, 2); ?>
            </span>
        </span>
        
        <!-- Floating Heart Icon -->
        <?php if (isset($_SESSION['user'])): ?>
            <a href="menu.php?toggle-wishlist=<?php echo htmlspecialchars($dish_id); ?>" class="wishlist-floating-heart" title="<?php echo $is_fav ? 'Remove from Wish List' : 'Save to Wish List'; ?>">
                <span><?php echo $is_fav ? "❤️" : "🤍"; ?></span>
            </a>
        <?php else: ?>
            <a href="login.php" class="wishlist-floating-heart" title="Login to save item">
                <span>🤍</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Details Metadata Content Block -->
    <div class="menu-card-content-tray">
        <div class="title-desc-bound-box">
            <h3 class="menu-item-title-heading"><?php echo htmlspecialchars($item['name']); ?></h3>
            <div class="menu-item-excerpt-text"><?php echo htmlspecialchars($item['desc']); ?></div>
        </div>

        <div class="menu-card-action-footer-row">
            <div class="ratings-stars-align-group" style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                <?php
                // --- 🧮 MATHEMATICAL AVERAGE STAR RATING CALCULATOR ---
                $reviewsFilePath = __DIR__ . '/../reviews.json';
                $reviewsDataset = file_exists($reviewsFilePath) ? json_decode(file_get_contents($reviewsFilePath), true) : [];

                $matchingRatings = [];
                if (is_array($reviewsDataset)) {
                    foreach ($reviewsDataset as $entry) {
                        if (isset($entry['dish_id']) && (int)$entry['dish_id'] === (int)$item['id']) {
                            $matchingRatings[] = (int)$entry['rating'];
                        }
                    }
                }

                $reviewsCount = count($matchingRatings);
                if ($reviewsCount > 0) {
                    $mathMeanAverage = array_sum($matchingRatings) / $reviewsCount;
                    $displayStarsInteger = (int)round($mathMeanAverage);
                } else {
                    $mathMeanAverage = 5.0;
                    $displayStarsInteger = 5;
                }

                $starsVisualRow = str_repeat('★', $displayStarsInteger) . str_repeat('☆', 5 - $displayStarsInteger);
                ?>

                <!-- Visual Dynamic Stars Display -->
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span class="menu-card-stars" style="color: #ffcc33;" title="Average Rating: <?php echo number_format($mathMeanAverage, 1); ?> Stars">
                        <?php echo $starsVisualRow; ?>
                    </span>
                    <span class="reviews-count-text" style="color: #8c8f94; font-size: 12px;">
                        (<?php echo $reviewsCount; ?>)
                    </span>
                </div>
                
                <!-- SINGLE CLEAN CLICK TRIGGER WORD LINK -->
                <span class="review-popup-trigger-btn" data-dish-id="<?php echo (int)$item['id']; ?>" data-dish-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" style="color: tomato; font-size: 12px; font-weight: bold; cursor: pointer; text-decoration: underline; display: inline-block;">
                    Review
                </span>
            </div>  
            <a href="menu.php?add-to-cart=<?php echo htmlspecialchars($dish_id); ?>" class="storefront-order-action-btn">
                Order
            </a>
        </div>
    </div>
</div>