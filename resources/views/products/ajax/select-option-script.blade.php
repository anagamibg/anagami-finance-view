<script>
    window.buildProductSelectOption = function (product) {
        var escapeHtml = function (value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        };

        var name = escapeHtml(product.name || product.title || product.text || '');
        var sku = escapeHtml(product.sku || '');
        var skuLabel = sku || '—';
        var imageUrl = product.image_url || '';

        if (!imageUrl && product.files && product.files.length) {
            imageUrl = product.files[0].file_url || '';
        }

        imageUrl = escapeHtml(imageUrl);

        var price = product.price != null ? product.price : '';
        var priceLabel = escapeHtml(product.formatted_price || price);
        var tokens = $.trim((product.name || product.title || '') + ' ' + (product.sku || '') + ' ' + price);
        var label = name + (sku ? ' (' + sku + ')' : '');

        var thumb = imageUrl
            ? "<img class='product-select-thumb' src='" + imageUrl + "' alt='' onerror=\"this.classList.add('d-none');this.nextElementSibling.classList.remove('d-none');\">" +
              "<span class='product-select-thumb product-select-thumb-empty d-none'><i class='fa fa-cube'></i></span>"
            : "<span class='product-select-thumb product-select-thumb-empty'><i class='fa fa-cube'></i></span>";

        var content = "<div class='product-select-option'>" +
            "<span class='product-select-thumb-wrap'>" + thumb + "</span>" +
            "<span class='product-select-meta'>" +
            "<span class='product-select-name'>" + name + "</span>" +
            "<span class='product-select-sku'>@lang('app.sku'): " + skuLabel + "</span>" +
            "</span>" +
            "<span class='product-select-price'>" + priceLabel + "</span>" +
            "</div>";

        // AFV (upgrade 6.0.19): attribute values escaped for the attribute (quotes too) - the browser decodes them once,
        // bootstrap-select (sanitize: false) inserts data-content as HTML; before, &lt; in a name became a tag and " broke out
        var escapeAttr = function (value) {
            return escapeHtml(value).replace(/"/g, '&quot;');
        };

        return '<option value="' + escapeAttr(product.id) + '" data-tokens="' + escapeAttr(tokens) +
            '" data-content="' + escapeAttr(content) + '">' + label + '</option>';
    };

    window.fillProductSelectOptions = function ($select, products, placeholder) {
        var options = [];

        $.each(products || [], function (index, value) {
            options.push(window.buildProductSelectOption(value));
        });

        $select.html(
            '<option value="">' + (placeholder || "@lang('app.menu.selectProduct')") + '</option>' +
            options.join('')
        );
        $select.selectpicker('refresh');
    };

    window.initProductSelectPicker = function (selector) {
        var $select = $(selector || '#add-products');

        if (!$select.length) {
            return;
        }

        if ($select.data('selectpicker')) {
            $select.selectpicker('destroy');
        }

        $select.selectpicker({
            liveSearch: true,
            size: 10,
            sanitize: false,
            liveSearchPlaceholder: "@lang('app.search') / @lang('app.sku')"
        });
    };
</script>
