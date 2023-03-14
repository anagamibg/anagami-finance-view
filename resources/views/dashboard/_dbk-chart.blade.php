{{-- AFV (S9/P3): AFV dashboard chart rendered through the global dbkChart helper instead of the
     Frappe / Chart.js components (x-bar-chart, x-line-chart, x-pie-chart).
     @include('dashboard._dbk-chart', [
        'id' => 'task-chart1', 'type' => 'bar'|'line'|'donut',
        'chartData' => [...],            // bar/line: labels, values (array | array of arrays when multiple), name
        'labels' => [...], 'values' => [...], 'colors' => [...],   // donut
        'multiple' => false, 'unit' => 'money'|'number', 'role' => 'revenue'|..., 'keepColors' => false, 'horizontal' => false, 'size' => null|'sm'|'lg',
     ]) --}}
@php
    $type = $type ?? 'bar';
    $multiple = !empty($multiple) && $multiple !== 'false';
    $unit = $unit ?? 'number';
    $size = $size ?? null;
    $role = $role ?? null;
    $arr = fn($v) => array_values($v instanceof \Illuminate\Support\Collection ? $v->all() : (array)($v ?? []));

    if ($type === 'donut') {
        $payload = [
            'labels' => $arr($labels ?? []),
            'values' => array_map('floatval', $arr($values ?? [])),
            'colors' => !empty($keepColors) ? $arr($colors ?? []) : null,
        ];
    } else {
        $chartData = $chartData instanceof \Illuminate\Support\Collection ? $chartData->all() : ($chartData ?? []);
        $series = $multiple ? $arr($chartData['values'] ?? []) : [$arr($chartData['values'] ?? [])];
        $names = $multiple ? $arr($chartData['name'] ?? []) : [$chartData['name'] ?? ''];
        $payload = [
            'labels' => $arr($chartData['labels'] ?? []),
            'datasets' => collect($series)->map(fn($data, $i) => [
                'label' => html_entity_decode((string)($names[$i] ?? ''), ENT_QUOTES),
                'data' => array_map('floatval', $arr($data)),
            ])->all(),
        ];
    }
@endphp

<x-dbk.chart :id="$id" :size="$size" />
<script>
    (function () {
        // inline in the page (before layouts/app loads dbkChart) or injected by the AJAX tab switch (already loaded)
        var run = function () {
        if (!window.dbkChart) return;
        var d = @json($payload);
        var emptyText = @json(__('messages.notEnoughData'));
        var unit = @json($unit);
        var role = @json($role);
        var type = @json($type);

        if (type === 'donut') {
            var items = d.labels.map(function (l, i) { return {label: l, value: d.values[i] || 0, color: d.colors ? d.colors[i] : null}; })
                .filter(function (x) { return x.value; });
            var folded = items.length > 6;
            if (folded) items = dbkChart.topN(items, 6);
            dbkChart.donut(@json($id), {
                labels: items.map(function (x) { return x.label; }),
                data: items.map(function (x) { return x.value; }),
                colors: d.colors && !folded ? items.map(function (x) { return x.color; }) : undefined,
                unit: unit,
                emptyText: emptyText
            });
            return;
        }

        var datasets = d.datasets.map(function (ds, i) {
            return role && d.datasets.length === 1 ? Object.assign(ds, {role: role}) : ds;
        });
        var horizontal = @json(!empty($horizontal));
        if (horizontal && datasets.length === 1) {
            // ranking: horizontal bars sorted desc
            var order = d.labels.map(function (l, i) { return i; })
                .sort(function (a, b) { return (datasets[0].data[b] || 0) - (datasets[0].data[a] || 0); });
            d.labels = order.map(function (i) { return d.labels[i]; });
            datasets[0].data = order.map(function (i) { return datasets[0].data[i]; });
        }
        var opts = {labels: d.labels, datasets: datasets, unit: unit, emptyText: emptyText, horizontal: horizontal};
        if (type === 'line') {
            opts.area = d.datasets.length === 1;
            opts.beginAtZero = true;
            dbkChart.line(@json($id), opts);
        } else {
            dbkChart.bar(@json($id), opts);
        }
        };
        if (window.dbkChart) run(); else document.addEventListener('DOMContentLoaded', run);
    })();
</script>
