@extends('layouts.app')

@section('content')
    @php
        $df = company()->date_format;
        $fmtDate = fn($d) => $d ? $d->format($df) : '-';
        $hasResult = $analysis->deliverable_file || $safeLink;
    @endphp

    <div class="content-wrapper dbk-teams">

        <!-- HEADER -->
        @php
            $statusTone = ['new' => 'is-info', 'in_progress' => 'is-warning', 'review' => 'dbk-teams-ast-review', 'delivered' => 'is-good', 'closed' => ''];
        @endphp
        <x-dbk.page-header :title="$analysis->title">
            <div class="dbk-teams-meta">
                <span class="dbk-badge {{ $statusTone[$analysis->status] ?? '' }}">@lang('databackd-teams-common.analysis_status.' . $analysis->status)</span>
                <span class="dbk-badge dbk-teams-prio-{{ $analysis->priority }}">@lang('databackd-teams-common.priority.' . $analysis->priority)</span>
                <span class="an-type">@lang('databackd-teams-common.analysis_type.' . $analysis->type)</span>
                @if ($analysis->recurrence !== 'none')
                    <span><i class="fa fa-sync-alt mr-1"></i>@lang('databackd-teams-common.recurrence.' . $analysis->recurrence)</span>
                @endif
                @include('databackd.teams.analysis._stars', ['rating' => $analysis->client_rating])
            </div>
            <x-slot name="actions">
                <x-forms.link-secondary :link="route('databackd.teams.analysis.index')" icon="arrow-left">
                    @lang('databackd-teams-analysis.back_to_list')
                </x-forms.link-secondary>
                @if ($isStaff)
                    <div class="dbk-teams-status-select">
                        <select id="status-select" class="form-control select-picker" data-size="6" aria-label="@lang('databackd-teams-analysis.fields.status')">
                            @foreach (\App\Models\Databackd\AnalysisRequest::STATUSES as $status)
                                <option value="{{ $status }}" @selected($analysis->status === $status)>@lang('databackd-teams-common.analysis_status.' . $status)</option>
                            @endforeach
                        </select>
                    </div>
                    <x-forms.link-secondary :link="route('databackd.teams.analysis.edit', $analysis->id)" icon="edit">
                        @lang('databackd-teams-analysis.edit_request')
                    </x-forms.link-secondary>
                @endif
            </x-slot>
        </x-dbk.page-header>

        {{-- AFV (S12): where the request is now and what happens next --}}
        @php
            $steps = \App\Models\Databackd\AnalysisRequest::STATUSES;
            $currentStep = array_search($analysis->status, $steps, true);
        @endphp
        <div class="dbk-card dbk-teams-progress-card">
            <ol class="dbk-teams-stepper" aria-label="@lang('databackd-teams-analysis.fields.status')">
                @foreach ($steps as $i => $step)
                    <li @class(['is-done' => $i < $currentStep, 'is-current' => $i === $currentStep]) @if ($i === $currentStep) aria-current="step" @endif>
                        <span class="dbk-teams-step-dot" aria-hidden="true">@if ($i < $currentStep)<i class="fa fa-check"></i>@else{{ $i + 1 }}@endif</span>
                        <span class="dbk-teams-step-label">@lang('databackd-teams-common.analysis_status.' . $step)</span>
                    </li>
                @endforeach
            </ol>
            <p class="dbk-teams-step-hint">{{ __('databackd-teams-analysis.status_hint.' . ($isClient ? 'client_' : 'staff_') . $analysis->status) }}</p>
        </div>

        <div class="dbk-teams-layout is-8-4">
            <div>

                <!-- REQUEST DATA -->
                <x-dbk.card :title="__('databackd-teams-analysis.sections.request')">
                    @if ($analysis->description)
                        <p class="dbk-teams-pre mb-4">{{ $analysis->description }}</p>
                    @endif
                    <dl class="dbk-teams-detail">
                        <div><dt>@lang('databackd-teams-analysis.fields.type')</dt><dd>@lang('databackd-teams-common.analysis_type.' . $analysis->type)</dd></div>
                        <div><dt>@lang('databackd-teams-analysis.fields.period')</dt>
                            <dd>{{ $analysis->period_from || $analysis->period_to ? $fmtDate($analysis->period_from) . ' – ' . $fmtDate($analysis->period_to) : '-' }}</dd></div>
                        <div><dt>@lang('databackd-teams-analysis.fields.due_date')</dt>
                            <dd class="{{ $isOverdue ? 'an-overdue' : '' }}">{{ $fmtDate($analysis->due_date) }}
                                @if ($isOverdue)<i class="fa fa-exclamation-circle ml-1"></i>@endif</dd></div>
                        <div><dt>@lang('databackd-teams-analysis.fields.recurrence')</dt><dd>@lang('databackd-teams-common.recurrence.' . $analysis->recurrence)</dd></div>
                        <div><dt>@lang('databackd-teams-analysis.fields.team_request')</dt>
                            <dd>
                                @if ($analysis->teamRequest)
                                    @if ($teamRequestUrl)
                                        <a href="{{ $teamRequestUrl }}">{{ $analysis->teamRequest->title }}</a>
                                    @else
                                        {{ $analysis->teamRequest->title }}
                                    @endif
                                @else - @endif
                            </dd></div>
                        <div><dt>@lang('databackd-teams-analysis.fields.created')</dt><dd>{{ $analysis->created_at?->timezone(company()->timezone)->translatedFormat($df . ' H:i') ?? '-' }}</dd></div>
                        @if ($analysis->parent)
                            <div><dt>@lang('databackd-teams-analysis.fields.parent')</dt>
                                <dd><a href="{{ route('databackd.teams.analysis.show', $analysis->parent->id) }}">#{{ $analysis->parent->id }} · {{ $fmtDate($analysis->parent->due_date) }}</a></dd></div>
                        @endif
                        @if ($child)
                            <div><dt>@lang('databackd-teams-analysis.fields.next_instance')</dt>
                                <dd><a href="{{ route('databackd.teams.analysis.show', $child->id) }}">#{{ $child->id }}</a></dd></div>
                        @endif
                        @if ($analysis->delivered_at)
                            <div><dt>@lang('databackd-teams-analysis.fields.delivered_at')</dt><dd>{{ $analysis->delivered_at->timezone(company()->timezone)->format($df . ' H:i') }}</dd></div>
                        @endif
                    </dl>
                </x-dbk.card>

                <!-- RESULT -->
                <x-dbk.card :title="__('databackd-teams-analysis.sections.result')">
                    @if ($hasResult)
                        <div class="d-flex flex-wrap" style="gap: 10px">
                            @if ($analysis->deliverable_file)
                                <x-forms.link-primary :link="route('databackd.teams.analysis.download', $analysis->id)" icon="download">
                                    @lang('databackd-teams-analysis.result.download')
                                </x-forms.link-primary>
                            @endif
                            @if ($safeLink)
                                <a href="{{ $safeLink }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary rounded f-14 p-2">
                                    <i class="fa fa-external-link-alt mr-1"></i> @lang('databackd-teams-analysis.result.open_link')
                                </a>
                            @endif
                        </div>
                    @else
                        <x-dbk.empty icon="hourglass-split" size="sm" :text="__('databackd-teams-analysis.result.none')" />
                    @endif

                    @if ($isStaff)
                        <hr class="dbk-teams-divider">
                        <form id="deliverable-form" enctype="multipart/form-data" autocomplete="off">
                            @csrf
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="f-14 text-dark-grey" for="deliverable_file">@lang('databackd-teams-analysis.result.file')</label>
                                    <input type="file" class="form-control-file f-13" name="deliverable_file" id="deliverable_file">
                                    @if ($analysis->deliverable_file)
                                        <small class="form-text text-muted">@lang('databackd-teams-analysis.result.replace_help')</small>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    @if ($linkTargets)
                                        <label class="f-14 text-dark-grey" for="deliverable_route">@lang('databackd-teams-analysis.result.saved_report')</label>
                                        <select name="deliverable_route" id="deliverable_route" class="form-control select-picker" data-size="8">
                                            <option value="">@lang('databackd-teams-analysis.result.saved_report_none')</option>
                                            @foreach ($linkTargets as $key => $url)
                                                <option value="{{ $key }}" @selected($analysis->deliverable_link === $url)>@lang('databackd-teams-analysis.link_targets.' . $key)</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <div class="col-12 mt-3">
                                    <label class="f-14 text-dark-grey" for="deliverable_link">@lang('databackd-teams-analysis.result.link')</label>
                                    <input type="text" class="form-control height-35 f-14" name="deliverable_link" id="deliverable_link"
                                           value="{{ $analysis->deliverable_link }}" placeholder="https://… / /account/…">
                                </div>
                                @unless (in_array($analysis->status, ['delivered', 'closed'], true))
                                    <div class="col-12 mt-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="mark_delivered" id="mark_delivered" value="1" checked>
                                            <label class="form-check-label f-14 text-dark-grey" for="mark_delivered">@lang('databackd-teams-analysis.result.mark_delivered')</label>
                                        </div>
                                    </div>
                                @endunless
                            </div>
                            <button type="button" id="save-deliverable" class="btn btn-primary rounded f-14 p-2 mt-3">
                                <i class="fa fa-upload mr-1"></i> @lang('databackd-teams-analysis.result.save')
                            </button>
                        </form>
                    @endif
                </x-dbk.card>

                <!-- COMMENTS -->
                <x-dbk.card :title="__('databackd-teams-analysis.sections.comments') . ' (' . $analysis->comments->count() . ')'" id="comments">
                    @forelse ($analysis->comments as $comment)
                        @php $author = $comment->user; @endphp
                        <div class="an-comment">
                            <img src="{{ $author?->image_url ?? asset('img/gravatar.png') }}" class="an-avatar" alt="">
                            <div class="flex-grow-1">
                                <div class="dbk-teams-comment-head">
                                    <span class="dbk-teams-comment-author">
                                        {{ $author?->name ?? '-' }}
                                        @if ($author && $author->id === $analysis->client_id)
                                            <span class="an-type ml-1">@lang('databackd-teams-analysis.comments.client')</span>
                                        @endif
                                    </span>
                                    <span class="dbk-teams-muted" title="{{ $comment->created_at?->timezone(company()->timezone)->format($df . ' H:i') }}">
                                        {{ $comment->created_at?->timezone(company()->timezone)->diffForHumans() }}
                                    </span>
                                </div>
                                <div class="an-comment-text">{{ $comment->comment }}</div>
                                @if ($comment->file)
                                    <a href="{{ route('databackd.teams.analysis.comment_download', $comment->id) }}" class="f-13 d-inline-block mt-2">
                                        <i class="fa fa-paperclip mr-1"></i>@lang('databackd-teams-analysis.comments.attachment')
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <x-dbk.empty icon="chat-left-text" size="sm" :text="__('databackd-teams-analysis.comments.none')" />
                    @endforelse

                    <hr class="dbk-teams-divider">
                    <form id="comment-form" enctype="multipart/form-data" autocomplete="off">
                        @csrf
                        <div class="form-group mb-2">
                            <label class="sr-only" for="comment">@lang('databackd-teams-analysis.sections.comments')</label>
                            <textarea name="comment" id="comment" rows="3" class="form-control f-14" maxlength="5000"
                                      placeholder="@lang('databackd-teams-analysis.comments.placeholder')"></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <label class="f-13 text-dark-grey mb-0 mr-2" for="comment_file"><i class="fa fa-paperclip mr-1"></i>@lang('databackd-teams-analysis.comments.attach')</label>
                                <input type="file" class="f-13" name="file" id="comment_file" aria-describedby="comment_file_help">
                                <small class="form-text text-muted" id="comment_file_help">@lang('databackd-teams-analysis.comments.file_help')</small>
                            </div>
                            <button type="button" id="save-comment" class="btn btn-primary rounded f-14 p-2 mt-2 mt-md-0">
                                <i class="fa fa-paper-plane mr-1"></i> @lang('databackd-teams-analysis.comments.send')
                            </button>
                        </div>
                    </form>
                </x-dbk.card>
            </div>

            <div>

                <!-- PEOPLE -->
                <x-dbk.card :title="__('databackd-teams-analysis.sections.people')">
                    <dl class="dbk-teams-people">
                        @if ($isStaff)
                            <dt>@lang('databackd-teams-analysis.fields.client')</dt>
                            <dd>
                                <img src="{{ $analysis->client?->image_url ?? asset('img/gravatar.png') }}" class="dbk-teams-avatar" alt="">{{ $analysis->client?->name ?? '-' }}
                            </dd>
                        @endif
                        <dt>@lang('databackd-teams-analysis.fields.assignee')</dt>
                        <dd>
                            @if ($analysis->assignee)
                                <img src="{{ $analysis->assignee->image_url }}" class="dbk-teams-avatar" alt="">{{ $analysis->assignee->name }}
                            @else
                                <span class="dbk-text-muted">@lang('databackd-teams-analysis.unassigned')</span>
                            @endif
                        </dd>
                    </dl>
                </x-dbk.card>

                <!-- MANAGE (staff) -->
                @if ($isStaff)
                    <x-dbk.card :title="__('databackd-teams-analysis.sections.manage')">
                        <form id="manage-form" autocomplete="off">
                            @csrf
                            @method('PUT')
                            <div class="form-group">
                                <label class="f-14 text-dark-grey" for="assigned_to">@lang('databackd-teams-analysis.fields.assignee')</label>
                                <select name="assigned_to" id="assigned_to" class="form-control select-picker" data-live-search="true" data-size="8">
                                    <option value="">@lang('databackd-teams-analysis.unassigned')</option>
                                    @foreach ($specialists as $specialist)
                                        <option value="{{ $specialist->id }}" @selected($analysis->assigned_to == $specialist->id)>{{ $specialist->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="f-14 text-dark-grey" for="priority">@lang('databackd-teams-analysis.fields.priority')</label>
                                <select name="priority" id="priority" class="form-control select-picker" data-size="6">
                                    @foreach (\App\Models\Databackd\AnalysisRequest::PRIORITIES as $priority)
                                        <option value="{{ $priority }}" @selected($analysis->priority === $priority)>@lang('databackd-teams-common.priority.' . $priority)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="f-14 text-dark-grey" for="due_date">@lang('databackd-teams-analysis.fields.due_date')</label>
                                <input type="text" class="form-control height-35 f-14" name="due_date" id="due_date"
                                       value="{{ $analysis->due_date?->format($df) }}" placeholder="@lang('placeholders.date')">
                            </div>
                            <button type="button" id="save-manage" class="btn btn-primary rounded f-14 p-2">
                                <i class="fa fa-check mr-1"></i> @lang('databackd-teams-analysis.form.save')
                            </button>
                        </form>
                    </x-dbk.card>
                @endif

                <!-- RATING -->
                <x-dbk.card :title="__('databackd-teams-analysis.sections.rating')">
                    @if ($canRate)
                        <form id="rate-form" autocomplete="off">
                            @csrf
                            <input type="hidden" name="client_rating" id="client_rating" value="{{ $analysis->client_rating }}">
                            {{-- AFV (S12): real buttons - keyboard / screen reader usable, 44 px touch targets --}}
                            <div class="an-rate-stars mb-3" id="rate-stars" role="group" aria-label="@lang('databackd-teams-analysis.attributes.client_rating')">
                                @for ($i = 1; $i <= 5; $i++)
                                    <button type="button" class="an-star {{ $analysis->client_rating >= $i ? 'on' : '' }}" data-v="{{ $i }}"
                                            aria-pressed="{{ (int)$analysis->client_rating === $i ? 'true' : 'false' }}"
                                            aria-label="{{ __('databackd-teams-analysis.rating.star', ['value' => $i]) }}"><i class="fa fa-star" aria-hidden="true"></i></button>
                                @endfor
                            </div>
                            <div class="form-group">
                                <label class="sr-only" for="client_feedback">@lang('databackd-teams-analysis.attributes.client_feedback')</label>
                                <textarea name="client_feedback" id="client_feedback" rows="3" class="form-control f-14" maxlength="2000"
                                          placeholder="@lang('databackd-teams-analysis.rating.placeholder')">{{ $analysis->client_feedback }}</textarea>
                            </div>
                            <button type="button" id="save-rating" class="btn btn-primary rounded f-14 p-2">
                                <i class="fa fa-star mr-1"></i> {{ $analysis->client_rating ? __('databackd-teams-analysis.rating.update') : __('databackd-teams-analysis.rating.submit') }}
                            </button>
                        </form>
                    @elseif ($analysis->client_rating)
                        <div class="mb-2">@include('databackd.teams.analysis._stars', ['rating' => $analysis->client_rating])</div>
                        @if ($analysis->client_feedback)
                            <p class="dbk-teams-pre">{{ $analysis->client_feedback }}</p>
                        @endif
                    @else
                        <p class="dbk-teams-muted mb-0">{{ $isStaff ? __('databackd-teams-analysis.rating.not_rated') : __('databackd-teams-analysis.rating.after_delivery') }}</p>
                    @endif
                </x-dbk.card>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var send = function (url, form, button, extra) {
                $.easyAjax($.extend({
                    url: url,
                    container: form,
                    type: 'POST',
                    disableButton: true,
                    blockUI: true,
                    buttonSelector: button,
                    file: true,
                    data: {},
                    success: function (response) {
                        if (response.status === 'success') {
                            window.location.href = response.redirectUrl || window.location.href;
                            if ((response.redirectUrl || '').indexOf('#') !== -1) {
                                window.location.reload();
                            }
                        }
                    }
                }, extra || {}));
            };

            @if ($isStaff)
            $('#status-select').on('change', function () {
                $.easyAjax({
                    url: "{{ route('databackd.teams.analysis.move', $analysis->id) }}",
                    type: 'POST',
                    blockUI: true,
                    container: '.content-wrapper',
                    data: {_token: '{{ csrf_token() }}', status: $(this).val()},
                    success: function (response) {
                        if (response.status === 'success') {
                            window.location.reload();
                        }
                    }
                });
            });

            datepicker('#due_date', {position: 'bl', ...datepickerConfig});

            $('#save-manage').click(function () {
                send("{{ route('databackd.teams.analysis.update', $analysis->id) }}", '#manage-form', '#save-manage');
            });

            $('#save-deliverable').click(function () {
                send("{{ route('databackd.teams.analysis.deliverable', $analysis->id) }}", '#deliverable-form', '#save-deliverable');
            });
            @endif

            $('#save-comment').click(function () {
                send("{{ route('databackd.teams.analysis.comment', $analysis->id) }}", '#comment-form', '#save-comment');
            });

            @if ($canRate)
            var paint = function (v) {
                $('#rate-stars .an-star').each(function () {
                    $(this).toggleClass('on', $(this).data('v') <= v);
                });
            };
            $('#rate-stars .an-star').on('mouseenter focus', function () { paint($(this).data('v')); })
                .on('click', function () {
                    $('#client_rating').val($(this).data('v'));
                    $('#rate-stars .an-star').attr('aria-pressed', 'false');
                    $(this).attr('aria-pressed', 'true');
                    paint($(this).data('v'));
                });
            $('#rate-stars').on('mouseleave focusout', function () { paint(parseInt($('#client_rating').val() || 0, 10)); });

            $('#save-rating').click(function () {
                send("{{ route('databackd.teams.analysis.rate', $analysis->id) }}", '#rate-form', '#save-rating');
            });
            @endif

            init('.content-wrapper');
        });
    </script>
@endpush
