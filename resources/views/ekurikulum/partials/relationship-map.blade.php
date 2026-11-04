@php
    $mapNodeWidth = 158;
    $mapNodeHeight = 42;
    $mapColumnGap = 194;
    $mapPositions = [];
    $mapRowCount = max(1, ...array_map('count', array_column($mapColumns, 'nodes')));
    $mapWidth = max(480, 36 + count($mapColumns) * $mapColumnGap);
    $mapHeight = max(170, 40 + $mapRowCount * 58);
    foreach ($mapColumns as $columnIndex => $column) {
        foreach ($column['nodes'] as $nodeIndex => $node) {
            $mapPositions[$node['id']] = ['x' => 14 + ($columnIndex * $mapColumnGap), 'y' => 28 + ($nodeIndex * 58)];
        }
    }
@endphp
<div class="ekur-relationship-graph mt-4">
    <div class="ekur-map-actions">
        <div><h3 class="h5">{{ $mapTitle }}</h3><p class="subtext">{{ $mapDescription }} Seret elemen untuk mengatur posisi peta.</p></div>
        <button class="button button-secondary" type="button" data-map-download data-map-filename="{{ $mapFilename }}"><span data-lucide="file-down" aria-hidden="true"></span> Unduh PNG</button>
    </div>
    <p class="subtext" data-map-download-status aria-live="polite"></p>
    <div class="ekur-relationship-scroll">
        <svg class="ekur-relationship-svg" data-curriculum-map data-map-storage-key="ekur-map:{{ $mapId }}" width="{{ $mapWidth }}" height="{{ $mapHeight }}" viewBox="0 0 {{ $mapWidth }} {{ $mapHeight }}" role="img" aria-label="{{ $mapTitle }}">
            <defs><marker id="map-arrow-{{ $mapId }}" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto"><path d="M0,0 L8,4 L0,8 z" fill="#78977a"></path></marker></defs>
            @foreach ($mapColumns as $columnIndex => $column)
                <text class="ekur-relationship-heading" x="{{ 14 + ($columnIndex * $mapColumnGap) }}" y="17">{{ $column['label'] }}</text>
            @endforeach
            @foreach ($mapEdges as $edge)
                @php $from = $mapPositions[$edge['source']] ?? null; $to = $mapPositions[$edge['target']] ?? null; @endphp
                @if ($from && $to)
                    <path class="ekur-relationship-edge {{ $edge['class'] ?? '' }}" data-map-edge data-source="{{ $edge['source'] }}" data-target="{{ $edge['target'] }}" marker-end="url(#map-arrow-{{ $mapId }})" d="M{{ $from['x'] + $mapNodeWidth }} {{ $from['y'] + ($mapNodeHeight / 2) }} C{{ $from['x'] + $mapNodeWidth + 24 }} {{ $from['y'] + ($mapNodeHeight / 2) }}, {{ $to['x'] - 24 }} {{ $to['y'] + ($mapNodeHeight / 2) }}, {{ $to['x'] }} {{ $to['y'] + ($mapNodeHeight / 2) }}"></path>
                @endif
            @endforeach
            @foreach ($mapColumns as $column)
                @foreach ($column['nodes'] as $node)
                    @php $position = $mapPositions[$node['id']]; @endphp
                    <g class="ekur-relationship-node" data-map-node data-node-id="{{ $node['id'] }}" transform="translate({{ $position['x'] }} {{ $position['y'] }})">
                        <title>{{ $node['code'] }} — {{ $node['name'] }}</title>
                        <rect x="0" y="0" width="{{ $mapNodeWidth }}" height="{{ $mapNodeHeight }}" rx="7"></rect>
                        <text class="ekur-relationship-code" x="8" y="16">{{ \Illuminate\Support\Str::limit($node['code'], 20) }}</text>
                        <text class="ekur-relationship-name" x="8" y="31">{{ \Illuminate\Support\Str::limit($node['name'], 24) }}</text>
                    </g>
                @endforeach
            @endforeach
        </svg>
    </div>
</div>