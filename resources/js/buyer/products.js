
const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        const payload = JSON.parse(element.textContent || '');
        return payload && typeof payload === 'object' ? payload : null;
    } catch {
        return null;
    }
};

const initProducts = () => {
    const sizeSelect = document.getElementById('size_id');
    const cartQty = document.getElementById('cart-quantity');
    const cartBtn = document.getElementById('cart-btn');
    const buyBtn = document.getElementById('buy-btn');
    const mainProductImage = document.getElementById('main-product-image');
    const productData = readPayload('product-page-data');
    const thumbs = document.querySelectorAll('.variation-thumb');

    const productMainImageUrl =
        typeof productData?.mainImageUrl === 'string'
            ? productData.mainImageUrl
            : mainProductImage?.getAttribute('src') || '';

    const payloadVariations = Array.isArray(productData?.variations)
        ? productData.variations
        : [];

    const variations = payloadVariations.length > 0
        ? payloadVariations
        : Array.from(thumbs, (thumb) => ({
            id: thumb.getAttribute('data-variation-id'),
            stock: Number(thumb.getAttribute('data-variation-stock')) || 0,
            price: Number(thumb.getAttribute('data-variation-price')) || 0,
            originalPrice:
                Number(thumb.getAttribute('data-variation-original-price')) || 0,
            image:
                thumb.getAttribute('data-variation-image') || productMainImageUrl,
            name: thumb.getAttribute('data-variation-name') || ''
        }));

    const quantityMax = Number(cartQty?.max);
    const productStock = Number(productData?.stock);

    const fallbackStock = Number.isFinite(productStock)
        ? productStock
        : Number.isFinite(quantityMax)
            ? quantityMax
            : 0;

    let selectedVariationId = null;

    const hasMultipleVariations = () => variations.length > 1;

    const computeStockLimit = () => {
        if (variations.length > 0 && selectedVariationId === null) {
            return 0;
        }

        const limits = [];

        if (selectedVariationId !== null) {
            const variation = variations.find(
                (item) => String(item.id) === String(selectedVariationId)
            );

            if (!variation) return 0;

            const stock = Number(variation.stock);

            if (!Number.isFinite(stock)) return 0;

            limits.push(Math.max(0, Math.floor(stock)));
        } else {
            limits.push(Math.max(0, Math.floor(fallbackStock)));
        }

        if (sizeSelect && sizeSelect.value) {
            const option = sizeSelect.options[sizeSelect.selectedIndex];
            const rawStock = option?.dataset.stock;

            if (rawStock !== undefined && rawStock !== '') {
                const stock = Number(rawStock);

                if (Number.isFinite(stock)) {
                    limits.push(Math.max(0, Math.floor(stock)));
                }
            }
        }

        return limits.length ? Math.min(...limits) : 0;
    };

    const updateButtons = () => {
        const limit = computeStockLimit();

        const variationRequired = variations.length > 0 && selectedVariationId === null;

        const canPurchase = limit > 0 && !variationRequired;

        if (cartBtn) cartBtn.disabled = !canPurchase;
        if (buyBtn) buyBtn.disabled = !canPurchase;
    };

    const normalizeQuantity = () => {
        const limit = computeStockLimit();

        if (!cartQty) return limit > 0 ? 1 : 0;

        cartQty.max = String(limit);
        cartQty.disabled = limit <= 0;

        if (limit <= 0) {
            cartQty.value = '0';
            return 0;
        }

        const entered = Number(cartQty.value);

        const quantity = Number.isFinite(entered)
            ? Math.min(limit, Math.max(1, Math.floor(entered)))
            : 1;

        cartQty.value = String(quantity);

        return quantity;
    };

    const updateQuantityLimit = () => {
        const variationRequired =
            variations.length > 0 && selectedVariationId === null;

        if (cartQty) {
            cartQty.placeholder = variationRequired
                ? 'Select a variant first'
                : 'Enter quantity';

            if (variationRequired) {
                cartQty.value = '';
                cartQty.max = '0';
                cartQty.disabled = true;
            } else {
                normalizeQuantity();
            }
        }

        updateButtons();
    };

    if (cartQty) {
        cartQty.addEventListener('input', () => {
            if (cartQty.value === '') return;
            normalizeQuantity();
        });

        cartQty.addEventListener('change', updateQuantityLimit);
    }

    const syncHiddenSize = () => {
        const value = sizeSelect ? sizeSelect.value : '';

        const cartSize = document.getElementById('cart-size-id');
        const buySize = document.getElementById('buy-size-id');

        if (cartSize) cartSize.value = value;
        if (buySize) buySize.value = value;

        updateQuantityLimit();
    };

    const updateStockDisplay = (stock) => {
        const stockDisplay = document.getElementById('stock-display');
        const stockAmount = document.getElementById('stock-amount');

        if (!stockDisplay || !stockAmount) return;

        stockDisplay.className = '';

        if (stock > 0) {
            stockDisplay.className = 'text-muted d-block';
            stockAmount.textContent =
                `${stock} ${stock === 1 ? 'unit' : 'units'} available`;
        } else {
            stockDisplay.className = 'badge bg-danger rounded-pill';
            stockDisplay.textContent = 'Sold Out';
        }
    };

    window.selectVariation = (id, stock, price, originalPrice, image, name) => {
        thumbs.forEach((thumb) => {
            thumb.classList.remove('variation-selected');
        });

        const clicked = Array.from(thumbs).find(
            (thumb) => thumb.getAttribute('data-variation-id') === String(id)
        );

        if (clicked) clicked.classList.add('variation-selected');

        selectedVariationId = id;

        const selectedVariation =
            document.getElementById('selected-variation-id');
        const cartVariation =
            document.getElementById('cart-variation-id');
        const buyVariation =
            document.getElementById('buy-variation-id');

        if (selectedVariation) selectedVariation.value = id;
        if (cartVariation) cartVariation.value = id;
        if (buyVariation) buyVariation.value = id;

        if (mainProductImage && image) {
            mainProductImage.src = image;
            mainProductImage.style.opacity = '0';

            setTimeout(() => {
                mainProductImage.style.opacity = '1';
            }, 150);
        }

        const displayPrice = document.getElementById('display-price');
        const originalPriceElement =
            document.getElementById('original-price');
        const discountBadge =
            document.getElementById('discount-badge');

        const currentPrice = parseFloat(price);
        const previousPrice = parseFloat(originalPrice);

        if (displayPrice && Number.isFinite(currentPrice)) {
            displayPrice.textContent = `\u20B1${currentPrice.toFixed(2)}`;

            const hasDiscount =
                Number.isFinite(previousPrice) &&
                previousPrice > 0 &&
                currentPrice < previousPrice;

            if (hasDiscount) {
                displayPrice.classList.add('text-danger');

                if (originalPriceElement) {
                    originalPriceElement.textContent = `\u20B1${previousPrice.toFixed(2)}`;
                    originalPriceElement.style.display = '';
                }

                if (discountBadge) {
                    const discountPercent = Math.round(
                        (1 - currentPrice / previousPrice) * 100
                    );

                    discountBadge.textContent = `-${discountPercent}%`;
                    discountBadge.style.display = '';
                }
            } else {
                displayPrice.classList.remove('text-danger');

                if (originalPriceElement) {
                    originalPriceElement.style.display = 'none';
                }

                if (discountBadge) {
                    discountBadge.style.display = 'none';
                }
            }
        }

        updateStockDisplay(stock);

        const info = document.getElementById('selected-variant-info');
        const nameElement = document.getElementById('selected-variant-name');

        if (info && nameElement) {
            nameElement.textContent = name;
            info.style.display = 'block';
        }

        updateQuantityLimit();
    };

    const submitPurchase = (formId, quantityId) => {
        if (variations.length > 0 && selectedVariationId === null) {
            window.alert('Please select a product option first.');
            return;
        }

        const quantity = normalizeQuantity();

        if (quantity <= 0) {
            window.alert('This product is currently out of stock.');
            updateButtons();
            return;
        }

        const hiddenQuantity = document.getElementById(quantityId);

        if (hiddenQuantity) {
            hiddenQuantity.value = String(quantity);
        }

        document.getElementById(formId)?.submit();
    };

    window.addToCart = () => {
        submitPurchase('cart-form', 'cart-submit-qty');
    };

    window.placeOrder = () => {
        submitPurchase('buynow-form', 'buy-quantity');
    };

    if (sizeSelect) {
        sizeSelect.addEventListener('change', syncHiddenSize);
        syncHiddenSize();
    }

    thumbs.forEach((thumb) => {
        thumb.addEventListener('click', () => {
            const id = thumb.getAttribute('data-variation-id');
            const stock = parseInt(
                thumb.getAttribute('data-variation-stock'),
                10
            );

            const price = parseFloat(
                thumb.getAttribute('data-variation-price')
            );

            const originalPrice =
                parseFloat(
                    thumb.getAttribute('data-variation-original-price')
                ) || price;

            const image =
                thumb.getAttribute('data-variation-image') ||
                productMainImageUrl;

            const name =
                thumb.getAttribute('data-variation-name') || '';

            window.selectVariation(
                id,
                stock,
                price,
                originalPrice,
                image,
                name
            );
        });
    });

    updateQuantityLimit();

    const modal = document.getElementById('secondaryImageModal');
    const bootstrapApi = window.bootstrap;

    if (modal && bootstrapApi && bootstrapApi.Carousel) {
        modal.addEventListener('shown.bs.modal', () => {
            const carouselElement =
                document.getElementById('lightbox-carousel');

            if (!carouselElement) return;

            const carousel =
                bootstrapApi.Carousel.getOrCreateInstance(carouselElement);

            const items =
                carouselElement.querySelectorAll('.carousel-item');

            const currentIndexElement =
                document.getElementById('current-image-index');

            const updateIndex = () => {
                const active =
                    carouselElement.querySelector('.carousel-item.active');

                if (active && currentIndexElement) {
                    currentIndexElement.textContent = String(
                        Array.from(items).indexOf(active) + 1
                    );
                }
            };

            carousel.cycle();
            updateIndex();

            carouselElement.addEventListener(
                'slide.bs.carousel',
                updateIndex
            );
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProducts, {
        once: true
    });
} else {
    initProducts();
}

export { initProducts };
