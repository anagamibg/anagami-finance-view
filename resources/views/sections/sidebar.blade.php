<!-- SIDEBAR START -->
<aside class="{{ !user()->dark_theme ? 'sidebar-' . $appTheme->sidebar_theme : '' }}">
    <!-- MOBILE CLOSE SIDEBAR PANEL START -->
    <div class="mobile-close-sidebar-panel w-100 h-100" onclick="closeMobileMenu()" id="mobile_close_panel"></div>
    <!-- MOBILE CLOSE SIDEBAR PANEL END -->
    @php
        $userName = (session()->has('clientContact') && session('clientContact')) ? session('clientContact')->contact_name : user()->name;
    @endphp
    <!-- MAIN SIDEBAR START -->
    <div class="main-sidebar" id="mobile_menu_collapse">
        <!-- SIDEBAR BRAND START -->
        <div class="sidebar-brand-box dropdown cursor-pointer {{ user()->dark_theme ? 'bg-dark' : '' }}">
            <div class="dropdown-toggle sidebar-brand d-flex align-items-center justify-content-between  w-100"
                type="link" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">

                @if (companyOrGlobalSetting()->sidebar_logo_style !== 'full')
                    <!-- SIDEBAR BRAND NAME START -->
                    <div class="sidebar-brand-name">
                        {{-- AFV ui: company name wraps to 2 lines (full name in the tooltip), user name ellipsis + tooltip --}}
                        <h1 class="mb-0 f-16 f-w-500 text-white-shade mt-0 dbk-sb-company" data-placement="bottom" data-toggle="tooltip"
                            data-original-title="{{ $appName }}"><span class="dbk-sb-company-name">{{ $appName }}</span>
                            <i class="icon-arrow-down icons pl-2" aria-hidden="true"></i>
                        </h1>
                        <div class="mb-0 position-relative pro-name">
                            <span class="bg-light-green rounded-circle"></span>
                            <p class="f-13 text-lightest mb-0 dbk-sb-user" data-placement="bottom" data-toggle="tooltip"
                                data-original-title="{{ $userName }}">{{ $userName }}</p>
                        </div>
                    </div>
                    <!-- SIDEBAR BRAND NAME END -->
                    <!-- SIDEBAR BRAND LOGO START -->
                    <div class="sidebar-brand-logo">
                        <img src="{{ companyOrGlobalSetting()->logo_url }}">
                    </div>
                    <!-- SIDEBAR BRAND LOGO END -->
                @else
                    <!-- SIDEBAR BRAND NAME START -->
                    <div class="sidebar-brand-name">
                        <h1 class="mb-0 f-16 f-w-500 text-white-shade mt-0" data-placement="bottom"
                            data-toggle="tooltip" data-original-title="{{ $appName }}">
                            <img src="{{ companyOrGlobalSetting()->logo_url }}">
                        </h1>
                    </div>
                    <!-- SIDEBAR BRAND NAME END -->
                    <!-- SIDEBAR BRAND LOGO START -->
                    <div class="sidebar-brand-logo text-white-shade f-12">
                        <i class="icon-arrow-down icons pl-2"></i>
                    </div>
                    <!-- SIDEBAR BRAND LOGO END -->
                @endif
            </div>
            <!-- DROPDOWN - INFORMATION -->
            <div class="dropdown-menu dropdown-menu-right sidebar-brand-dropdown ml-3"
                aria-labelledby="dropdownMenuLink" tabindex="0">
                <div class="d-flex justify-content-between align-items-center profile-box">
                    <a @if(in_array('client', user_roles())) href="{{ route('profile-settings.index') }}" @elseif (user()->is_superadmin) href="{{ route('superadmin.settings.super-admin-profile.index') }}" @else href="{{ databackd_employee_url(user()->id) ?? route('profile-settings.index') }}" @endif >
                        <div class="profileInfo d-flex align-items-center mr-1 flex-wrap">
                            <div class="profileImg mr-2">
                                <img class="h-100" src="{{ $user->image_url }}"
                                    alt="{{ $userName }}">
                            </div>
                            <div class="ProfileData">
                                <h3 class="f-15 f-w-500 text-dark" data-placement="bottom" data-toggle="tooltip"
                                    data-original-title="{{ $userName }}">{{ $userName }}</h3>
                                <p class="mb-0 f-12 text-dark-grey">{{ user()->employeeDetail->designation->name ?? '' }}</p>
                            </div>
                        </div>
                    </a>

                    {{-- SAAS --}}
                    @if(user()->is_superadmin)
                        <a href="{{ route('superadmin.settings.super-admin-profile.index') }}"
                           data-toggle="tooltip"
                           data-original-title="{{ __('app.menu.profileSettings') }}">
                            <i class="side-icon bi bi-pencil-square"></i>
                        </a>
                    @else
                        <a href="{{ route('profile-settings.index') }}" data-toggle="tooltip"
                           data-original-title="{{ __('app.menu.profileSettings') }}">
                            <i class="side-icon bi bi-pencil-square"></i>
                        </a>
                    @endif
                </div>
                @if (checkCompanyCanAddMoreEmployees(user()->company_id))
                    @if (!in_array('client', user_roles()) && ($sidebarUserPermissions['add_employees'] == 4 || $sidebarUserPermissions['add_employees'] == 1) && in_array('employees', user_modules()))
                        <a class="dropdown-item d-flex justify-content-between align-items-center f-15 text-dark invite-member"
                            href="javascript:;">
                            <span>@lang('app.inviteMember') {{ ($companyName) }}</span>
                            <i class="side-icon bi bi-person-plus"></i>
                        </a>
                    @endif
                @endif

                <a class="dropdown-item d-flex justify-content-between align-items-center f-15 text-dark"
                    href="javascript:;">
                    <label for="dark-theme-toggle">@lang('app.darkTheme')</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="dark-theme-toggle"
                            @if (user()->dark_theme) checked @endif>
                        <label class="custom-control-label f-14" for="dark-theme-toggle"></label>
                    </div>
                </a>
                <a class="dropdown-item d-flex justify-content-between align-items-center f-15 text-dark"
                    href="{{ route('logout') }}" onclick="event.preventDefault();
                document.getElementById('logout-form').submit();">
                    @lang('app.logout')
                    <i class="side-icon bi bi-power"></i>
                </a>

                {{-- SAAS --}}
                @include('super-admin.sections.choose-company')

            </div>
        </div>
        <!-- SIDEBAR BRAND END -->

        {{-- AFV ui (S12): menu filter - typed text shows only the matching entries and opens their groups (Ctrl/⌘+K, Esc clears) --}}
        <div class="dbk-sb-filter {{ user()->dark_theme ? 'bg-dark' : '' }}" role="search">
            <label for="dbk-sb-filter-input" class="sr-only">{{ __('databackd-ui.menu_filter.label') }}</label>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" class="dbk-sb-filter-icon" aria-hidden="true" viewBox="0 0 16 16">
                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
            </svg>
            <input type="text" id="dbk-sb-filter-input" class="dbk-sb-filter-input" autocomplete="off" spellcheck="false"
                   placeholder="{{ __('databackd-ui.menu_filter.placeholder') }}" title="{{ __('databackd-ui.menu_filter.shortcut_hint') }}"
                   aria-controls="sideMenuScroll" aria-keyshortcuts="Control+K Meta+K" enterkeyhint="go">
            <kbd class="dbk-sb-filter-kbd" id="dbk-sb-filter-kbd" aria-hidden="true">Ctrl K</kbd>
            <button type="button" class="dbk-sb-filter-clear" id="dbk-sb-filter-clear" hidden
                    aria-label="{{ __('databackd-ui.menu_filter.clear') }}" title="{{ __('databackd-ui.menu_filter.clear') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" aria-hidden="true" viewBox="0 0 16 16">
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                </svg>
            </button>
        </div>

        <!-- SIDEBAR MENU START -->
        <div class="sidebar-menu {{ user()->dark_theme ? 'bg-dark' : '' }}" id="sideMenuScroll">
            {{-- SAAS --}}
            <p class="dbk-sb-filter-empty" id="dbk-sb-filter-empty" role="status" hidden>{{ __('databackd-ui.menu_filter.empty') }}</p>
            @if(user()->is_superadmin)
                @include('super-admin.sections.super-admin-menu')
            @else()
                @include('sections.menu')
            @endif
        </div>
        <!-- SIDEBAR MENU END -->
    </div>
    <!-- MAIN SIDEBAR END -->
    <!-- Sidebar Toggler -->
    <div
        class="text-center d-flex justify-content-between align-items-center position-fixed sidebarTogglerBox {{ user()->dark_theme ? 'bg-dark' : '' }}">
        <button class="border-0 d-lg-block d-none text-lightest font-weight-bold" id="sidebarToggle"></button>
        {{-- databackd: no mobile app button / app version in the sidebar footer --}}
    </div>
    <!-- Sidebar Toggler -->

    @include('sections.raise-support-ticket-modal')
</aside>
<!-- SIDEBAR END -->

<script>
    // AFV ui: sidebar active state, collapsible AFV groups (remembered), menu filter (Ctrl/⌘+K),
    // keyboard access, collapsed-rail tooltips and flyouts, mobile drawer close.
    // Legacy groups (li.accordionItem) are toggled by public/js/main.js toggleItem(), which compares the whole className:
    // never add classes to those <li> elements here - only attributes.
    $(function () {
        var $menu = $('#sideMenuScroll');
        if (!$menu.length) {
            return;
        }

        var GROUPS_KEY = 'dbk-sb-groups';
        var readGroups = function () {
            try { return JSON.parse(window.localStorage.getItem(GROUPS_KEY) || '{}') || {}; } catch (e) { return {}; }
        };
        var saveGroup = function (key, open) {
            try {
                var saved = readGroups();
                saved[key] = open ? 1 : 0;
                window.localStorage.setItem(GROUPS_KEY, JSON.stringify(saved));
            } catch (e) { /* private mode / storage blocked: the state is simply not remembered */ }
        };
        var setGroupOpen = function (li, open) {
            li.classList.toggle('is-open', open);
            $(li).children('.dbk-sb-group-toggle').attr('aria-expanded', open ? 'true' : 'false');
        };

        // 1. Active item = the menu link whose path (or one of its data-dbk-match paths) is the longest prefix of the
        //    current path (custom.js only matches the exact URL / the first path segment, so nested pages lost their item).
        var norm = function (p) { return (p || '').replace(/\/+$/, ''); };
        var here = norm(window.location.pathname);
        var best = null, bestLen = -1;
        $menu.find('a[href]').each(function () {
            if (this.target === '_blank' || this.host !== window.location.host) {
                return;
            }
            var p = norm(this.pathname);
            var at = p.indexOf('/account/');
            if (at === -1) {
                return;
            }
            var candidates = [p], base = p.substring(0, at);
            (this.getAttribute('data-dbk-match') || '').split(/\s+/).forEach(function (m) {
                if (m) {
                    candidates.push(base + '/' + m.replace(/^\/+|\/+$/g, ''));
                }
            });
            for (var i = 0; i < candidates.length; i++) {
                var c = candidates[i];
                if ((here === c || here.indexOf(c + '/') === 0) && c.length > bestLen) {
                    best = this;
                    bestLen = c.length;
                }
            }
        });

        if (best) {
            $menu.find('a.active, button.active').removeClass('active').removeAttr('aria-current');
            $menu.find('li.accordionItem.openIt').each(function () {
                this.className = this.className.replace('openIt', 'closeIt');
            });
            $menu.find('li.dbk-sb-group.is-current').each(function () {
                this.classList.remove('is-current');
                setGroupOpen(this, readGroups()[this.getAttribute('data-dbk-group')] === 1);
            });
            var $best = $(best).addClass('active').attr('aria-current', 'page');
            var $dbkGroup = $best.closest('li.dbk-sb-group');
            var $group = $best.closest('.accordionItemContent').closest('li.accordionItem');
            if ($dbkGroup.length) {
                $dbkGroup[0].classList.add('is-current');
                setGroupOpen($dbkGroup[0], true);
                $dbkGroup.children('.dbk-sb-group-toggle').addClass('active');
            } else if ($group.length) {
                $group[0].className = $group[0].className.replace('closeIt', 'openIt');
                $group.children('a.accordionItemHeading').addClass('active');
            }
        }

        // keep the active item visible in a long menu
        var activeEl = $menu.find('.accordionItemContent a.active, .dbk-sb-group-items a.active, li > a.nav-item.active').last()[0];
        if (activeEl) {
            var menuRect = $menu[0].getBoundingClientRect(), itemRect = activeEl.getBoundingClientRect();
            if (itemRect.bottom > menuRect.bottom - 24 || itemRect.top < menuRect.top) {
                $menu[0].scrollTop += itemRect.top - menuRect.top - menuRect.height / 3;
            }
        }

        // 2. AFV groups: toggle + remember (several can be open; the group of the current page opens on every load)
        $menu.on('click', '.dbk-sb-group-toggle', function () {
            var li = this.parentNode, open = !li.classList.contains('is-open');
            setGroupOpen(li, open);
            saveGroup(li.getAttribute('data-dbk-group'), open);
        });

        // legacy group headings are focusable (tabindex) - open them with Enter / Space
        $menu.on('keydown', '.accordionItemHeading', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });

        // Arrow Up / Down move between the visible menu entries
        var focusables = function () {
            return $menu.find('a.nav-item, button.nav-item, .accordionItemContent a, .dbk-sb-group-items a').filter(function () {
                return this.offsetParent !== null && this.getBoundingClientRect().height > 0;
            });
        };
        $menu.on('keydown', 'a, button', function (e) {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') {
                return;
            }
            var $all = focusables(), i = $all.index(this);
            if (i === -1) {
                return;
            }
            e.preventDefault();
            if (e.key === 'ArrowUp' && i === 0) {
                $('#dbk-sb-filter-input').trigger('focus');
                return;
            }
            var next = $all[e.key === 'ArrowDown' ? Math.min(i + 1, $all.length - 1) : i - 1];
            if (next) {
                next.focus();
            }
        });

        // 3. Collapsed rail: tooltips for plain links, flyout (title + sub-items) for groups
        var isRail = function () {
            return document.body.classList.contains('sidebar-toggled') && window.innerWidth >= 992;
        };

        $menu.find('li.accordionItem').each(function () {
            var title = $(this).children('a.nav-item').find('.dbk-sb-label').text().trim();
            $(this).children('.accordionItemContent').first().attr('data-dbk-title', title);
        });

        if ($.fn.tooltip) {
            $menu.find('li > a.nav-item').not('.accordionItemHeading').each(function () {
                var label = $(this).find('.dbk-sb-label, span.pl-3').first().text().trim() || this.getAttribute('title');
                $(this).tooltip({
                    title: label,
                    placement: 'right',
                    trigger: 'hover focus',
                    container: 'body',
                    boundary: 'window',
                    template: '<div class="tooltip dbk-sb-tooltip" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>'
                }).on('show.bs.tooltip', function (e) {
                    if (!isRail()) {
                        e.preventDefault();
                    }
                });
            });
        }

        var placeFlyout = function () {
            if (!isRail()) {
                return;
            }
            var li = this, rect = li.getBoundingClientRect(), top = rect.top;
            $(li).children('.accordionItemContent, .dbk-sb-group-items').each(function () {
                var h = Math.min(this.scrollHeight + 2, window.innerHeight - 16);
                var t = Math.max(8, Math.min(top, window.innerHeight - h - 8));
                this.style.setProperty('--dbk-flyout-top', t + 'px');
                this.style.setProperty('--dbk-flyout-max', (window.innerHeight - t - 8) + 'px');
                top = t + h + 4;
            });
        };
        $menu.on('mouseenter focusin', 'li.accordionItem, li.dbk-sb-group', placeFlyout);

        // 4. Menu filter: shows only the entries whose name (or group name) contains the text, opens their groups.
        //    Ctrl/⌘+K focuses it (expands the collapsed rail for the search), Esc clears it, Enter opens the first match.
        var $filter = $('#dbk-sb-filter-input'), $clear = $('#dbk-sb-filter-clear'), $empty = $('#dbk-sb-filter-empty');
        var railCollapsedBySearch = false;
        var squash = function (s) {
            return (s || '').toLocaleLowerCase().replace(/\s+/g, ' ').trim();
        };
        var labelOf = function (el) {
            var $l = $(el).find('.dbk-sb-label');
            return squash(($l.length ? $l.first() : $(el)).text());
        };
        var hide = function (el, hidden) {
            if (hidden) {
                el.setAttribute('data-dbk-hidden', '');
            } else {
                el.removeAttribute('data-dbk-hidden');
            }
        };
        var firstMatch = null;

        var applyFilter = function () {
            var q = squash($filter.val());
            // top-level entries (some are wrapped in a div, e.g. the mobile-only ones), in document order
            var $items = $menu.find('li').filter(function () { return $(this).parents('li').length === 0; });
            $menu.toggleClass('dbk-sb-filtering', q !== '');
            $clear.prop('hidden', q === '');
            firstMatch = null;

            if (q === '') {
                $menu.find('[data-dbk-hidden]').each(function () { hide(this, false); });
                $menu.find('[data-dbk-filter-open]').removeAttr('data-dbk-filter-open');
                $empty.prop('hidden', true);
                return;
            }

            var total = 0, $sectionTitle = null, sectionCount = 0;
            var closeSection = function () {
                if ($sectionTitle) {
                    hide($sectionTitle[0], sectionCount === 0);
                }
            };

            $items.each(function () {
                var li = this, $li = $(li);
                if ($li.hasClass('dbk-sb-section')) {
                    closeSection();
                    $sectionTitle = $li;
                    sectionCount = 0;
                    return;
                }
                var $heading = $li.children('.accordionItemHeading, .dbk-sb-group-toggle');
                var $content = $li.children('.accordionItemContent, .dbk-sb-group-items');
                var count = 0;

                if ($heading.length && $content.length) {
                    var groupHit = labelOf($heading[0]).indexOf(q) !== -1;
                    $content.find('a[href]').each(function () {
                        var hit = groupHit || labelOf(this).indexOf(q) !== -1;
                        hide(this, !hit);
                        if (hit) {
                            count++;
                            firstMatch = firstMatch || this;
                        }
                    });
                    $content.find('.dbk-sb-group-title').each(function () { hide(this, true); });
                    if (count) {
                        li.setAttribute('data-dbk-filter-open', '');
                    } else {
                        li.removeAttribute('data-dbk-filter-open');
                    }
                } else {
                    var $link = $li.children('a[href]').first();
                    if ($link.length && labelOf($link[0]).indexOf(q) !== -1) {
                        count = 1;
                        firstMatch = firstMatch || $link[0];
                    }
                }
                hide(li, count === 0);
                sectionCount += count;
                total += count;
            });
            closeSection();
            $empty.prop('hidden', total > 0);
        };

        var clearFilter = function () {
            $filter.val('');
            applyFilter();
        };

        $filter.on('input', applyFilter);
        $clear.on('click', function () {
            clearFilter();
            $filter.trigger('focus');
        });
        $filter.on('keydown', function (e) {
            if (e.key === 'Escape') {
                if ($filter.val() !== '') {
                    e.preventDefault(); // first Esc clears the text, the next one leaves the field / closes the mobile drawer
                    clearFilter();
                } else {
                    $filter.trigger('blur');
                    if (railCollapsedBySearch) {
                        document.body.classList.add('sidebar-toggled');
                        railCollapsedBySearch = false;
                    }
                }
            } else if (e.key === 'Enter' && firstMatch) {
                e.preventDefault();
                firstMatch.click();
            } else if (e.key === 'ArrowDown') {
                var first = focusables()[0];
                if (first) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        // shortcut hint: ⌘K on Apple devices, Ctrl K elsewhere
        if (/Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent)) {
            $('#dbk-sb-filter-kbd').text('⌘K');
        }

        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && (e.key === 'k' || e.key === 'K' || e.keyCode === 75)) {
                // the shortcut stays with editors (Quill: Ctrl+K = insert link), form fields and open modals
                if (!$filter.length || e.isDefaultPrevented() || $('.modal.show').length
                    || (e.target !== $filter[0] && $(e.target).closest('input, textarea, select, [contenteditable], .ql-editor').length)) {
                    return;
                }
                e.preventDefault();
                if (window.innerWidth < 992 && !$('#mobile_menu_collapse').hasClass('toggled') && typeof openMobileMenu === 'function') {
                    openMobileMenu();
                }
                if (isRail()) {
                    document.body.classList.remove('sidebar-toggled');
                    railCollapsedBySearch = true;
                }
                $filter.trigger('focus').trigger('select');
            }
        });

        // the rail expanded for a search folds back when the focus leaves the sidebar
        $('#mobile_menu_collapse').on('focusout', function () {
            setTimeout(function () {
                if (railCollapsedBySearch && !document.getElementById('mobile_menu_collapse').contains(document.activeElement)) {
                    railCollapsedBySearch = false;
                    clearFilter();
                    document.body.classList.add('sidebar-toggled');
                }
            }, 0);
        });

        // a search typed into the box survives a reload of the same page (e.g. after a form post) - not across pages
        if ($filter.length && $filter.val()) {
            applyFilter();
        }

        // 5. Mobile drawer: Escape closes it
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && !e.isDefaultPrevented() && $('#mobile_menu_collapse').hasClass('toggled') && typeof closeMobileMenu === 'function') {
                closeMobileMenu();
            }
        });
    });
</script>

<script>
    $(document).ready(function() {

        $('.invite-member').click(function() {
            const url = "{{ route('employees.invite_member') }}";
            $(MODAL_LG + ' ' + MODAL_HEADING).html('...');
            $.ajaxModal(MODAL_LG, url);
        });

        $('#dark-theme-toggle').change(function() {
            const darkTheme = ($(this).is(':checked')) ? '1' : '0'

            $.easyAjax({
                type: 'POST',
                url: "{{ route('profile.dark_theme') }}",
                blockUI: true,
                data: {
                    '_token': '{{ csrf_token() }}',
                    'darkTheme': darkTheme
                },
                success: function(response) {
                    if (response.status === 'success') {
                        window.location.reload();
                    }
                }
            });

        });

    });
</script>
