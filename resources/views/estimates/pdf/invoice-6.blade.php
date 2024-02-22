<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@lang('ui-shared.pdf.estimate') - {{ $estimate->estimate_number }}</title>
    @includeIf('estimates.pdf.estimate_pdf_css')
    @include('invoices.pdf.partials.template-6-styles')
</head>
<body>
{{-- AFV (upgrade 6.0.19): CUS_000328 dual-language PDF, as in invoice-5 - when the PDF language is not bg, the document is rendered once more in Bulgarian after a page break (one loop instead of a copied body) --}}
@foreach ($invoiceSetting->locale != 'bg' ? ['', 'bg'] : [''] as $dbkPdfCopy)
@if ($dbkPdfCopy == 'bg')
<div class="page_break"></div>
@php App::setLocale('bg'); @endphp
@endif
@include('invoices.pdf.partials.template-6-banners')

<div class="tpl6-body">
    <div class="tpl6-meta">
        <strong>@lang('app.date'):</strong>
        {{ $estimate->created_at->translatedFormat(($company ?? $settings)->date_format) }}
        <span style="float:right; text-transform:uppercase; font-weight:700;">
            @lang('modules.estimates.' . $estimate->status)
        </span>
    </div>

    @if (
        ($estimate->client || $estimate->clientDetails)
        && ($estimate->client->name || $estimate->client->email || $estimate->client->mobile || ($estimate->clientDetails->company_name ?? null) || ($estimate->clientDetails->address ?? null))
        && ($invoiceSetting->show_client_name == 'yes' || $invoiceSetting->show_client_email == 'yes' || $invoiceSetting->show_client_phone == 'yes' || $invoiceSetting->show_client_company_name == 'yes' || $invoiceSetting->show_client_company_address == 'yes')
    )
        <div class="tpl6-meta">
            <strong>@lang('ui-shared.pdf.billed_to'):</strong><br>
            @if ($estimate->client && $estimate->client->name && $invoiceSetting->show_client_name == 'yes')
                {{ $estimate->client->name_salutation }}<br>
            @endif
            @if ($estimate->clientDetails && $estimate->clientDetails->company_name && $invoiceSetting->show_client_company_name == 'yes')
                {{ $estimate->clientDetails->company_name }}<br>
            @endif
            @if ($estimate->clientDetails && $estimate->clientDetails->address && $invoiceSetting->show_client_company_address == 'yes')
                {!! nl2br(e($estimate->clientDetails->address)) !!}<br>
            @endif
            @if ($estimate->client && $estimate->client->email && $invoiceSetting->show_client_email == 'yes')
                {{ $estimate->client->email }}<br>
            @endif
            @if ($estimate->client && $estimate->client->mobile && $invoiceSetting->show_client_phone == 'yes')
                {{ $estimate->client->mobile_with_phonecode }}
            @endif
            @if ($invoiceSetting->show_gst == 'yes' && !is_null($estimate->clientDetails->gst_number ?? null))
                <br>{{ $estimate->client->clientDetails->tax_name ?? __('ui-shared.pdf.tax_id') }}: {{ $estimate->clientDetails->gst_number }}
            @endif
        </div>
    @endif

    <div class="tpl6-subject">
        @lang('modules.invoiceSettings.subject'): @lang('ui-shared.pdf.estimate') ({{ $estimate->estimate_number }})
        @if ($estimate->valid_till)
            — @lang('modules.estimates.validTill'): {{ $estimate->valid_till->translatedFormat(($company ?? $settings)->date_format) }}
        @endif
    </div>

    <div class="tpl6-greeting">@lang('modules.invoiceSettings.quotationGreeting')</div>
    <div class="tpl6-intro">
        @if ($estimate->description)
            {!! nl2br(pdfStripTags($estimate->description)) !!}
        @else
            @lang('modules.invoiceSettings.quotationIntro')
        @endif
    </div>

    <table class="tpl6-table">
        <thead>
        <tr>
            <th class="col-name">@lang('app.product')</th>
            <th class="col-desc">@lang('app.description')</th>
            <th class="col-image">@lang('app.image')</th>
            <th class="col-price">@lang('modules.invoices.unitPrice')</th>
            <th class="col-qty">@lang('modules.invoices.qty')</th>
            <th class="col-total">@lang('ui-shared.pdf.amount')</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($estimate->items->sortBy('field_order') as $item)
            @if ($item->type == 'item')
                <tr>
                    <td class="col-name word-break">{{ $item->item_name }}</td>
                    <td class="col-desc word-break">
                        @if ($item->item_summary != '')
                            {!! nl2br(pdfStripTags($item->item_summary)) !!}
                        @else
                            --
                        @endif
                        @if ($invoiceSetting->hsn_sac_code_show && $item->hsn_sac_code)
                            <br><span class="f-11">@lang('app.hsnSac'): {{ $item->hsn_sac_code }}</span>
                        @endif
                        @if ($item->tax_list)
                            <br><span class="f-11">@lang('modules.invoices.tax'): {{ $item->tax_list }}</span>
                        @endif
                    </td>
                    <td class="col-image">
                        @if ($item->estimateItemImage)
                            <img src="{{ $item->estimateItemImage->file_url }}" class="tpl6-item-image" alt="">
                        @else
                            --
                        @endif
                    </td>
                    <td class="col-price">{{ currency_format($item->unit_price, $estimate->currency_id, false) }}</td>
                    <td class="col-qty">
                        {{ $item->quantity }}
                        @if($item->unit)
                            <br><span class="f-11">{{ $item->unit->unit_type }}</span>
                        @endif
                    </td>
                    <td class="col-total">{{ currency_format($item->amount, $estimate->currency_id, false) }}</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    <table class="tpl6-totals">
        <tr>
            <td class="label">@lang('ui-shared.pdf.subtotal')</td>
            <td class="value">{{ currency_format($estimate->sub_total, $estimate->currency_id, false) }}</td>
        </tr>
        @if ($discount != 0 && $discount != '')
            <tr>
                <td class="label">
                    @lang('modules.invoices.discount')
                    @if($estimate->discount_type == 'percent')
                        ({{ $estimate->discount }}%)
                    @endif
                </td>
                <td class="value">{{ currency_format($discount, $estimate->currency_id, false) }}</td>
            </tr>
        @endif
        @foreach ($taxes as $key => $tax)
            <tr>
                <td class="label">{{ $key }}</td>
                <td class="value">{{ currency_format($tax, $estimate->currency_id, false) }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="label">@lang('modules.invoices.total') ({{ $estimate->currency->currency_code }})</td>
            <td class="value"><strong>{{ currency_format($estimate->total, $estimate->currency_id, false) }}</strong></td>
        </tr>
    </table>

    @if ($estimate->note != '')
        <div class="tpl6-notes">
            <strong>@lang('app.note')</strong><br>
            {!! nl2br(e($estimate->note)) !!}
        </div>
    @endif

    @if ($estimate->sign)
        <div class="tpl6-notes">
            <strong>@lang('app.signature')</strong><br>
            <img src="{{ $estimate->sign->signature }}" style="height: 75px;"><br>
            ({{ $estimate->sign->full_name }})
        </div>
    @endif

    <div class="tpl6-notes">
        <strong>@lang('modules.invoiceSettings.invoiceTerms')</strong><br>
        {!! nl2br(e($invoiceSetting->estimate_terms ?: $invoiceSetting->invoice_terms)) !!}
    </div>

    @if (isset($invoiceSetting->other_info))
        <div class="tpl6-notes">{!! nl2br(e($invoiceSetting->other_info)) !!}</div>
    @endif

    @if (isset($taxes) && $invoiceSetting->tax_calculation_msg == 1)
        <div class="tpl6-notes">
            @if ($estimate->calculate_tax == 'after_discount')
                @lang('messages.calculateTaxAfterDiscount')
            @else
                @lang('messages.calculateTaxBeforeDiscount')
            @endif
        </div>
    @endif

    @if (isset($fields) && count($fields) > 0)
        <div class="page_break"></div>
        <div class="tpl6-section-title">@lang('modules.projects.otherInfo')</div>
        @foreach ($fields as $field)
            <div class="tpl6-notes">
                <strong>{{ $field->label }}</strong><br>
                @if ($field->type == 'text' || $field->type == 'password' || $field->type == 'number' || $field->type == 'textarea')
                    {{ $estimate->custom_fields_data['field_' . $field->id] ?? '-' }}
                @elseif($field->type == 'radio')
                    {{ !is_null($estimate->custom_fields_data['field_' . $field->id] ?? null) ? $estimate->custom_fields_data['field_' . $field->id] : '-' }}
                @elseif($field->type == 'select')
                    {{ !is_null($estimate->custom_fields_data['field_' . $field->id] ?? null) && $estimate->custom_fields_data['field_' . $field->id] != '' ? $field->values[$estimate->custom_fields_data['field_' . $field->id]] : '-' }}
                @elseif($field->type == 'checkbox')
                    {{ !is_null($estimate->custom_fields_data['field_' . $field->id] ?? null) ? $estimate->custom_fields_data['field_' . $field->id] : '-' }}
                @elseif($field->type == 'date')
                    {{ !is_null($estimate->custom_fields_data['field_' . $field->id] ?? null) ? \Carbon\Carbon::parse($estimate->custom_fields_data['field_' . $field->id])->translatedFormat($estimate->company->date_format) : '--' }}
                @endif
            </div>
        @endforeach
    @endif
</div>
@endforeach
{{-- AFV (upgrade 6.0.19): end of CUS_000328 --}}

@include('invoices.pdf.partials.template-6-footer')
</body>
</html>
