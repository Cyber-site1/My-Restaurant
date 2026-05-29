<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<?php include 'header.php'; ?>

<div class="page-content">

    <!-- HERO SECTION -->

    <section class="shop-hero-section">

        <h2 class="hero-title">
            Fresh. Local. Delicious.
        </h2>

        <p class="hero-description">

            Welcome to shop.
            We serve authentic gourmet meals crafted daily
            with premium, fresh ingredients.

        </p>

        <a href="menu.php" class="hero-action-link">
            View Our Menu
        </a>

    </section>

    <!-- FEATURES SECTION -->

    <section class="shop-features-section"
             style="
             background-image:
             url('../uploads/1779530186_Chapati.webp');">

        <div class="features-flex-container">

            <!-- BOX 1 -->

            <div class="feature-info-box">

                <h3 class="info-box-heading">
                    Our Kitchen
                </h3>

                <p class="info-box-paragraph margin-bottom-none">

                    Every dish is carefully prepared by professional chefs.
                    Taste the difference in quality ingredients.

                </p>

            </div>

            <!-- BOX 2 -->

            <div class="feature-info-box">

                <h3 class="info-box-heading">
                    Opening Hours
                </h3>

                <p class="info-box-paragraph margin-bottom-small">

                    <strong>Monday - Friday:</strong>
                    11:00 AM - 10:00 PM

                </p>

                <p class="info-box-paragraph margin-bottom-none">

                    <strong>Saturday - Sunday:</strong>
                    12:00 PM - 11:00 PM

                </p>

            </div>

        </div>

    </section>

</div>

<!-- Put this ONLY inside user/index.php -->
<style>
    footer {
        margin-top: 40px !important;
    }
</style>

<?php include 'footer.php';?>