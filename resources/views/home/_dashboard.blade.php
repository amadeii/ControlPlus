@php
    $summary = $dashboard['summary'] ?? [
        'total_vendas' => 0,
        'total_pedidos' => 0,
        'total_clientes' => 0,
        'total_produtos' => 0,
    ];
    $recentSales = $dashboard['recentSales'] ?? collect();
    $recentOrders = $dashboard['recentOrders'] ?? collect();
    $lowStockProducts = $dashboard['lowStockProducts'] ?? collect();

    $summaryCards = [
        [
            'label' => 'Total de vendas',
            'value' => 'R$ ' . __moeda($summary['total_vendas'] ?? 0),
            'icon' => 'ri-shopping-cart-fill',
            'class' => 'text-bg-primary',
        ],
        [
            'label' => 'Total de pedidos',
            'value' => number_format($summary['total_pedidos'] ?? 0, 0, ',', '.'),
            'icon' => 'ri-file-list-3-line',
            'class' => 'text-bg-success',
        ],
        [
            'label' => 'Total de clientes',
            'value' => number_format($summary['total_clientes'] ?? 0, 0, ',', '.'),
            'icon' => 'ri-account-box-fill',
            'class' => 'text-bg-dark',
        ],
        [
            'label' => 'Total de produtos',
            'value' => number_format($summary['total_produtos'] ?? 0, 0, ',', '.'),
            'icon' => 'ri-box-3-line',
            'class' => 'text-bg-info',
        ],
    ];

    $quickLinks = [
        ['label' => 'Produtos', 'route' => 'produtos.index', 'icon' => 'ri-price-tag-3-line'],
        ['label' => 'Pedidos', 'route' => 'pedidos-cardapio.index', 'icon' => 'ri-file-list-3-line'],
        ['label' => 'Clientes', 'route' => 'clientes.index', 'icon' => 'ri-user-3-line'],
        ['label' => 'Relatorios', 'route' => 'relatorios.index', 'icon' => 'ri-dashboard-3-line'],
    ];
@endphp

<div class="dashboard-painel mt-3">
    <div class="row g-3">
        @foreach ($summaryCards as $card)
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card widget-icon-box {{ $card['class'] }} h-100">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="text-uppercase">{{ $card['label'] }}</h4>
                            <h3>{{ $card['value'] }}</h3>
                        </div>
                        <div class="avatar-sm">
                            <span class="avatar-title shadow">
                                <i class="{{ $card['icon'] }}"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <div class="card card-elev h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Vendas recentes</h5>
                    @if ($recentSales->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Venda</th>
                                        <th>Cliente</th>
                                        <th>Status</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentSales as $sale)
                                        <tr>
                                            <td>
                                                <a href="{{ $sale['url'] }}" class="fw-semibold">
                                                    {{ $sale['tipo'] }} #{{ $sale['numero'] }}
                                                </a>
                                                <div class="small text-muted">{{ __data_pt($sale['created_at']) }}</div>
                                            </td>
                                            <td>{{ $sale['cliente'] }}</td>
                                            <td><span class="badge bg-light text-dark">{{ $sale['estado'] }}</span></td>
                                            <td class="text-end fw-semibold">R$ {{ __moeda($sale['total']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted py-4 text-center">Nenhum registro encontrado</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card card-elev h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Pedidos recentes</h5>
                    @if ($recentOrders->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Pedido</th>
                                        <th>Cliente</th>
                                        <th>Status</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentOrders as $order)
                                        <tr>
                                            <td>
                                                <a href="{{ $order['url'] }}" class="fw-semibold">
                                                    {{ $order['origem'] }} #{{ $order['numero'] }}
                                                </a>
                                                <div class="small text-muted">{{ __data_pt($order['created_at']) }}</div>
                                            </td>
                                            <td>{{ $order['cliente'] }}</td>
                                            <td><span class="badge bg-light text-dark">{{ $order['estado'] }}</span></td>
                                            <td class="text-end fw-semibold">R$ {{ __moeda($order['total']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted py-4 text-center">Nenhum registro encontrado</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-8">
            <div class="card card-elev h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Produtos com estoque baixo</h5>
                    @if ($lowStockProducts->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th class="text-end">Atual</th>
                                        <th class="text-end">Minimo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lowStockProducts as $product)
                                        <tr>
                                            <td>
                                                <a href="{{ route('produtos.edit', [$product->id]) }}" class="fw-semibold">
                                                    {{ $product->nome }}
                                                </a>
                                            </td>
                                            <td class="text-end">{{ __moeda($product->quantidade) }} {{ $product->unidade }}</td>
                                            <td class="text-end">{{ __moeda($product->estoque_minimo ?? 0) }} {{ $product->unidade }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted py-4 text-center">Nenhum registro encontrado</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card card-elev h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Atalhos rapidos</h5>
                    <div class="row g-2">
                        @foreach ($quickLinks as $link)
                            <div class="col-6">
                                <a href="{{ route($link['route']) }}" class="btn btn-light w-100 h-100 py-3 d-flex flex-column align-items-center justify-content-center gap-1">
                                    <i class="{{ $link['icon'] }} fs-3"></i>
                                    <span class="fw-semibold">{{ $link['label'] }}</span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
