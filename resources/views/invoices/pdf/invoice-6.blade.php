<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@lang('app.invoice') - {{ $invoice->invoice_number }}</title>
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
    $client = null;
    if (!is_null($invoice->project) && !is_null($invoice->project->client) && !is_null($invoice->project->client->clientDetails)) {
        $client = $invoice->project->client;
    } elseif (!is_null($invoice->client_id) && !is_null($invoice->clientDetails)) {
        $client = $invoice->client;
    }

    $taxesIncluded = $taxesIncluded ?? [];
    $taxesDeducted = $taxesDeducted ?? [];
    $hasDeducted = count($taxesDeducted) > 0;
    $totalPaymentAmount = $invoice->sub_total - $discount + array_sum($taxesIncluded);
    $deductedSum = array_sum(array_map('abs', $taxesDeducted));
    $netPayableBeforeAdjustment = $hasDeducted
        ? ($totalPaymentAmount - $deductedSum)
        : ($invoice->sub_total - $discount + array_sum($taxes ?? []));
@endphp

<div class="tpl6-body">
    <div class="tpl6-meta">
        <strong>@lang('app.date'):</strong>
        {{ $invoice->issue_date->translatedFormat($invoice->company->date_format) }}
        @if ($invoiceSetting->show_status)
            <span style="float:right; text-transform:uppercase; font-weight:700;">
                @if ($invoice->credit_note)
                    @lang('ui-shared.pdf.credit_note')
                @else
                    @lang('modules.invoices.' . $invoice->status)
                @endif
            </span>
        @endif
    </div>

    @if ($client && ($invoiceSetting->show_client_name == 'yes' || $invoiceSetting->show_client_email == 'yes' || $invoiceSetting->show_client_phone == 'yes' || $invoiceSetting->show_client_company_name == 'yes' || $invoiceSetting->show_client_company_address == 'yes'))
        <div class="tpl6-meta">
            <strong>@lang('ui-shared.pdf.billed_to'):</strong><br>
            @if ($client->name && $invoiceSetting->show_client_name == 'yes')
                {{ $client->name_salutation }}<br>
            @endif
            @if ($client->clientDetails->company_name && $invoiceSetting->show_client_company_name == 'yes')
                {{ $client->clientDetails->company_name }}<br>
            @endif
            @if ($client->clientDetails->address && $invoiceSetting->show_client_company_address == 'yes')
                {!! nl2br(e($client->clientDetails->address)) !!}<br>
            @endif
            @if ($client->email && $invoiceSetting->show_client_email == 'yes')
                {{ $client->email }}<br>
            @endif
            @if ($client->mobile && $invoiceSetting->show_client_phone == 'yes')
                {{ $client->mobile_with_phonecode }}
            @endif
            @if ($invoiceSetting->show_gst == 'yes' && !is_null($client->clientDetails->gst_number))
                <br>
                @if ($client->clientDetails->tax_name)
                    {{ $client->clientDetails->tax_name }}: {{ $client->clientDetails->gst_number }}
                @else
                    @lang('ui-shared.pdf.tax_id'): {{ $client->clientDetails->gst_number }}
                @endif
            @endif
        </div>
    @endif

    <div class="tpl6-subject">
        @lang('modules.invoiceSettings.subject'): @lang('app.invoice') ({{ $invoice->invoice_number }})
        @if ($creditNote)
            / @lang('ui-shared.pdf.credit_note') {{ $creditNote->cn_number }}
        @endif
    </div>

    <div class="tpl6-greeting">@lang('modules.invoiceSettings.quotationGreeting')</div>
    <div class="tpl6-intro">@lang('modules.invoiceSettings.quotationIntro')</div>

    @if ($invoiceSetting->show_project == 1 && isset($invoice->project->project_name))
        <div class="tpl6-section-title">{{ $invoice->project->project_name }}</div>
    @endif

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
        @foreach ($invoice->items->sortBy('field_order') as $item)
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
                        @if ($item->invoiceItemImage)
                            <img src="{{ $item->invoiceItemImage->file_url }}" class="tpl6-item-image" alt="">
                        @else
                            --
                        @endif
                    </td>
                    <td class="col-price">{{ currency_format($item->unit_price, $invoice->currency_id, false) }}</td>
                    <td class="col-qty">
                        {{ $item->quantity }}
                        @if($item->unit)
                            <br><span class="f-11">{{ $item->unit->unit_type }}</span>
                        @endif
                    </td>
                    <td class="col-total">{{ currency_format($item->amount, $invoice->currency_id, false) }}</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    <table class="tpl6-totals">
        <tr>
            <td class="label">@lang('ui-shared.pdf.subtotal')</td>
            <td class="value">{{ currency_format($invoice->sub_total, $invoice->currency_id, false) }}</td>
        </tr>
        @if ($discount != 0 && $discount != '')
            <tr>
                <td class="label">
                    @lang('modules.invoices.discount')
                    @if($invoice->discount_type == 'percent')
                        ({{ $invoice->discount }}%)
                    @endif
                </td>
                <td class="value">{{ currency_format($discount, $invoice->currency_id, false) }}</td>
            </tr>
        @endif
        @if ($hasDeducted)
            @foreach ($taxesIncluded as $key => $tax)
                <tr>
                    <td class="label">{{ $key }}</td>
                    <td class="value">{{ currency_format($tax, $invoice->currency_id, false) }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="label">@lang('ui-shared.pdf.total_payment')</td>
                <td class="value">{{ currency_format($totalPaymentAmount, $invoice->currency_id, false) }}</td>
            </tr>
            @foreach ($taxesDeducted as $key => $tax)
                <tr>
                    <td class="label">{{ $key }}</td>
                    <td class="value">-{{ currency_format(abs($tax), $invoice->currency_id, false) }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="label">@lang('ui-shared.pdf.net_payable')</td>
                <td class="value">{{ currency_format($netPayableBeforeAdjustment, $invoice->currency_id, false) }}</td>
            </tr>
        @else
            @foreach ($taxes as $key => $tax)
                <tr>
                    <td class="label">{{ $key }}</td>
                    <td class="value">{{ currency_format($tax, $invoice->currency_id, false) }}</td>
                </tr>
            @endforeach
        @endif
        <tr>
            <td class="label">@lang('modules.invoices.total') ({{ $invoice->currency->currency_code }})</td>
            <td class="value"><strong>{{ currency_format($invoice->total, $invoice->currency_id, false) }}</strong></td>
        </tr>
        @if ($invoice->amountDue())
            <tr>
                <td class="label">@lang('ui-shared.pdf.total_due')</td>
                <td class="value">{{ currency_format($invoice->amountDue(), $invoice->currency_id, false) }}</td>
            </tr>
        @endif
    </table>

    @if ($invoiceSetting->authorised_signatory && $invoiceSetting->authorised_signatory_signature && $invoice->status == 'paid')
        <div class="tpl6-right mt-1">
            <img style="height:80px;" src="{{ $invoiceSetting->authorised_signatory_signature_url }}" alt=""><br>
            <span class="f-11">@lang('ui-shared.pdf.authorised_signatory')</span>
        </div>
    @endif

    @includeIf('invoices.payment_details')

    @if ($invoice->note)
        <div class="tpl6-notes">
            <strong>@lang('app.note')</strong><br>
            {!! nl2br(e($invoice->note)) !!}
        </div>
    @endif

    <div class="tpl6-notes">
        <strong>@lang('modules.invoiceSettings.invoiceTerms')</strong><br>
        {!! nl2br(e($invoiceSetting->invoice_terms)) !!}
    </div>

    @if (isset($invoiceSetting->other_info))
        <div class="tpl6-notes">{!! nl2br(e($invoiceSetting->other_info)) !!}</div>
    @endif

    @if (isset($taxes) && $invoiceSetting->tax_calculation_msg == 1)
        <div class="tpl6-notes">
            @if ($invoice->calculate_tax == 'after_discount')
                @lang('messages.calculateTaxAfterDiscount')
            @else
                @lang('messages.calculateTaxBeforeDiscount')
            @endif
        </div>
    @endif

    @if(isset($fields) && count($fields) > 0)
        <div class="page_break"></div>
        <div class="tpl6-section-title">@lang('modules.projects.otherInfo')</div>
        @foreach($fields as $field)
            <div class="tpl6-notes">
                <strong>{{ $field->label }}</strong><br>
                @if( $field->type == 'text' || $field->type == 'password' || $field->type == 'number' || $field->type == 'textarea')
                    {{$invoice->custom_fields_data['field_'.$field->id] ?? '-'}}
                @elseif($field->type == 'radio')
                    {{ !is_null($invoice->custom_fields_data['field_'.$field->id] ?? null) ? $invoice->custom_fields_data['field_'.$field->id] : '-' }}
                @elseif($field->type == 'select')
                    {{ (!is_null($invoice->custom_fields_data['field_'.$field->id] ?? null) && $invoice->custom_fields_data['field_'.$field->id] != '') ? $field->values[$invoice->custom_fields_data['field_'.$field->id]] : '-' }}
                @elseif($field->type == 'checkbox')
                    {{ !is_null($invoice->custom_fields_data['field_'.$field->id] ?? null) ? $invoice->custom_fields_data['field_'.$field->id] : '-' }}
                @elseif($field->type == 'date')
                    {{ !is_null($invoice->custom_fields_data['field_'.$field->id] ?? null) ? \Carbon\Carbon::parse($invoice->custom_fields_data['field_'.$field->id])->translatedFormat($invoice->company->date_format) : '--'}}
                @endif
            </div>
        @endforeach
    @endif

    @if (count($payments) > 0)
        <div class="page_break"></div>
        <div class="tpl6-section-title">@lang('app.menu.payments') ({{ $invoice->invoice_number }})</div>
        <table class="tpl6-table">
            <thead>
            <tr>
                <th>#</th>
                <th>@lang('ui-shared.pdf.line_amount')</th>
                <th>@lang('modules.invoices.paymentMethod')</th>
                <th>@lang('modules.invoices.paidOn')</th>
            </tr>
            </thead>
            <tbody>
            @forelse($payments as $key => $payment)
                <tr>
                    <td style="text-align:center;">{{ $key + 1 }}</td>
                    <td style="text-align:center;">{{ currency_format($payment->amount, $invoice->currency_id, false) }} {{ $invoice->currency->currency_code }}</td>
                    <td style="text-align:center;">
                        @php
                            $method = '--';
                            if (!is_null($payment->offline_method_id)) {
                                $method = $payment->offlineMethod->name;
                            } elseif (isset($payment->gateway)) {
                                $method = $payment->gateway;
                            }
                        @endphp
                        {{ $method }}
                    </td>
                    <td style="text-align:center;">{{ $payment->paid_on->translatedFormat($company->date_format) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">@lang('messages.noRecordFound')</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    @endif
</div>
@endforeach
{{-- AFV (upgrade 6.0.19): end of CUS_000328 --}}

@include('invoices.pdf.partials.template-6-footer')
</body>
</html>
