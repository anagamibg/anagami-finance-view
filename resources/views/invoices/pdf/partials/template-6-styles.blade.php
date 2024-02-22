<style>
    @page {
        margin: 0;
    }

    body {
        margin: 0;
        padding: 0;
        color: #222;
        background: #fff;
        font-size: 12px;
    }

    .tpl6-banner-img {
        width: 100%;
        height: auto;
        display: block;
        border: 0;
    }

    .tpl6-header {
        width: 100%;
        margin: 0;
        padding: 0;
    }

    .tpl6-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        width: 100%;
    }

    .tpl6-body {
        padding: 18px 36px {{ !empty($invoiceSetting->template_footer_url) ? '110px' : '24px' }} 36px;
    }

    .tpl6-meta {
        margin-bottom: 14px;
        line-height: 1.5;
    }

    .tpl6-subject {
        font-weight: 700;
        text-decoration: underline;
        margin: 12px 0;
    }

    .tpl6-greeting {
        margin: 10px 0 6px;
    }

    .tpl6-intro {
        margin: 0 0 14px;
        line-height: 1.5;
    }

    .tpl6-section-title {
        text-align: center;
        font-weight: 700;
        text-decoration: underline;
        margin: 12px 0 10px;
        font-size: 13px;
    }

    .tpl6-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .tpl6-table th,
    .tpl6-table td {
        border: 1px solid #222;
        padding: 6px 8px;
        vertical-align: top;
        font-size: 11px;
    }

    .tpl6-table th {
        background: #f3f3f3;
        font-weight: 700;
        text-align: center;
    }

    .tpl6-table .col-name {
        width: 16%;
        text-align: center;
        font-weight: 700;
    }

    .tpl6-table .col-desc {
        width: 34%;
        text-align: left;
    }

    .tpl6-table .col-image {
        width: 16%;
        text-align: center;
    }

    .tpl6-table .col-price,
    .tpl6-table .col-qty,
    .tpl6-table .col-total {
        width: 11%;
        text-align: center;
    }

    .tpl6-item-image {
        max-width: 70px;
        max-height: 70px;
    }

    .tpl6-totals {
        width: 45%;
        margin-left: auto;
        margin-top: 10px;
        border-collapse: collapse;
    }

    .tpl6-totals td {
        border: 1px solid #222;
        padding: 5px 8px;
        font-size: 11px;
    }

    .tpl6-totals .label {
        text-align: right;
        font-weight: 700;
        width: 55%;
        background: #f7f7f7;
    }

    .tpl6-totals .value {
        text-align: right;
        width: 45%;
    }

    .tpl6-notes {
        margin-top: 14px;
        font-size: 11px;
        line-height: 1.45;
        color: #444;
    }

    .tpl6-right {
        text-align: right;
    }

    .page_break {
        page-break-before: always;
    }

    .word-break {
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: normal;
    }

    .f-11 { font-size: 11px; }
    .f-12 { font-size: 12px; }
    .f-13 { font-size: 13px; }
    .mb-0 { margin-bottom: 0; }
    .mt-1 { margin-top: 8px; }

    @if(($invoiceSetting->locale ?? '') == 'th')
        table td, .description {
            font-weight: bold !important;
            font-size: 16px !important;
        }
    @endif
</style>
