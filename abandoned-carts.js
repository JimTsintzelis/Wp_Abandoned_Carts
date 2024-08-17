document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("modal");
    const closeModal = document.getElementById("close-modal");

    if (closeModal) {
        closeModal.addEventListener("click", function() {
            modal.style.display = "none";
        });
    }

    const rows = document.querySelectorAll(".cart-row");
    rows.forEach((row) => {
        const viewCartButton = row.querySelector(".view-cart-button");


        viewCartButton.addEventListener("click", function(event) {
            event.preventDefault();
        
            // Use try-catch to handle potential errors in parsing JSON
            let cartData;
            try {
                cartData = JSON.parse(row.getAttribute("data-cart"));
            } catch (e) {
                console.error("Error parsing cart data:", e);
            }
        
            let cartHTML = "<h2>Cart Contents:</h2>";
        
            // Check if cartData is an array and if it's empty or null
            if (cartData && Array.isArray(cartData) && cartData.length === 0) {
                cartHTML += "<p>Your cart is empty.</p>";
            } else if (cartData) {
                // Only attempt to use cartData if it is not null
                cartHTML += "<table><thead><tr><th>Item</th><th>Quantity</th></tr></thead><tbody>";
                cartData.forEach(item => {
                    cartHTML += `<tr><td>${item.name}</td><td>${item.quantity}</td></tr>`;
                });
                cartHTML += "</tbody></table>";
            } else {
                // Handle null cartData here
                cartHTML += "<p>There was an error retrieving your cart data.</p>";
            }
        
            cartHTML += '<button class="modal-quit-button" id="quit-button">Quit</button>';
        
            document.getElementById("modal-content").innerHTML = cartHTML;
            modal.style.display = "block";
        
            // Add event listener for quit button here, after it is created
            document.getElementById("quit-button").addEventListener("click", function() {
                modal.style.display = "none";
            });
        });
        
    });
});


jQuery(document).ready(function($) {
    $('#coupon_selector').select2({
        placeholder: "Select coupons",
        allowClear: true
    });
});




document.addEventListener('DOMContentLoaded', function() {
    const viewButtons = document.querySelectorAll('.view-cart-button');

    viewButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const userId = this.closest('tr').querySelector('input[type="checkbox"]').value;
            const userEmail = this.closest('tr').querySelector('td:nth-child(3)').textContent;
            const userFirstName = this.closest('tr').querySelector('td:nth-child(4)').textContent;
            const userLastName = this.closest('tr').querySelector('td:nth-child(5)').textContent;

            // Prepare data to send
            const data = {
                action: 'fetch_email_template',
                user_id: userId,
                email: userEmail,
                first_name: userFirstName,
                last_name: userLastName,
                // Add any other data needed for the email
            };

            // AJAX request to server
            fetch(ajaxurl, { // `ajaxurl` should be defined by wp_localize_script
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8'
                },
                body: new URLSearchParams(data).toString()
            })
            .then(response => response.text())
            .then(content => {
                // Fill the textbox with the email content
                document.querySelector('#email-preview-textbox').value = content;
            })
            .catch(error => console.error('Error:', error));
        });
    });
});




