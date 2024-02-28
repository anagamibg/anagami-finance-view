<style>
    /* AFV (upgrade 6.0.19): colours through the --dbk-* tokens (S9 dark theme), the upstream values are the fallbacks */
    .bootstrap-select:has(#add-products) .dropdown-menu {
        min-width: 380px !important;
    }

    .bootstrap-select .dropdown-menu.inner {
        max-width: 100%;
    }

    .bootstrap-select .dropdown-menu li a.dropdown-item,
    .bootstrap-select .dropdown-menu li a {
        padding: 8px 12px !important;
    }

    .bootstrap-select .dropdown-menu li a .text {
        width: 100%;
        display: block;
    }

    .product-select-option {
        display: flex !important;
        align-items: center;
        gap: 10px;
        width: 100% !important;
        min-height: 44px;
        line-height: 1.25;
        white-space: normal !important;
        box-sizing: border-box;
    }

    .product-select-thumb-wrap {
        flex: 0 0 40px;
        width: 40px;
        height: 40px;
    }

    .product-select-thumb {
        display: block;
        width: 40px !important;
        height: 40px !important;
        border-radius: 6px;
        object-fit: cover;
        background: var(--dbk-surface-2, #f4f5f7);
        border: 1px solid var(--dbk-border, #e8e8e8);
    }

    .product-select-thumb-empty {
        display: flex !important;
        align-items: center;
        justify-content: center;
        color: var(--dbk-text-muted, #99a5b5);
        font-size: 16px;
        background: var(--dbk-surface-2, #f4f5f7);
    }

    .product-select-meta {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
        flex: 1 1 auto;
        gap: 2px;
        overflow: hidden;
    }

    .product-select-name {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--dbk-text, #28313c);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .product-select-sku {
        display: block;
        font-size: 11px;
        color: var(--dbk-text-muted, #7a869a);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .product-select-price {
        flex: 0 0 auto;
        margin-left: auto;
        padding-left: 12px;
        font-size: 13px;
        font-weight: 700;
        color: var(--dbk-text, #28313c);
        white-space: nowrap;
        text-align: right;
    }

    #add-products + .dropdown-toggle .filter-option-inner-inner {
        display: flex;
        align-items: center;
    }

    #add-products + .dropdown-toggle .filter-option-inner-inner .product-select-option {
        min-height: auto;
        gap: 8px;
    }

    #add-products + .dropdown-toggle .filter-option-inner-inner .product-select-thumb-wrap,
    #add-products + .dropdown-toggle .filter-option-inner-inner .product-select-thumb,
    #add-products + .dropdown-toggle .filter-option-inner-inner .product-select-thumb-empty {
        width: 24px !important;
        height: 24px !important;
        flex-basis: 24px;
        font-size: 11px;
    }

    #add-products + .dropdown-toggle .filter-option-inner-inner .product-select-name {
        max-width: 150px;
        font-size: 13px;
        font-weight: 500;
    }

    #add-products + .dropdown-toggle .filter-option-inner-inner .product-select-sku,
    #add-products + .dropdown-toggle .filter-option-inner-inner .product-select-price {
        display: none;
    }

    .bootstrap-select .dropdown-menu .dropdown-item.active .product-select-name,
    .bootstrap-select .dropdown-menu .dropdown-item:active .product-select-name,
    .bootstrap-select .dropdown-menu .dropdown-item.active .product-select-sku,
    .bootstrap-select .dropdown-menu .dropdown-item:active .product-select-sku,
    .bootstrap-select .dropdown-menu .dropdown-item.active .product-select-price,
    .bootstrap-select .dropdown-menu .dropdown-item:active .product-select-price {
        color: #fff !important;
    }

    .bootstrap-select .dropdown-menu .dropdown-item.active .product-select-thumb-empty,
    .bootstrap-select .dropdown-menu .dropdown-item:active .product-select-thumb-empty {
        color: #fff;
        border-color: rgba(255, 255, 255, .35);
        background: rgba(255, 255, 255, .12);
    }

    .bootstrap-select .dropdown-menu .dropdown-item.active .product-select-thumb,
    .bootstrap-select .dropdown-menu .dropdown-item:active .product-select-thumb {
        border-color: rgba(255, 255, 255, .45);
    }
</style>
