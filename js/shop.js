// Function to show product info from element
function showProductInfoFromElement(element) {
    // Prevent event bubbling
    event.stopPropagation();
    
    // Get data from data attributes
    const productName = element.getAttribute('data-product-name');
    const productDescription = element.getAttribute('data-product-description');
    
    Swal.fire({
        title: productName,
        html: `<div style="text-align: left; max-height: 300px; overflow-y: auto;">${productDescription.replace(/\n/g, '<br>')}</div>`,
        showCloseButton: true,
        showConfirmButton: false,
        customClass: {
            popup: 'product-info-popup'
        }
    });
}

// Function to purchase item
function purchaseItem(productId, productName, price) {
    // Logika pembelian produk
}

// Function to confirm purchase
function confirmPurchase(productId, productName, price, characterId) {
    // Logika konfirmasi pembelian
}