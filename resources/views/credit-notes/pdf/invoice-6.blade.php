<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@lang('ui-shared.pdf.credit_note') - {{ $creditNote->cn_number }}</title>
    @includeIf('invoices.pdf.invoice_pdf_css')
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

@php
    if (!is_null($creditNote->project_id) && !is_null($creditNote->project) && !is_null($creditNote->project->clientDetails)) {
        $client = $creditNote->project->client;
    } elseif (!is_null($creditNote->client_id)) {
        $client = $creditNote->client;
    } else {
        $client = null;
    }
@endphp

<div class="tpl6-body">
    <div class="tpl6-meta">
        <strong>@lang('app.date'):</strong>
        {{ $creditNote->issue_date->translatedFormat(company()->date_format) }}
    </div>

    @if ($client)
        <div class="tpl6-meta">
            <strong>@lang('ui-shared.pdf.billed_to'):</strong><br>
            {{ $client->name_salutation }}<br>
            @if ($client->clientDetails->company_name ?? null)
                {{ $client->clientDetails->company_name }}<br>
            @endif
            @if ($client->clientDetails->address ?? null)
                {!! nl2br(e($client->clientDetails->address)) !!}<br>
            @endif
            {{ $client->email }}
        </div>
    @endif

    <div class="tpl6-subject">
        @lang('modules.invoiceSettings.subject'): @lang('ui-shared.pdf.credit_note') ({{ $creditNote->cn_number }})
        @if ($creditNote->invoice)
            — @lang('modules.invoices.invoiceNumber'): {{ $creditNote->invoice->invoice_number }}
        @endif
    </div>

    <div class="tpl6-greeting">@lang('modules.invoiceSettings.quotationGreeting')</div>
    <div class="tpl6-intro">@lang('modules.invoiceSettings.quotationIntro')</div>

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
        @foreach ($creditNote->items->sortBy('field_order') as $item)
            @if ($item->type == 'item')
                <tr>
                    <td class="col-name word-break">{{ $item->item_name }}</td>
                    <td class="col-desc word-break">
                        @if ($item->item_summary != '')
                            {!! nl2br(pdfStripTags($item->item_summary)) !!}
                        @else
                            --
                        @endif
                        @if (($invoiceSetting->hsn_sac_code_show ?? 0) && $item->hsn_sac_code)
                            <br><span class="f-11">@lang('app.hsnSac'): {{ $item->hsn_sac_code }}</span>
                        @endif
                        @if ($item->tax_list)
                            <br><span class="f-11">@lang('modules.invoices.tax'): {{ $item->tax_list }}</span>
                        @endif
                    </td>
                    <td class="col-image">
                        @if ($item->creditNoteItemImage ?? $item->invoiceItemImage ?? null)
                            <img src="{{ ($item->creditNoteItemImage ?? $item->invoiceItemImage)->file_url }}" class="tpl6-item-image" alt="">
                        @else
                            --
                        @endif
                    </td>
                    <td class="col-price">{{ currency_format($item->unit_price, $creditNote->currency_id, false) }}</td>
                    <td class="col-qty">
                        {{ $item->quantity }}
                        @if($item->unit)
                            <br><span class="f-11">{{ $item->unit->unit_type }}</span>
                        @endif
                    </td>
                    <td class="col-total">{{ currency_format($item->amount, $creditNote->currency_id, false) }}</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    <table class="tpl6-totals">
        <tr>
            <td class="label">@lang('ui-shared.pdf.subtotal')</td>
            <td class="value">{{ currency_format($creditNote->sub_total, $creditNote->currency_id, false) }}</td>
        </tr>
        @if (($discount ?? 0) != 0 && $discount != '')
            <tr>
                <td class="label">@lang('modules.invoices.discount')</td>
                <td class="value">{{ currency_format($discount, $creditNote->currency_id, false) }}</td>
            </tr>
        @endif
        @foreach (($taxes ?? []) as $key => $tax)
            <tr>
                <td class="label">{{ $key }}</td>
                <td class="value">{{ currency_format($tax, $creditNote->currency_id, false) }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="label">@lang('modules.invoices.total') ({{ $creditNote->currency->currency_code }})</td>
            <td class="value"><strong>{{ currency_format($creditNote->total, $creditNote->currency_id, false) }}</strong></td>
        </tr>
    </table>

    @if (!empty($creditNote->note))
        <div class="tpl6-notes">
            <strong>@lang('app.note')</strong><br>
            {!! nl2br(e($creditNote->note)) !!}
        </div>
    @endif

    <div class="tpl6-notes">
        <strong>@lang('modules.invoiceSettings.invoiceTerms')</strong><br>
        {!! nl2br(e($invoiceSetting->invoice_terms)) !!}
    </div>

    @if (isset($invoiceSetting->other_info))
        <div class="tpl6-notes">{!! nl2br(e($invoiceSetting->other_info)) !!}</div>
    @endif
</div>
@endforeach
{{-- AFV (upgrade 6.0.19): end of CUS_000328 --}}

@include('invoices.pdf.partials.template-6-footer')
</body>
</html>
