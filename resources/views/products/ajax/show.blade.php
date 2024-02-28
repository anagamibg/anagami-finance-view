@php
    $editPermission = user()->permission('edit_product');
    $deletePermission = user()->permission('delete_product');
    $canEdit = $editPermission == 'all' || ($editPermission == 'added' && $product->added_by == user()->id);
    $canDelete = $deletePermission == 'all' || ($deletePermission == 'added' && $product->added_by == user()->id);
    $productImages = $product->files ?? collect();
    $mainImageUrl = $product->image_url ?: ($productImages->first()->file_url ?? null);
    $priceWithTax = $priceWithTax ?? $product->price;
    // AFV (upgrade 6.0.19): only resources with a usable link (link_url is null for non-http(s) URLs)
    $productResources = ($product->resources ?? collect())->filter(fn ($resource) => !empty($resource->link_url));
    $isClient = in_array('client', user_roles());
@endphp

<style>
    /* AFV (upgrade 6.0.19): colours through the --dbk-* tokens (S9 dark theme), the upstream values are the fallbacks */
    .pd-wrap { padding: 4px 2px 20px; }

    .pd-top {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        overflow: visible;
        position: relative;
        z-index: 20;
    }

    .pd-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        overflow: visible;
        position: relative;
        z-index: 20;
    }

    .pd-actions .dropdown { position: relative; }

    .pd-actions .dropdown-menu {
        z-index: 1050;
        min-width: 140px;
    }

    .pd-actions .dropdown-toggle::after { display: none; }

    .pd-card {
        background: var(--dbk-surface, #fff);
        border: 1px solid var(--dbk-border, #e8e8e8);
        border-radius: 6px;
        padding: 16px;
        margin-bottom: 16px;
        height: calc(100% - 16px);
    }

    .pd-gallery {
        position: relative;
        background: var(--dbk-surface-2, #f4f5f7);
        border-radius: 4px;
        min-height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .pd-gallery img {
        width: 100%;
        max-height: 360px;
        object-fit: contain;
        display: block;
    }

    .pd-gallery-empty {
        text-align: center;
        padding: 70px 20px;
    }

    .pd-gallery-empty i {
        font-size: 40px;
        display: block;
        margin-bottom: 8px;
    }

    .pd-zoom {
        position: absolute;
        left: 50%;
        bottom: 12px;
        transform: translateX(-50%);
        background: var(--dbk-surface, rgba(255, 255, 255, .95));
        border: 1px solid var(--dbk-border, #e8e8e8);
        border-radius: 20px;
        padding: 4px 12px;
        white-space: nowrap;
        pointer-events: none;
    }

    .pd-thumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }

    .pd-thumb {
        width: 58px;
        height: 58px;
        border-radius: 4px;
        border: 2px solid transparent;
        overflow: hidden;
        padding: 0;
        background: var(--dbk-surface-2, #f4f5f7);
        cursor: pointer;
    }

    .pd-thumb.active,
    .pd-thumb:hover { border-color: var(--header_color); }

    .pd-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pd-badge {
        display: inline-block;
        border-radius: 4px;
        padding: 3px 8px;
        margin: 0 6px 8px 0;
        font-size: 11px;
        font-weight: 500;
        /* AFV (upgrade 6.0.19): no capitalize (S7: capital letter only at the start) and no text-uppercase on the
           spec labels below (S9: no ALL CAPS) */
    }

    .pd-badge-warn {
        background: var(--dbk-warning-soft, #fff4eb);
        color: var(--header_color);
    }

    .pd-badge-info {
        background: var(--dbk-surface-2, #eef2f7);
        color: var(--dbk-text-2, #616e80);
    }

    .pd-badge-muted {
        background: var(--dbk-surface-2, #f4f5f7);
        color: var(--dbk-text-muted, #99a5b5);
    }

    .pd-specs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 16px;
        margin-bottom: 16px;
    }

    .pd-price {
        background: var(--dbk-surface-2, #f8f9fb);
        border: 1px solid var(--dbk-border, #e8e8e8);
        border-radius: 4px;
        padding: 12px 14px;
        margin-bottom: 12px;
    }

    .pd-price .amount {
        margin: 2px 0 0;
        font-size: 24px;
        font-weight: 700;
        line-height: 1.2;
        color: var(--header_color);
    }

    .pd-cta .btn {
        width: 100%;
        margin-bottom: 8px;
    }

    .pd-desc {
        line-height: 1.65;
    }

    .pd-desc ul { padding-left: 18px; margin-bottom: 0; }

    .pd-link {
        display: flex;
        align-items: center;
        width: 100%;
        border: 1px solid var(--dbk-border, #e8e8e8);
        border-radius: 4px;
        padding: 10px 12px;
        margin-bottom: 8px;
        text-decoration: none;
        background: var(--dbk-surface, #fff);
        transition: border-color .15s, color .15s;
    }

    .pd-link:hover {
        border-color: var(--header_color);
        color: var(--header_color);
        text-decoration: none;
    }

    .pd-link i {
        width: 22px;
        margin-right: 10px;
        font-size: 15px;
    }

    .pd-link:hover i { color: var(--header_color); }

    .pd-link .pd-link-text { flex: 1; min-width: 0; }

    .pd-empty {
        text-align: center;
        padding: 28px 12px;
    }

    .pd-empty i {
        display: block;
        font-size: 26px;
        margin-bottom: 8px;
    }

    @media (max-width: 991px) {
        .pd-gallery { min-height: 220px; }
        .pd-card { height: auto; }
    }

    @media (max-width: 575px) {
        .pd-specs { grid-template-columns: 1fr; }
    }
</style>

<div id="product-detail-section" class="pd-wrap">
    <div class="pd-top">
        <div>
            <h3 class="heading-h1 text-darkest-grey mb-1">{{ $product->name }}</h3>
            <p class="f-12 text-lightest mb-0">
                @if ($product->sku)
                    {{ $product->sku }}
                @else
                    #{{ $product->id }}
                @endif
            </p>
        </div>
        <div class="pd-actions">
            <a href="{{ route('products.index') }}" class="btn btn-secondary f-14">
                <i class="fa fa-arrow-left mr-1"></i>@lang('app.backToProducts')
            </a>
            @if ($canEdit)
                <a href="{{ route('products.edit', $product->id) }}" class="btn btn-primary f-14 openRightModal">
                    <i class="fa fa-pencil-alt mr-1"></i>@lang('app.editProduct')
                </a>
            @endif
            @if ($canDelete)
                <div class="dropdown">
                    <button class="btn btn-lg f-14 px-2 py-1 text-dark-grey rounded dropdown-toggle"
                            type="button"
                            id="product-detail-more-menu"
                            data-toggle="dropdown"
                            data-boundary="viewport"
                            aria-haspopup="true"
                            aria-expanded="false">
                        <i class="fa fa-ellipsis-h"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right border-grey rounded b-shadow-4 p-0"
                         aria-labelledby="product-detail-more-menu">
                        <a class="dropdown-item delete-product text-dark-grey f-14 py-2 px-3"
                           href="javascript:;"
                           data-product-id="{{ $product->id }}">
                            <i class="fa fa-trash mr-2"></i>@lang('app.delete')
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 col-md-12">
            <div class="pd-card">
                <div class="pd-gallery">
                    @if ($mainImageUrl)
                        <a href="javascript:;" class="img-lightbox d-block w-100" id="product-main-lightbox"
                           data-image-url="{{ $mainImageUrl }}">
                            <img src="{{ $mainImageUrl }}" alt="{{ $product->name }}" id="product-main-image">
                        </a>
                        <div class="pd-zoom f-11 text-lightest">
                            <i class="fa fa-search-plus mr-1"></i>@lang('app.hoverToZoom')
                        </div>
                    @else
                        <div class="pd-gallery-empty text-lightest">
                            <i class="fa fa-image"></i>
                            <span class="f-13">@lang('app.noProductImage')</span>
                        </div>
                    @endif
                </div>

                @if ($productImages->count() > 0)
                    <div class="pd-thumbs">
                        @foreach ($productImages as $index => $file)
                            <button type="button"
                                    class="pd-thumb {{ ($mainImageUrl == $file->file_url || (!$product->image_url && $index === 0)) ? 'active' : '' }}"
                                    data-image-url="{{ $file->file_url }}">
                                <img src="{{ $file->file_url }}" alt="{{ $file->filename }}">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-5 col-md-12">
            <div class="pd-card">
                <div>
                    @if ($isClient)
                        <span class="pd-badge pd-badge-warn f-11">@lang('app.active')</span>
                    @else
                        <span class="pd-badge f-11 {{ $product->allow_purchase ? 'pd-badge-warn' : 'pd-badge-muted' }}">
                            {{ $product->allow_purchase ? __('app.allowed') : __('app.notAllowed') }}
                        </span>
                    @endif
                    @if ($product->category)
                        <span class="pd-badge pd-badge-info f-11">{{ $product->category->category_name }}</span>
                    @endif
                    @if ($product->subCategory)
                        <span class="pd-badge pd-badge-info f-11">{{ $product->subCategory->category_name }}</span>
                    @endif
                </div>

                <h4 class="heading-h3 text-darkest-grey mb-3">{{ $product->name }}</h4>

                <div class="pd-specs">
                    <div>
                        <span class="d-block f-11 text-lightest mb-1">@lang('app.sku')</span>
                        <p class="mb-0 f-14 f-w-500 text-darkest-grey d-flex align-items-center">
                            @if ($product->sku)
                                <span>{{ $product->sku }}</span>
                                <a href="javascript:;" class="btn-copy text-lightest ml-2"
                                   data-clipboard-text="{{ $product->sku }}"
                                   data-toggle="tooltip"
                                   data-original-title="@lang('app.copy')">
                                    <i class="fa fa-copy"></i>
                                </a>
                            @else
                                –
                            @endif
                        </p>
                    </div>
                    <div>
                        <span class="d-block f-11 text-lightest mb-1">@lang('modules.unitType.unitType')</span>
                        <p class="mb-0 f-14 f-w-500 text-darkest-grey">{{ $product->unit->unit_type ?? '–' }}</p>
                    </div>
                    <div>
                        <span class="d-block f-11 text-lightest mb-1">@lang('app.hsnSac')</span>
                        <p class="mb-0 f-14 f-w-500 text-darkest-grey">{{ $product->hsn_sac_code ?: '–' }}</p>
                    </div>
                    <div>
                        <span class="d-block f-11 text-lightest mb-1">@lang('modules.invoices.tax')</span>
                        <p class="mb-0 f-14 f-w-500 text-darkest-grey">{{ !empty($taxValue) ? $taxValue : '–' }}</p>
                    </div>
                    @unless ($isClient)
                        {{-- AFV (S8): cost price (used by the margins report), hidden from clients --}}
                        <div>
                            <span class="d-block f-11 text-lightest mb-1">@lang('databackd-gaps-margins.cost_price.label')</span>
                            <p class="mb-0 f-14 f-w-500 text-darkest-grey">{{ !is_null($product->cost_price) ? currency_format($product->cost_price, company()->currency_id) : '–' }}</p>
                        </div>
                    @endunless
                </div>

                <div class="pd-price">
                    <div class="f-12 text-dark-grey">@lang('app.authorizedSellingPrice')</div>
                    <p class="amount">{{ currency_format($priceWithTax, company()->currency_id) }}</p>
                    @if (!empty($taxValue))
                        <div class="f-11 text-lightest mt-1">@lang('app.inclusiveAllTaxes')</div>
                    @endif
                </div>

                <div class="pd-cta">
                    @if ($isClient)
                        <button type="button" class="btn btn-primary f-14 add-product"
                                data-product-id="{{ $product->id }}"
                                id="add-to-cart-btn-{{ $product->id }}">
                            <i class="fa fa-plus mr-1"></i>@lang('app.addToCart')
                        </button>
                    @endif

                    {{-- AFV (upgrade 6.0.19): the downloadable file is for staff (as in 6.0.10); clients get it from a completed order --}}
                    @if ($product->downloadable && $product->download_file_url && !$isClient)
                        <a href="{{ $product->download_file_url }}" class="btn btn-secondary f-14" download>
                            <i class="fa fa-download mr-1"></i>@lang('app.downloadableFile')
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 col-md-12">
            <div class="pd-card">
                <h4 class="heading-h4 text-darkest-grey mb-3">@lang('app.productDescription')</h4>
                <div class="pd-desc f-14 text-dark-grey">
                    @if (!empty($product->description))
                        {!! clean_editor($product->description) !!}{{-- AFV (upgrade 6.0.19): imported / older descriptions are not sanitized --}}
                    @else
                        <div class="pd-empty text-lightest f-13">
                            <i class="fa fa-align-left"></i>
                            @lang('messages.noRecordFound')
                        </div>
                    @endif
                </div>

                @if (isset($fields) && count($fields) > 0)
                    <div class="mt-4 pt-3 border-top-grey">
                        <x-forms.custom-field-show :fields="$fields" :model="$product"></x-forms.custom-field-show>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4 col-md-12">
            <div class="pd-card">
                <h4 class="heading-h4 text-darkest-grey mb-3">@lang('app.videosAndDocuments')</h4>

                @if ($productResources->count() > 0)
                    @foreach ($productResources as $resource)
                        <a href="{{ $resource->link_url }}"
                           class="pd-link text-dark-grey"
                           target="_blank"
                           rel="noopener"
                           @if($resource->source === 'file') download @endif>
                            <i class="fa {{ $resource->icon }} text-lightest"></i>
                            <span class="pd-link-text">
                                <span class="d-block f-13 f-w-500">{{ $resource->display_title }}</span>
                                <span class="d-block f-11 text-lightest mt-1">
                                    {{ $resource->type === 'video' ? __('app.productVideo') : __('app.productDocument') }}
                                </span>
                            </span>
                        </a>
                    @endforeach
                @elseif ($product->downloadable && $product->download_file_url && !$isClient) {{-- AFV (upgrade 6.0.19) --}}
                    <a href="{{ $product->download_file_url }}" class="pd-link text-dark-grey" download>
                        <i class="fa fa-file-download text-lightest"></i>
                        <span class="pd-link-text">
                            <span class="d-block f-13 f-w-500">{{ $product->downloadable_file }}</span>
                            <span class="d-block f-11 text-lightest mt-1">@lang('app.productDocument')</span>
                        </span>
                    </a>
                @else
                    <div class="pd-empty text-lightest f-13">
                        <i class="fa fa-folder-open"></i>
                        @lang('messages.noRecordFound')
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('vendor/jquery/clipboard.min.js') }}"></script>
<script>
    // AFV (upgrade 6.0.19): $(fn) instead of an IIFE - on the full page (/account/products/{id}) this script runs
    // before main.js, .tooltip() threw and the delete/thumbnail handlers below were never bound
    $(function() {
        if (typeof ClipboardJS !== 'undefined') {
            var productDetailClipboard = new ClipboardJS('#product-detail-section .btn-copy');
            productDetailClipboard.on('success', function() {
                Swal.fire({
                    icon: 'success',
                    text: '@lang("app.copied")',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    customClass: { confirmButton: 'btn btn-primary' },
                    showClass: {
                        popup: 'swal2-noanimation',
                        backdrop: 'swal2-noanimation'
                    },
                });
            });
        }

        $('[data-toggle="tooltip"]').tooltip();

        $('#product-detail-section').off('click.pdThumb').on('click.pdThumb', '.pd-thumb', function() {
            var imageUrl = $(this).data('image-url');
            if ($('#product-main-image').length) {
                $('#product-main-image').attr('src', imageUrl);
                $('#product-main-lightbox').attr('data-image-url', imageUrl);
            }
            $('.pd-thumb').removeClass('active');
            $(this).addClass('active');
        });

        $('body').off('click.deleteProduct').on('click.deleteProduct', '.delete-product', function() {
            var id = $(this).data('product-id');
            Swal.fire({
                title: "@lang('messages.sweetAlertTitle')",
                text: "@lang('messages.recoverRecord')",
                icon: 'warning',
                showCancelButton: true,
                focusConfirm: false,
                confirmButtonText: "@lang('messages.confirmDelete')",
                cancelButtonText: "@lang('app.cancel')",
                customClass: {
                    confirmButton: 'btn btn-primary mr-3',
                    cancelButton: 'btn btn-secondary'
                },
                showClass: {
                    popup: 'swal2-noanimation',
                    backdrop: 'swal2-noanimation'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.isConfirmed) {
                    var url = "{{ route('products.destroy', ':id') }}";
                    url = url.replace(':id', id);
                    $.easyAjax({
                        type: 'POST',
                        url: url,
                        data: { '_token': '{{ csrf_token() }}', '_method': 'DELETE' },
                        success: function(response) {
                            if (response.status == 'success') {
                                window.location.href = "{{ route('products.index') }}";
                            }
                        }
                    });
                }
            });
        });
    });
</script>
