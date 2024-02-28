<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@lang('modules.lead.proposal') - {{ $proposal->proposal_number }}</title>
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

<div class="tpl6-body">
    <div class="tpl6-meta">
        <strong>@lang('app.date'):</strong>
        {{ $proposal->created_at->translatedFormat($company->date_format) }}
        <span style="float:right; text-transform:uppercase; font-weight:700;">
            @lang('modules.proposal.' . $proposal->status)
        </span>
    </div>

    @if ($proposal->lead->contact && ($proposal->lead->contact->client_name || $proposal->lead->contact->client_email || $proposal->lead->contact->mobile || $proposal->lead->contact->company_name || $proposal->lead->contact->address) && ($invoiceSetting->show_client_name == 'yes' || $invoiceSetting->show_client_email == 'yes' || $invoiceSetting->show_client_phone == 'yes' || $invoiceSetting->show_client_company_name == 'yes' || $invoiceSetting->show_client_company_address == 'yes'))
        <div class="tpl6-meta">
            <strong>@lang('ui-shared.pdf.billed_to'):</strong><br>
            @if ($proposal->deal && !empty($proposal->deal->name))
                {{ $proposal->deal->name }}<br>
            @endif
            @if ($proposal->lead->contact->client_name && $invoiceSetting->show_client_name == 'yes')
                {{ $proposal->lead->contact->client_name_salutation }}<br>
            @endif
            @if ($proposal->lead->contact->company_name && $invoiceSetting->show_client_company_name == 'yes')
                {{ $proposal->lead->contact->company_name }}<br>
            @endif
            @if ($proposal->lead->contact->address && $invoiceSetting->show_client_company_address == 'yes')
                {!! nl2br(e($proposal->lead->contact->address)) !!}<br>
            @endif
            @if ($proposal->lead->contact->client_email && $invoiceSetting->show_client_email == 'yes')
                {{ $proposal->lead->contact->client_email }}<br>
            @endif
            @if ($proposal->lead->contact->mobile && $invoiceSetting->show_client_phone == 'yes')
                {{ $proposal->lead->contact->mobile }}
            @endif
        </div>
    @endif

    <div class="tpl6-subject">
        @lang('modules.invoiceSettings.subject'): @lang('modules.lead.proposal') ({{ $proposal->proposal_number }})
        @if ($proposal->valid_till)
            — @lang('modules.estimates.validTill'): {{ $proposal->valid_till->translatedFormat($company->date_format) }}
        @endif
    </div>

    <div class="tpl6-greeting">@lang('modules.invoiceSettings.quotationGreeting')</div>
    <div class="tpl6-intro">
        @if ($proposal->description)
            {!! pdfStripTags($proposal->description) !!}
        @else
            @lang('modules.invoiceSettings.quotationIntro')
        @endif
    </div>

    @if (count($proposal->items) > 0)
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
            @foreach ($proposal->items->sortBy('field_order') as $item)
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
                            @if ($item->proposalItemImage ?? $item->invoiceItemImage ?? null)
                                <img src="{{ ($item->proposalItemImage ?? $item->invoiceItemImage)->file_url }}" class="tpl6-item-image" alt="">
                            @else
                                --
                            @endif
                        </td>
                        <td class="col-price">{{ currency_format($item->unit_price, $proposal->currency_id, false) }}</td>
                        <td class="col-qty">
                            {{ $item->quantity }}
                            @if($item->unit)
                                <br><span class="f-11">{{ $item->unit->unit_type }}</span>
                            @endif
                        </td>
                        <td class="col-total">{{ currency_format($item->amount, $proposal->currency_id, false) }}</td>
                    </tr>
                @endif
            @endforeach
            </tbody>
        </table>

        <table class="tpl6-totals">
            <tr>
                <td class="label">@lang('ui-shared.pdf.subtotal')</td>
                <td class="value">{{ currency_format($proposal->sub_total, $proposal->currency_id, false) }}</td>
            </tr>
            @if ($discount != 0 && $discount != '')
                <tr>
                    <td class="label">@lang('modules.invoices.discount')</td>
                    <td class="value">{{ currency_format($discount, $proposal->currency_id, false) }}</td>
                </tr>
            @endif
            @foreach ($taxes as $key => $tax)
                <tr>
                    <td class="label">{{ $key }}</td>
                    <td class="value">{{ currency_format($tax, $proposal->currency_id, false) }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="label">@lang('modules.invoices.total') ({{ $proposal->currency->currency_code }})</td>
                <td class="value"><strong>{{ currency_format($proposal->total, $proposal->currency_id, false) }}</strong></td>
            </tr>
        </table>
    @endif

    @if (!empty($proposal->note))
        <div class="tpl6-notes">
            <strong>@lang('app.note')</strong><br>
            {!! nl2br(e($proposal->note)) !!}
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
