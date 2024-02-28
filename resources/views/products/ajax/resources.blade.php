@php
    $existingResources = $existingResources ?? collect();
@endphp
{{-- AFV (upgrade 6.0.19): "File" and the title placeholder from ui-products (app.file is the plural "Files",
     placeholders.title is "e.g. Manager"); the server checks links/files in ProductController::validateProductResources() --}}

<style>
    /* AFV (upgrade 6.0.19): colours through the --dbk-* tokens (S9 dark theme), the upstream values are the fallbacks */
    .product-resource-row {
        border: 1px solid var(--dbk-border, #e8e8e8);
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 10px;
        background: var(--dbk-surface-2, #fafbfc);
    }
</style>

<div class="col-lg-12 mt-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <h5 class="mb-0 f-16 font-weight-bold">@lang('app.videosAndDocuments')</h5>
        <button type="button" class="btn btn-secondary btn-sm" id="add-product-resource">
            <i class="fa fa-plus mr-1"></i>@lang('app.addResource')
        </button>
    </div>
    <p class="f-12 text-lightest mb-3">@lang('app.productResourcesInfo')</p>

    <div id="product-resources-list">
        @foreach ($existingResources as $index => $resource)
            <div class="product-resource-row" data-resource-id="{{ $resource->id }}">
                <input type="hidden" name="resources[{{ $index }}][id]" value="{{ $resource->id }}">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group mb-2 mb-md-0">
                            <label class="f-14 text-dark-grey">@lang('app.type')</label>
                            <select name="resources[{{ $index }}][type]" class="form-control height-35 resource-type">
                                <option value="video" @selected($resource->type === 'video')>@lang('app.productVideo')</option>
                                <option value="document" @selected($resource->type === 'document')>@lang('app.productDocument')</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group mb-2 mb-md-0">
                            <label class="f-14 text-dark-grey">@lang('app.title')</label>
                            <input type="text" name="resources[{{ $index }}][title]" class="form-control height-35"
                                   value="{{ $resource->title }}" placeholder="@lang('ui-products.resources.title_placeholder')">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2 mb-md-0">
                            <label class="f-14 text-dark-grey">@lang('app.source')</label>
                            <select name="resources[{{ $index }}][source]" class="form-control height-35 resource-source">
                                <option value="url" @selected($resource->source === 'url')>@lang('app.url')</option>
                                <option value="file" @selected($resource->source === 'file')>@lang('ui-products.resources.file')</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-2 mb-md-0 resource-url-field {{ $resource->source === 'file' ? 'd-none' : '' }}">
                            <label class="f-14 text-dark-grey">@lang('app.url')</label>
                            <input type="url" name="resources[{{ $index }}][url]" class="form-control height-35"
                                   value="{{ $resource->url }}" placeholder="https://">
                        </div>
                        <div class="form-group mb-2 mb-md-0 resource-file-field {{ $resource->source === 'url' ? 'd-none' : '' }}">
                            <label class="f-14 text-dark-grey">@lang('ui-products.resources.file')</label>
                            @if ($resource->source === 'file' && $resource->filename)
                                <div class="f-12 mb-1">
                                    <a href="{{ $resource->file_url }}" target="_blank">{{ $resource->filename }}</a>
                                </div>
                            @endif
                            <input type="file" name="resources[{{ $index }}][file]" class="form-control height-35"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.mp4,.webm,.mov,.avi">
                        </div>
                    </div>
                    <div class="col-md-1 d-flex align-items-end justify-content-end">
                        <button type="button" class="btn btn-secondary btn-sm remove-product-resource mb-2"
                                data-toggle="tooltip" data-original-title="@lang('app.remove')">
                            <i class="fa fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<template id="product-resource-row-template">
    <div class="product-resource-row">
        <div class="row">
            <div class="col-md-2">
                <div class="form-group mb-2 mb-md-0">
                    <label class="f-14 text-dark-grey">@lang('app.type')</label>
                    <select name="resources[__INDEX__][type]" class="form-control height-35 resource-type">
                        <option value="video">@lang('app.productVideo')</option>
                        <option value="document">@lang('app.productDocument')</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-2 mb-md-0">
                    <label class="f-14 text-dark-grey">@lang('app.title')</label>
                    <input type="text" name="resources[__INDEX__][title]" class="form-control height-35"
                           placeholder="@lang('ui-products.resources.title_placeholder')">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-2 mb-md-0">
                    <label class="f-14 text-dark-grey">@lang('app.source')</label>
                    <select name="resources[__INDEX__][source]" class="form-control height-35 resource-source">
                        <option value="url">@lang('app.url')</option>
                        <option value="file">@lang('ui-products.resources.file')</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group mb-2 mb-md-0 resource-url-field">
                    <label class="f-14 text-dark-grey">@lang('app.url')</label>
                    <input type="url" name="resources[__INDEX__][url]" class="form-control height-35" placeholder="https://">
                </div>
                <div class="form-group mb-2 mb-md-0 resource-file-field d-none">
                    <label class="f-14 text-dark-grey">@lang('ui-products.resources.file')</label>
                    <input type="file" name="resources[__INDEX__][file]" class="form-control height-35"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.mp4,.webm,.mov,.avi">
                </div>
            </div>
            <div class="col-md-1 d-flex align-items-end justify-content-end">
                <button type="button" class="btn btn-secondary btn-sm remove-product-resource mb-2"
                        data-toggle="tooltip" data-original-title="@lang('app.remove')">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
    (function() {
        var resourceIndex = {{ max($existingResources->count(), 0) }};

        function toggleResourceSource($row) {
            var source = $row.find('.resource-source').val();
            if (source === 'file') {
                $row.find('.resource-url-field').addClass('d-none');
                $row.find('.resource-file-field').removeClass('d-none');
            } else {
                $row.find('.resource-file-field').addClass('d-none');
                $row.find('.resource-url-field').removeClass('d-none');
            }
        }

        $('#add-product-resource').off('click').on('click', function() {
            var html = $('#product-resource-row-template').html().replace(/__INDEX__/g, resourceIndex);
            resourceIndex++;
            $('#product-resources-list').append(html);
            $('[data-toggle="tooltip"]').tooltip();
        });

        $('body').off('change.productResourceSource').on('change.productResourceSource', '.resource-source', function() {
            toggleResourceSource($(this).closest('.product-resource-row'));
        });

        $('body').off('click.removeProductResource').on('click.removeProductResource', '.remove-product-resource', function() {
            var $row = $(this).closest('.product-resource-row');
            var resourceId = $row.data('resource-id');

            if (resourceId) {
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
                        $.easyAjax({
                            type: 'POST',
                            url: "{{ route('product-resources.destroy', ':id') }}".replace(':id', resourceId),
                            data: {
                                _token: "{{ csrf_token() }}",
                                _method: 'DELETE'
                            },
                            success: function(response) {
                                if (response.status === 'success') {
                                    $row.remove();
                                }
                            }
                        });
                    }
                });
            } else {
                $row.remove();
            }
        });
    })();
</script>
