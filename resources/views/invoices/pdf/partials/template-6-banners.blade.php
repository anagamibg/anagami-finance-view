{{-- Shared header banner for invoice template 6 (only when uploaded) --}}
@if (!empty($invoiceSetting->template_header_url))
    <div class="tpl6-header">
        <img src="{{ $invoiceSetting->template_header_url }}" alt="{{ $company->company_name ?? '' }}" class="tpl6-banner-img"/>
    </div>
@endif
