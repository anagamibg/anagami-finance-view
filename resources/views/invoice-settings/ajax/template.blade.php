<link rel="stylesheet" href="{{ asset('vendor/css/image-picker.min.css') }}">

<div class="col-lg-12 col-md-12 ntfcn-tab-content-left w-100 p-4 ">
    @method('POST')
    <div class="row">
        <div class="col-lg-12 mt-4">
            <div class="form-group">
                <x-forms.label fieldId="template" :fieldLabel="__('modules.invoiceSettings.template')" fieldRequired="true">
                </x-forms.label>
                <select name="template" id="invoice-template-picker" class="image-picker show-labels show-html">
                    <option data-img-src="{{ asset('img/invoice-template/1.png') }}"
                        @if ($invoiceSetting->template == 'invoice-1') selected @endif value="invoice-1">@lang('modules.invoiceSettings.template') 1
                    </option>
                    <option data-img-src="{{ asset('img/invoice-template/2.png') }}"
                        @if ($invoiceSetting->template == 'invoice-2') selected @endif value="invoice-2">@lang('modules.invoiceSettings.template') 2
                    </option>
                    <option data-img-src="{{ asset('img/invoice-template/3.png') }}"
                        @if ($invoiceSetting->template == 'invoice-3') selected @endif value="invoice-3">@lang('modules.invoiceSettings.template') 3
                    </option>
                    <option data-img-src="{{ asset('img/invoice-template/4.png') }}"
                        @if ($invoiceSetting->template == 'invoice-4') selected @endif value="invoice-4">@lang('modules.invoiceSettings.template') 4
                    </option>
                    <option data-img-src="{{ asset('img/invoice-template/5.png') }}"
                        @if ($invoiceSetting->template == 'invoice-5') selected @endif value="invoice-5">@lang('modules.invoiceSettings.template') 5
                    </option>
                    <option data-img-src="{{ asset('img/invoice-template/6.png') }}"
                        @if ($invoiceSetting->template == 'invoice-6') selected @endif value="invoice-6">@lang('modules.invoiceSettings.template') 6
                    </option>
                </select>
            </div>
        </div>

        <div class="col-lg-12 mt-3 template-6-customisation @if ($invoiceSetting->template != 'invoice-6') d-none @endif">
            {{-- AFV (upgrade 6.0.19): ui-invoices.template.* - the modules.invoiceSettings.template{CustomParts,Header,Footer}* keys are missing in the 6.0.19 lang files --}}
            <h5 class="heading-h4 mb-3">@lang('ui-invoices.template.banners_title')</h5>
            <div class="row">
                <div class="col-lg-6">
                    <x-forms.file allowedFileExtensions="png jpg jpeg bmp webp" class="mr-0 mr-lg-2 mr-md-2"
                        :fieldLabel="__('ui-invoices.template.top_image')"
                        fieldName="template_header" fieldId="template_header"
                        :fieldValue="$invoiceSetting->masked_template_header_url"
                        :popover="__('ui-invoices.template.top_image_info')"/>
                </div>
                <div class="col-lg-6">
                    <x-forms.file allowedFileExtensions="png jpg jpeg bmp webp" class="mr-0 mr-lg-2 mr-md-2"
                        :fieldLabel="__('ui-invoices.template.bottom_image')"
                        fieldName="template_footer" fieldId="template_footer"
                        :fieldValue="$invoiceSetting->masked_template_footer_url"
                        :popover="__('ui-invoices.template.bottom_image_info')"/>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Buttons Start -->
<div class="w-100 border-top-grey">
    <x-setting-form-actions>
        <x-forms.button-primary id="save-form" class="mr-3" icon="check">@lang('app.save')
        </x-forms.button-primary>
    </x-setting-form-actions>
</div>
<!-- Buttons End -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/image-picker/0.3.1/image-picker.min.js"></script>
<script>
    // Initializing image picker
    $('.image-picker').imagepicker();

    function toggleTemplate6Customisation() {
        var selected = $('select[name="template"]').val();
        if (selected === 'invoice-6') {
            $('.template-6-customisation').removeClass('d-none');
        } else {
            $('.template-6-customisation').addClass('d-none');
        }
    }

    $(document).on('change', 'select[name="template"]', toggleTemplate6Customisation);
    // image-picker updates selection via click on thumbnails
    $(document).on('click', '.thumbnail', function() {
        setTimeout(toggleTemplate6Customisation, 50);
    });

    // Ensure remove marks the field for deletion on save
    $('#template_header, #template_footer').off('dropify.afterClear.template6').on('dropify.afterClear.template6', function() {
        var elementID = this.id;
        var elementName = this.name;

        if ($('#' + elementID + '_delete').length === 0) {
            $(this).after('<input type="hidden" name="' + elementName + '_delete" id="' + elementID + '_delete" value="yes">');
        }
    });

    // save invoice setting
    $('#save-form').click(function() {
        $.easyAjax({
            url: "{{ route('invoice_settings.update_template', $invoiceSetting->id) }}",
            container: '#editSettings',
            type: "POST",
            redirect: true,
            file: true,
            data: {},
            disableButton: true,
            blockUI: true,
            buttonSelector: "#save-form",
        });
    });
</script>
