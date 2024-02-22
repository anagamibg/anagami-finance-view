{{-- Shared footer banner for invoice template 6 (only when uploaded) --}}
@if (!empty($invoiceSetting->template_footer_url))
    <div class="tpl6-footer">
        <img src="{{ $invoiceSetting->template_footer_url }}" alt="{{ $company->company_name ?? '' }}" class="tpl6-banner-img"/>
    </div>
@endif
