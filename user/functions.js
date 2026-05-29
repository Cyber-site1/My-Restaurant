/* ======================================================
   HEADER FUNCTIONS
====================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const triggerButton = document.getElementById('userMenuTriggerButton');
    const dropdownMenu = document.getElementById('userHeaderVerticalDropdown');

    if (triggerButton && dropdownMenu) {
        triggerButton.addEventListener('click', function (event) {
            event.stopPropagation();
            dropdownMenu.classList.toggle('show-dropdown');
        });

        document.addEventListener('click', function (event) {
            if (!dropdownMenu.contains(event.target) && !triggerButton.contains(event.target)) {
                dropdownMenu.classList.remove('show-dropdown');
            }
        });
    }
});

/* ======================================================
   ACCOUNT DETAILS FUNCTIONS (PART 1 & 2)
====================================================== */
document.addEventListener('DOMContentLoaded', function() {
    const eyeToggles = document.querySelectorAll('.password-reveal-eye');
    eyeToggles.forEach(eye => {
        eye.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetField = document.getElementById(targetId);
            if (!targetField) return;

            if (targetField.type === "password") {
                targetField.type = "text";
                this.innerText = "🙈";
            } else {
                targetField.type = "password";
                this.innerText = "👁";
            }
        });
    });

    const newPasswordInput = document.getElementById('new_password');
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const pass = this.value;
            const wrapper = document.getElementById('shopStrengthWrapper');
            const bar = document.getElementById('shopStrengthBar');
            const text = document.getElementById('shopStrengthText');

            if (!wrapper || !bar || !text) return;

            if (pass.length === 0) {
                wrapper.style.display = 'none';
                return;
            }
            wrapper.style.display = 'block';

            let score = 0;
            if (pass.length >= 6) score++;
            if (/[A-Za-z]/.test(pass)) score++;
            if (/[0-9]/.test(pass)) score++;
            if (/[^A-Za-z0-9]/.test(pass)) score++;

            if (pass.length < 6) {
                bar.style.width = '25%';
                bar.style.backgroundColor = '#e74c3c';
                text.innerText = 'Too Short (Min 6 characters)';
                text.style.color = '#e74c3c';
            } else if (score < 4) {
                bar.style.width = '60%';
                bar.style.backgroundColor = '#f39c12';
                text.innerText = 'Weak (Must mix letters, numbers, and symbols)';
                text.style.color = '#f39c12';
            } else {
                bar.style.width = '100%';
                bar.style.backgroundColor = '#2ecc71';
                text.innerText = 'Strong Password';
                text.style.color = '#2ecc71';
            }
        });
    }

    const deleteForm = document.getElementById('deleteAccountForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(event) {
            const firstConfirm = confirm("Are you absolutely sure you want to delete your account?\n\nThis will restrict your access immediately.");
            if (firstConfirm) {
                const secondConfirm = confirm("FINAL WARNING:\n\nProceed to delete this profile permanently?");
                if (secondConfirm) {
                    return true;
                }
            }
            event.preventDefault();
            return false;
        });
    }
});

/* ======================================================
   SHOPPING CART ITEM INCREMENTOR FUNCTIONS
====================================================== */
document.addEventListener('DOMContentLoaded', function() {
    const minusButtons = document.querySelectorAll('.btn-qty-minus');
    minusButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentNode.querySelector('.input-qty-field');
            if (input && input.form) {
                let val = parseInt(input.value);
                if (!isNaN(val) && val > 1) {
                    input.value = val - 1;
                    input.form.submit();
                }
            }
        });
    });

    const plusButtons = document.querySelectorAll('.btn-qty-plus');
    plusButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentNode.querySelector('.input-qty-field');
            if (input && input.form) {
                let val = parseInt(input.value);
                if (!isNaN(val) && val < 20) {
                    input.value = val + 1;
                    input.form.submit();
                }
            }
        });
    });
});

/* ======================================================
   IN-BUILT WISHLIST NOTIFICATION DRAWER
====================================================== */
document.addEventListener("DOMContentLoaded", function() {
    // Only run if the PHP session flagged a successful addition
    if (window.showWishlistNotification) {
        // 1. Create the notification element dynamically
        const toast = document.createElement("div");
        toast.innerText = "🛒 Item added in cart";
        
        // 2. Style it perfectly so it overrides any global stylesheet conflicts
        Object.assign(toast.style, {
            position: "fixed",
            top: "20px",
            left: "50%",
            transform: "translateX(-50%) translateY(-20px)",
            backgroundColor: "#2c3e50",
            color: "#ffffff",
            padding: "12px 24px",
            borderRadius: "30px",
            fontWeight: "bold",
            fontSize: "14px",
            boxShadow: "0 4px 15px rgba(0,0,0,0.2)",
            zIndex: "99999",
            opacity: "0",
            transition: "all 0.4s ease",
            fontFamily: "Arial, sans-serif"
        });

        // 3. Inject it into the page body
        document.body.appendChild(toast);

        // 4. Animate it into view immediately
        setTimeout(() => {
            toast.style.opacity = "1";
            toast.style.transform = "translateX(-50%) translateY(0)";
        }, 50);

        // 5. Smoothly fade it out and remove it after 3 seconds
        setTimeout(() => {
            toast.style.opacity = "0";
            toast.style.transform = "translateX(-50%) translateY(-20px)";
            setTimeout(() => toast.remove(), 400);
        }, 3000);
    }
});

/* ======================================================
   ORDER HISTORY INTERCEPTOR
====================================================== */
document.addEventListener('DOMContentLoaded', function() {
    const cancelOrderButtons = document.querySelectorAll('.btn-order-cancel-intercept');
    cancelOrderButtons.forEach(btn => {
        btn.addEventListener('click', function(event) {
            const confirmed = confirm("Are you sure you want to request cancellation for this order?");
            if (!confirmed) {
                event.preventDefault();
                return false;
            }
        });
    });
});

/* ======================================================
   BULK CANCELLED ORDERS ACTION CONTROLLER MODULE
====================================================== */
document.addEventListener('DOMContentLoaded', function() {
    const rowCheckboxes = document.querySelectorAll('.check-order-row-item');
    const actionDeleteBar = document.getElementById('shopDeleteBar');
    const bulkTriggerBtn = document.querySelector('.btn-bulk-delete-trigger');

    if (rowCheckboxes.length > 0) {
        function refreshDeleteBarState() {
            let anyChecked = false;
            rowCheckboxes.forEach(box => {
                if (box.checked) anyChecked = true;
            });
            if (actionDeleteBar) {
                actionDeleteBar.style.display = anyChecked ? 'block' : 'none';
            }
        }

        rowCheckboxes.forEach(box => {
            box.addEventListener('change', refreshDeleteBarState);
        });
    }

    if (bulkTriggerBtn && rowCheckboxes.length > 0) {
        bulkTriggerBtn.addEventListener('click', function() {
            const selectedIds = [];
            rowCheckboxes.forEach(box => {
                if (box.checked) {
                    selectedIds.push(box.value);
                }
            });

            if (selectedIds.length === 0) return;

            const isConfirmed = confirm("Are you sure you want to permanently delete the selected cancelled orders?");
            if (isConfirmed) {
                window.location.href = "cancelled.php?action=delete_cancelled&orders=" + encodeURIComponent(selectedIds.join(','));
            }
        });
    }
});

/* ======================================================
   CHECKOUT INTERACTIVE CONTROL MODULE (FIXED)
====================================================== */
let isPhoneInputValid = false;

function validatePhoneLive() {
    const field = document.getElementById('mpesa_phone');
    const label = document.getElementById('phoneValidationError');
    if (!field || !label) return;
    
    const val = field.value.trim();

    if (val.length === 0) {
        field.style.setProperty('border', '2px solid #ccc', 'important');
        field.style.setProperty('background-color', '#ffffff', 'important');
        label.style.display = 'none';
        isPhoneInputValid = false;
        return;
    }

    const charValid = /^[0-9+]+$/.test(val);
    const prefixValid = val.startsWith('07') || val.startsWith('01') || val.startsWith('+254');
    const exactPattern = /^(?:\+2547\d{8}|\+2541\d{8}|07\d{8}|01\d{8})$/;

    if (!charValid || !prefixValid || (val.length >= 10 && !exactPattern.test(val))) {
        field.style.setProperty('border', '2px solid tomato', 'important');
        field.style.setProperty('background-color', '#fff5f5', 'important');
        label.style.display = 'block';
        isPhoneInputValid = false;
    } else {
        field.style.setProperty('border', '2px solid #2ecc71', 'important'); 
        field.style.setProperty('background-color', '#ffffff', 'important');
        label.style.display = 'none';
        isPhoneInputValid = !!exactPattern.test(val);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const phoneInputNode = document.getElementById('mpesa_phone');
    const triggerSubmitBtn = document.querySelector('.btn-checkout-submit-trigger');
    const successModalBackdrop = document.getElementById('shopSuccessModal');
    const modalRedirectActionBtn = document.querySelector('.btn-modal-order-history-redirect');
    const coreBillingForm = document.getElementById('shopMpesaForm');

    if (phoneInputNode) {
        phoneInputNode.addEventListener('input', validatePhoneLive);
    }

    if (triggerSubmitBtn) {
        triggerSubmitBtn.addEventListener('click', function(e) {
            const addressField = document.getElementById('delivery_address');
            const messageOutputNode = document.getElementById('checkoutMessage');
            if (!messageOutputNode) return;

            messageOutputNode.style.display = 'none';

            if (!addressField || addressField.value.trim() === "" || !phoneInputNode || !phoneInputNode.value.trim()) {
                messageOutputNode.innerText = "Please completely fill out your delivery address and phone fields before submitting.";
                messageOutputNode.style.display = 'block';
                return;
            }

            if (!isPhoneInputValid) {
                phoneInputNode.style.setProperty('border', '2px solid tomato', 'important');
                phoneInputNode.style.setProperty('background-color', '#fff5f5', 'important');
                const label = document.getElementById('phoneValidationError');
                if (label) label.style.display = 'block';
                phoneInputNode.focus();
                return;
            }

            messageOutputNode.innerText = "Connecting to Safaricom, please wait...";
            messageOutputNode.style.display = 'block';

            setTimeout(function() {
                messageOutputNode.style.display = 'none';
                if (successModalBackdrop) {
                    successModalBackdrop.style.display = 'flex';
                }
            }, 1500);
        });
    }

    if (modalRedirectActionBtn) {
        modalRedirectActionBtn.addEventListener('click', function() {
            if (coreBillingForm) coreBillingForm.submit();
        });
    }
});

// ==========================================================================
// 📱 REAL-TIME CHAR-BY-CHAR KENYAN MOBILE VALIDATION (01, 07, +2541, +2547)
// ==========================================================================

/**
 * Validates the phone string structure progressively as characters are added.
 * @param {string} phone - The current value typed in the input bar.
 * @returns {boolean} True if the current progress is correct (Green), False if it breaks rules (Red).
 */
function checkProgressiveValidity(phone) {
    if (phone === "") return true;

    // RULE: Reject letters or invalid symbols instantly anywhere
    if (!/^\+?[0-9]*$/.test(phone)) {
        return false;
    }

    // --- CHARACTER POSITION 1 CHECK ---
    if (phone.length >= 1) {
        let char1 = phone.charAt(0);
        if (char1 !== '0' && char1 !== '+') {
            return false; // Error if it doesn't start with 0 or +
        }
    }

    // --- EVALUATE SEQUENCES FOR NUMBERS STARTING WITH '0' ---
    if (phone.startsWith('0')) {
        if (phone.length >= 2) {
            let char2 = phone.charAt(1);
            if (char2 !== '1' && char2 !== '7') {
                return false; // Error if second number is not 1 or 7
            }
        }
        if (phone.length > 10) {
            return false; // Error if local number exceeds 10 digits
        }
        return true;
    }

    // --- EVALUATE SEQUENCES FOR NUMBERS STARTING WITH '+' ---
    if (phone.startsWith('+')) {
        // Progressive matching for +2, +25, +254
        if (phone.length >= 2 && phone.charAt(1) !== '2') return false;
        if (phone.length >= 3 && phone.charAt(2) !== '5') return false;
        if (phone.length >= 4 && phone.charAt(3) !== '4') return false;

        // Check the prefix digit immediately following +254 (position 5 in the string)
        if (phone.length >= 5) {
            let prefixDigit = phone.charAt(4);
            if (prefixDigit !== '1' && prefixDigit !== '7') {
                return false; // Error if number after +254 is not 1 or 7
            }
        }
        if (phone.length > 13) {
            return false; // Error if international number exceeds 13 characters (inc. +)
        }
        return true;
    }

    return true;
}

/**
 * Checks if the number has reached its absolute valid completion length for form submission.
 */
function isNumberFullyComplete(phone) {
    if (phone.startsWith('0') && phone.length === 10) return true;
    if (phone.startsWith('+') && phone.length === 13) return true;
    return false;
}

/**
 * Binds the validation logic to your form input element.
 */
function initializeCheckoutPhoneValidator() {
    const phoneInput = document.getElementById("mpesa_phone");
    const errorLabel = document.getElementById("phoneValidationError");
    const submitBtn = document.getElementById("shopSubmitBtn");
    const checkoutForm = document.getElementById("shopMpesaForm");

    if (!phoneInput || !errorLabel || !submitBtn || !checkoutForm) return;

    // INCREASE THE SIZE OF THE NUMBERS HERE
    phoneInput.style.fontSize = "15px";       // Makes the numbers larger and easier to read
    phoneInput.style.padding = "11px 13px";    // Adds breathing room inside the bar for the larger text
    phoneInput.style.fontWeight = "";      // Optional: Makes the numbers bold
    
    // Lock the submit action button until a full 10/13 digit number is achieved
    submitBtn.disabled = true;

    phoneInput.addEventListener("input", function () {
        let value = phoneInput.value.trim();

        if (value === "") {
            errorLabel.style.display = "none";
            submitBtn.disabled = true;
            phoneInput.style.borderColor = "";
            phoneInput.style.color = "";
            return;
        }

        if (checkProgressiveValidity(value)) {
            // PROGRESS IS CORRECT -> TURN GREEN IMMEDIATELY
            errorLabel.style.display = "none";
            phoneInput.style.borderColor = "#39e75f";
            phoneInput.style.color = "#39e75f";

            // Only enable form submission button if the number is totally finished
            if (isNumberFullyComplete(value)) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        } else {
            // RULE VIOLATION -> FLASH RED IMMEDIATELY AND DISPLAY BOX
            errorLabel.style.display = "block";
            submitBtn.disabled = true;
            phoneInput.style.borderColor = "red";
            phoneInput.style.color = "red";
        }
    });

    submitBtn.addEventListener("click", function () {
        let value = phoneInput.value.trim();
        if (checkProgressiveValidity(value) && isNumberFullyComplete(value)) {
            checkoutForm.submit();
        } else {
            errorLabel.style.display = "block";
            phoneInput.style.borderColor = "red";
            phoneInput.style.color = "red";
            phoneInput.focus();
        }
    });
}

// ==================================================================
// 🔄 AUTOMATED REAL-TIME PAYMENT STATUS POLLING CONTROLLER
// ==================================================================

/**
 * Continuously polls the server database until an order status flips from PENDING.
 * @param {string} orderId - The unique alphanumeric ME-XXXXXXXX order reference string.
 */
function beginLiveOrderStatusPolling(orderId) {
    if (!orderId) return;

    // Check status every 2500 milliseconds (2.5 seconds)
    const checkInterval = setInterval(function () {
        
        fetch(`check-status.php?order_id=${encodeURIComponent(orderId)}`)
            .then(response => response.json())
            .then(data => {
                // When payment passes successfully or gets rejected/cancelled, break loop and refresh UI
                if (data.status === 'Paid' || data.status === 'CANCELLED_BY_USER' || data.status === 'FAILED_STK_PUSH') {
                    clearInterval(checkInterval);
                    window.location.reload(); // Instantly update user dashboard view
                }
            })
            .catch(error => {
                console.error("Polling system sync delay...", error);
            });

    }, 2500);
}

// ==================================================================
// 🛒 MENU STOREFRONT ENGINE (PRICE FILTER, SEARCH & PAGINATION)
// ==================================================================

let allMenuItems = [];
let filteredItems = [];
let currentMenuPage = 1;
let currentSelectedCategory = 'All';

/**
 * Returns 8 items on mobile screens (768px or less) or 9 on desktop screens
 */
function getItemsPerPage() {
    return window.innerWidth <= 768 ? 8 : 9;
}

// Automatically load menu array state when page mounts
document.addEventListener('DOMContentLoaded', function() {
    const grid = document.getElementById('storefrontMenuGrid');
    if (!grid) return;

    // Read initial collection data elements embedded or fetch them safely
    // To feed the engine, we read product cards from the server array
    initializeClientSideMenuData();
});

function initializeClientSideMenuData() {
    // Collect embedded card data from DOM grid layout elements safely
    const cards = document.querySelectorAll('.storefront-product-matrix > .menu-card-item-node');
    allMenuItems = [];

    cards.forEach(card => {
        allMenuItems.push({
            id: card.getAttribute('data-id'),
            name: card.getAttribute('data-name'),
            price: parseFloat(card.getAttribute('data-price')),
            category: card.getAttribute('data-category'),
            html: card.outerHTML
        });
    });

    // Mirror initial data array to filters
    filteredItems = [...allMenuItems];
    renderEngineStorefrontGrid();
}

/**
 * Filter items dynamically when range slider is moved
 */
function filterMenuByPrice(maxPriceValue) {
    const valueLabel = document.getElementById('sliderValue');
    if (valueLabel) {
        valueLabel.innerText = maxPriceValue;
    }

    applyCombinedFiltersAndSearch();
}

/**
 * Filter items when category buttons are clicked
 */
function switchCategory(categoryName) {
    currentSelectedCategory = categoryName;

    // Update active tab buttons layout styles
    const tabs = document.querySelectorAll('.category-tab-btn');
    tabs.forEach(tab => {
        if (tab.getAttribute('onclick').includes(`'${categoryName}'`)) {
            tab.classList.add('active-tab');
        } else {
            tab.classList.remove('active-tab');
        }
    });

    applyCombinedFiltersAndSearch();
}

/**
 * Filter items when characters are typed inside the input search bar
 */
function searchMenuItems() {
    applyCombinedFiltersAndSearch();
}

/**
 * Combines category tracking, text strings, and slider thresholds safely
 */
function applyCombinedFiltersAndSearch() {
    const slider = document.getElementById('priceRangeSlider');
    const searchInput = document.getElementById('menuSearchInput');
    
    const maxPrice = slider ? parseFloat(slider.value) : 3000;
    const searchString = searchInput ? searchInput.value.toLowerCase().trim() : "";

    filteredItems = allMenuItems.filter(item => {
        const matchesCategory = (currentSelectedCategory === 'All' || item.category === currentSelectedCategory);
        const matchesPrice = (item.price <= maxPrice);
        const matchesSearch = (item.name.toLowerCase().includes(searchString));
        
        return matchesCategory && matchesPrice && matchesSearch;
    });

    currentMenuPage = 1; // Reset view windows to landing segment index page 1
    renderEngineStorefrontGrid();
}

/**
 * Master Pagination Control Event Handler
 */
function changeActivePage(directionSteps) {
    const limit = getItemsPerPage();
    const totalPages = Math.ceil(filteredItems.length / limit) || 1;
    currentMenuPage += directionSteps;

    if (currentMenuPage < 1) currentMenuPage = 1;
    if (currentMenuPage > totalPages) currentMenuPage = totalPages;

    renderEngineStorefrontGrid();
}

/**
 * Re-renders the display window grid matrix instantly based on parameters
 */
function renderEngineStorefrontGrid() {
    const gridContainer = document.getElementById('storefrontMenuGrid');
    const prevBtn = document.getElementById('prevPageBtn');
    const nextBtn = document.getElementById('nextPageBtn');
    const label = document.getElementById('paginationPageLabel');

    if (!gridContainer) return;

    const limit = getItemsPerPage();
    const totalPages = Math.ceil(filteredItems.length / limit) || 1;
    if (currentMenuPage > totalPages) currentMenuPage = totalPages;

    // Slice array segment parameters cleanly using dynamic limit values
    const startIndex = (currentMenuPage - 1) * limit;
    const endIndex = startIndex + limit;
    const itemsToDisplay = filteredItems.slice(startIndex, endIndex);

    // Empty grid panel layout structure wrapper
    gridContainer.innerHTML = "";

    if (itemsToDisplay.length > 0) {
        itemsToDisplay.forEach(item => {
            gridContainer.innerHTML += item.html;
        });
    } else {
        gridContainer.innerHTML = '<p style="grid-column: span 3; text-align: center; color: #999; padding: 40px; font-family: Arial;">No dishes found matching criteria.</p>';
    }

    // Refresh UI text and control toggle switches
    if (label) {
        label.innerText = `Page ${currentMenuPage} of ${totalPages}`;
    }

    // Un-freeze and control opacity/disabled state properties for structural arrows
    if (prevBtn) {
        prevBtn.disabled = (currentMenuPage === 1);
        prevBtn.style.opacity = (currentMenuPage === 1) ? "0.5" : "1";
        prevBtn.style.cursor = (currentMenuPage === 1) ? "not-allowed" : "pointer";
    }

    if (nextBtn) {
        nextBtn.disabled = (currentMenuPage === totalPages);
        nextBtn.style.opacity = (currentMenuPage === totalPages) ? "0.5" : "1";
        nextBtn.style.cursor = (currentMenuPage === totalPages) ? "not-allowed" : "pointer";
    }
}

// Watch screen dimensions to automatically update items if a user rotates their device
window.addEventListener('resize', function() {
    renderEngineStorefrontGrid();
});

// ==================================================================
// 🔄 AUTOMATED ASYNCHRONOUS M-PESA TRANSACTION STATUS POLLING ENGINE
// ==================================================================
function startMpesaPaymentStatusTracker(generatedOrderId) {
    console.log("Initializing transaction poller for Order: " + generatedOrderId);
    
    // Set up a repeated background check loop every 2000 milliseconds (2 seconds)
    const pollingIntervalId = setInterval(function() {
        
        fetch('check-status.php?order_id=' + encodeURIComponent(generatedOrderId))
            .then(response => response.json())
            .then(data => {
                console.log("Live background transaction status update:", data.status);
                
                // Case A: Customer entered their PIN successfully and payment cleared
                if (data.status === 'Paid') {
                    clearInterval(pollingIntervalId); // Stop the background interval loop
                    
                    // Reveal your hidden congratulations modal box element on screen
                    const successModal = document.getElementById('shopSuccessModal');
                    if (successModal) {
                        successModal.style.display = 'flex';
                    }
                }
                
                // Case B: Customer intentionally declined the prompt or typed the wrong PIN
                else if (data.status === 'CANCELLED_BY_USER' || data.status === 'FAILED_STK_PUSH') {
                    clearInterval(pollingIntervalId);
                    alert("⚠️ Transaction Failed: The payment request was cancelled or timed out. Please try again.");
                    window.location.reload(); // Refresh the checkout pane to reset forms
                }
            })
            .catch(error => {
                console.error("Background status pooling glitch:", error);
            });
            
    }, 2000);

    // Safety fallback: Automatically kill the loop after 60 seconds so it doesn't run forever
    setTimeout(function() {
        clearInterval(pollingIntervalId);
    }, 60000);
}

// ==========================================================================
// 📱 ASYNCHRONOUS POPUP DIALOG CONTROL ENGINE FOR DISH REVIEWS
// ==========================================================================
let activeModalDishId = null;
let activeSelectedRating = 5;

function openDishReviewPopup(dishId, dishName) {
    activeModalDishId = dishId;
    
    const titleElement = document.getElementById('modalDishTitleName');
    const inputElement = document.getElementById('modalReviewTextInput');
    const feedElement = document.getElementById('modalReviewsScrollFeed');
    const modalWindow = document.getElementById('dishReviewPopupModal');

    if (titleElement) titleElement.innerText = dishName;
    if (inputElement) inputElement.value = "";
    
    updateModalStarHighlight(5); // Default to a 5-star start state
    
    if (feedElement) {
        feedElement.innerHTML = "<p style='text-align:center; color:#718096; font-size:13px; margin:20px 0;'>Loading feedback registry...</p>";
    }
    
    if (modalWindow) {
        modalWindow.style.display = 'flex';
    }
    
    // Fetch data asynchronously from your unified review.php script
    fetch('review.php?dish_id=' + dishId)
        .then(res => res.json())
        .then(reviews => {
            if (!feedElement) return;
            feedElement.innerHTML = "";
            
            if (reviews.length === 0) {
                feedElement.innerHTML = "<p style='text-align:center; color:#a0aec0; font-style:italic; font-size:13px; padding:20px 0;'>No reviews logged yet. Be the first to share your thoughts!</p>";
                return;
            }
            
            reviews.forEach(r => {
                let ratingStars = "★".repeat(r.rating) + "☆".repeat(5 - r.rating);
                let html = `
                    <div style="background:#f7fafc; padding:12px; border-radius:8px; border:1px solid #e2e8f0; box-sizing:border-box;">
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px; margin-bottom:4px;">
                            <strong style="color:#2d3748;">${r.user_name}</strong>
                            <span style="color:#ffcc33; letter-spacing:0.5px; font-size:14px;">${ratingStars}</span>
                        </div>
                        <p style="margin:0; font-size:13px; color:#444; text-align:left; line-height:1.4;">${r.comment}</p>
                `;
                
                // 🎯 THE HOOK DIRECTIVE SYMBOL REQUIREMENT (|--/Admin-->)
                if (r.admin_reply && r.admin_reply.trim() !== "") {
                    html += `
                        <div style="margin-top:8px; padding-left:10px; color:#2271b1; font-size:12px; line-height:1.4; text-align:left; font-family:monospace; box-sizing:border-box; word-break:break-all;">
                            └──/Admin--> <span style="font-family:Arial, sans-serif; color:#4a5568; font-weight:normal;">${r.admin_reply}</span>
                        </div>
                    `;
                }
                
                html += `</div>`;
                feedElement.innerHTML += html;
            });
        })
        .catch(err => {
            if (feedElement) feedElement.innerHTML = "<p style='text-align:center; color:tomato; font-size:13px;'>Error updating information array.</p>";
            console.error(err);
        });
}

function closeDishReviewPopup() {
    const modalWindow = document.getElementById('dishReviewPopupModal');
    if (modalWindow) {
        modalWindow.style.display = 'none';
    }
}

function updateModalStarHighlight(val) {
    activeSelectedRating = val;
    const nodes = document.querySelectorAll('.modal-star-node');
    nodes.forEach((node) => {
        let radioInput = node.previousElementSibling;
        if (radioInput && parseInt(radioInput.value) <= val) {
            node.style.color = '#ffcc33';
        } else {
            node.style.color = '#cbd5e0';
        }
    });
}

function submitModalReviewForm() {
    const inputElement = document.getElementById('modalReviewTextInput');
    const titleElement = document.getElementById('modalDishTitleName');
    
    const commentText = inputElement ? inputElement.value.trim() : "";
    if (!commentText) return;
    
    // Send data payload to review.php using JSON strings
    fetch('review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            dish_id: activeModalDishId,
            rating: activeSelectedRating,
            comment: commentText
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
        } else {
            // Live hot reload of views
            const currentTitle = titleElement ? titleElement.innerText : "";
            openDishReviewPopup(activeModalDishId, currentTitle);
            
            // Refresh parent storefront loop to trigger mean average calculation updates
            if (window.location.reload) { window.location.reload(); }
        }
    })
    .catch(err => {
        alert("Server communication breakdown processing review entry.");
        console.error(err);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // 1. Existing star radio listeners
    const starRadios = document.querySelectorAll('input[name="modal_rating"]');
    starRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            updateModalStarHighlight(parseInt(this.value));
        });
    });

    // 2. NEW: Global Click Capturer for the Review Link
    document.addEventListener('click', function(event) {
        // Check if the clicked element has our review button class
        if (event.target && event.target.classList.contains('review-popup-trigger-btn')) {
            event.preventDefault();
            
            // Extract the dish data safely from our HTML markers
            const dishId = event.target.getAttribute('data-dish-id');
            const dishName = event.target.getAttribute('data-dish-name');
            
            if (dishId && dishName) {
                // Fire up the window popup cleanly!
                openDishReviewPopup(dishId, dishName);
            }
        }
    });
});

