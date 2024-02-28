@php
    $editProductPermission = $editProductPermission ?? user()->permission('edit_product');
    $deleteProductPermission = $deleteProductPermission ?? user()->permission('delete_product');
    $cartProductIds = $cartProductIds ?? [];
@endphp

<style>
    /* AFV (upgrade 6.0.19): colours through the --dbk-* tokens (S9 dark theme), the upstream values are the fallbacks */
    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 12px;
    }

    @media (min-width: 1400px) {
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        }
    }

    @media (min-width: 1600px) {
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        }
    }

    @media (max-width: 575px) {
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
        }
    }

    .product-grid-card {
        border: 1px solid var(--dbk-border, #e8e8e8);
        border-radius: 8px;
        background: var(--dbk-surface, #fff);
        height: 100%;
        overflow: visible;
        position: relative;
        display: flex;
        flex-direction: column;
        transition: box-shadow .15s ease;
    }

    .product-grid-card:hover {
        box-shadow: 0 4px 14px rgba(40, 49, 60, 0.08);
    }

    .product-grid-card.dropdown-open {
        z-index: 30;
    }

    .product-grid-image {
        display: block;
        width: 100%;
        height: 150px;
        object-fit: cover;
        background: var(--dbk-surface-2, #f4f5f7);
        border-radius: 8px 8px 0 0;
    }

    .product-grid-image-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 150px;
        background: var(--dbk-surface-2, #f4f5f7);
        color: var(--dbk-text-muted, #b0b7c3);
        font-size: 36px;
        border-radius: 8px 8px 0 0;
    }

    @media (max-width: 575px) {
        .product-grid-image,
        .product-grid-image-placeholder {
            height: 120px;
        }
    }

    .product-grid-body {
        padding: 10px 12px 12px;
        display: flex;
        flex-direction: column;
        flex: 1;
    }

    .product-grid-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--dbk-text, #28313c);
        margin: 0 0 4px;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 38px;
    }

    .product-grid-meta {
        font-size: 11px;
        color: var(--dbk-text-muted, #7a869a);
        margin-bottom: 6px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .product-grid-price {
        font-size: 15px;
        font-weight: 700;
        color: var(--dbk-text, #28313c);
        margin-bottom: 6px;
    }

    .product-grid-sku {
        font-size: 11px;
        color: var(--dbk-text-2, #616e80);
        margin-bottom: 10px;
        min-width: 0;
    }

    .product-grid-sku > span:not(.mr-1) {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
    }

    .product-grid-actions {
        position: relative;
        z-index: 1;
        overflow: visible;
        margin-top: auto;
        gap: 6px;
    }

    .product-grid-actions .btn,
    .product-grid-actions .btn-secondary {
        font-size: 12px;
        padding: 4px 10px;
    }

    .product-grid-actions .dropdown {
        position: relative;
        flex-shrink: 0;
    }

    .product-grid-actions .dropdown-toggle::after {
        display: none;
    }

    .product-grid-actions .dropdown-menu {
        z-index: 1050;
        min-width: 140px;
        padding: 6px 0;
        margin-top: 4px;
        right: 0;
        left: auto;
    }

    .product-grid-actions .dropdown-item {
        padding: 8px 14px;
        font-size: 13px;
    }
</style>

@if ($products->count() > 0)
    <div class="product-grid">
        @foreach ($products as $product)
            @php
                $priceWithTax = $product->price;
                if (!is_null($product->taxes)) {
                    $totalTax = 0;
                    foreach (json_decode($product->taxes) as $tax) {
                        $prodTax = \App\Models\Product::taxbyid($tax)->first();
                        if ($prodTax) {
                            $totalTax += ($product->price * ($prodTax->rate_percent / 100));
                        }
                    }
                    $priceWithTax = $product->price + $totalTax;
                }
            @endphp
            <div class="product-grid-card">
                <a href="{{ route('products.show', [$product->id]) }}" class="openRightModal d-block">
                    @if ($product->default_image)
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="product-grid-image">
                    @else
                        <div class="product-grid-image-placeholder">
                            <i class="fa fa-box-open"></i>
                        </div>
                    @endif
                </a>
                <div class="product-grid-body">
                    <h4 class="product-grid-title">
                        <a href="{{ route('products.show', [$product->id]) }}" class="openRightModal text-darkest-grey">
                            {{ $product->name }}
                        </a>
                    </h4>

                    @if ($product->category)
                        <div class="product-grid-meta">
                            {{ $product->category->category_name }}
                            @if ($product->subCategory)
                                · {{ $product->subCategory->category_name }}
                            @endif
                        </div>
                    @endif

                    <div class="product-grid-price">
                        {{ currency_format($priceWithTax, company()->currency_id) }}
                    </div>

                    <div class="product-grid-sku d-flex align-items-center">
                        <span class="mr-1">@lang('app.sku'):</span>
                        @if ($product->sku)
                            <span>{{ $product->sku }}</span>
                            <a href="javascript:;" class="btn-copy text-lightest ml-1"
                               data-clipboard-text="{{ $product->sku }}"
                               data-toggle="tooltip"
                               data-original-title="@lang('app.copy')">
                                <i class="fa fa-copy"></i>
                            </a>
                        @else
                            <span>--</span>
                        @endif
                    </div>

                    <div class="product-grid-actions d-flex align-items-center justify-content-between">
                        @if (in_array('client', user_roles()))
                            @php
                                $addToCart = in_array($product->id, $cartProductIds)
                                    ? __('app.addedToCart')
                                    : '<i class="fa fa-plus mr-1"></i>' . __('app.addToCart');
                            @endphp
                            <button type="button"
                                    class="btn btn-secondary rounded f-14 add-product"
                                    data-product-id="{{ $product->id }}"
                                    id="add-to-cart-btn-{{ $product->id }}">
                                {!! $addToCart !!}
                            </button>
                        @else
                            <a href="{{ route('products.show', [$product->id]) }}"
                               class="btn btn-secondary btn-sm openRightModal">
                                @lang('app.view')
                            </a>
                            <div class="dropdown">
                                <button class="btn btn-secondary btn-sm dropdown-toggle" type="button"
                                        data-toggle="dropdown"
                                        data-boundary="viewport"
                                        aria-haspopup="true"
                                        aria-expanded="false">
                                    <i class="icon-options-vertical icons"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-right border-grey rounded b-shadow-4">
                                    @if ($editProductPermission == 'all' || ($editProductPermission == 'added' && user()->id == $product->added_by))
                                        <a class="dropdown-item openRightModal" href="{{ route('products.edit', [$product->id]) }}">
                                            <i class="fa fa-edit mr-2"></i>@lang('app.edit')
                                        </a>
                                    @endif
                                    @if ($deleteProductPermission == 'all' || ($deleteProductPermission == 'added' && user()->id == $product->added_by))
                                        <a class="dropdown-item delete-table-row" href="javascript:;"
                                           data-product-id="{{ $product->id }}">
                                            <i class="fa fa-trash mr-2"></i>@lang('app.delete')
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

@else
    <div class="bg-white rounded p-5 text-center text-lightest">
        @lang('messages.noRecordFound')
    </div>
@endif

<script>
    (function () {
        var $grid = $('#products-grid-wrap');

        $grid.off('show.bs.dropdown.productGrid hide.bs.dropdown.productGrid')
            .on('show.bs.dropdown.productGrid', '.dropdown', function () {
                $(this).closest('.product-grid-card').addClass('dropdown-open');
            })
            .on('hide.bs.dropdown.productGrid', '.dropdown', function () {
                $(this).closest('.product-grid-card').removeClass('dropdown-open');
            });
    })();
</script>
