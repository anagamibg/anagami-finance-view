@php
    $productImageUrl = $item->image_url;

    if (empty($productImageUrl) && $item->relationLoaded('files') && $item->files->isNotEmpty()) {
        $productImageUrl = $item->files->first()->file_url;
    } elseif (empty($productImageUrl)) {
        $firstFile = $item->files()->first();
        $productImageUrl = $firstFile ? $firstFile->file_url : '';
    }

    $searchTokens = trim(($item->name ?? '') . ' ' . ($item->sku ?? '') . ' ' . ($item->price ?? ''));
    $skuLabel = $item->sku ?: '—';
    $priceLabel = currency_format($item->price, company()->currency_id);
@endphp
{{-- AFV (upgrade 6.0.19): the values inside data-content are escaped twice - the browser decodes the attribute once and
     bootstrap-select (sanitize: false in select-option-script) inserts it as HTML, so a single escape let a product name run HTML --}}
<option value="{{ $item->id }}" @selected(!empty($productSelectSelected))
        data-tokens="{{ $searchTokens }}"
        data-content="<div class='product-select-option'><span class='product-select-thumb-wrap'>@if($productImageUrl)<img class='product-select-thumb' src='{{ e($productImageUrl) }}' alt='' onerror=&quot;this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');&quot;><span class='product-select-thumb product-select-thumb-empty d-none'><i class='fa fa-cube'></i></span>@else<span class='product-select-thumb product-select-thumb-empty'><i class='fa fa-cube'></i></span>@endif</span><span class='product-select-meta'><span class='product-select-name'>{{ e($item->name) }}</span><span class='product-select-sku'>{{ e(__('app.sku')) }}: {{ e($skuLabel) }}</span></span><span class='product-select-price'>{{ e($priceLabel) }}</span></div>">
    {{ $item->name }}@if ($item->sku) ({{ $item->sku }})@endif
</option>
