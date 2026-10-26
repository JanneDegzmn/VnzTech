<?php

function vnzPaginationState(int $perPage = 12, string $parameter = 'list_page'): array
{
    $requestedPage = filter_var(
        $_GET[$parameter] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $page = $requestedPage === false ? 1 : $requestedPage;

    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'parameter' => $parameter
    ];
}

function vnzPaginationClamp(array $state, int $total): array
{
    $lastPage = max(1, (int) ceil($total / $state['per_page']));
    $state['page'] = min($state['page'], $lastPage);
    $state['offset'] = ($state['page'] - 1) * $state['per_page'];
    $state['last_page'] = $lastPage;
    $state['total'] = $total;

    return $state;
}

function vnzRenderPagination(
    array $state,
    array $query,
    string $targetPage,
    string $context,
    string $baseUrl
): string {
    $total = (int) $state['total'];
    $page = (int) $state['page'];
    $perPage = (int) $state['per_page'];
    $lastPage = (int) $state['last_page'];
    $start = $total === 0 ? 0 : $state['offset'] + 1;
    $end = min($total, $state['offset'] + $perPage);
    $query[$state['parameter']] = $page;

    $link = static function (int $target) use (
        $query,
        $state,
        $targetPage,
        $context,
        $baseUrl
    ): string {
        if ($context === 'admin') {
            $query['page'] = $targetPage;
        } elseif ($context === 'seller' && !isset($query['page'])) {
            parse_str((string) parse_url($baseUrl, PHP_URL_QUERY), $baseQuery);
            if (!isset($baseQuery['page'])) {
                $query['page'] = $targetPage;
            }
        }
        $query[$state['parameter']] = $target;
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        $href = $baseUrl . $separator . http_build_query($query);
        $attributes = $context === 'admin'
            ? ' data-admin-pagination data-admin-page="' . htmlspecialchars($targetPage, ENT_QUOTES, 'UTF-8') . '"'
            : ' data-seller-page="' . htmlspecialchars($targetPage, ENT_QUOTES, 'UTF-8') . '"';

        return '<a class="vnz-pagination-link" href="'
            . htmlspecialchars($href, ENT_QUOTES, 'UTF-8')
            . '"' . $attributes . '>';
    };

    $html = '<nav class="vnz-pagination" aria-label="List pages">';
    $html .= '<span class="vnz-pagination-count">Showing '
        . number_format($start) . '–' . number_format($end)
        . ' of ' . number_format($total) . '</span>';
    $html .= '<div class="vnz-pagination-controls">';
    $controls = [];

    if ($page > 1) {
        $controls[] = $link($page - 1) . '‹ Previous</a>';
    } else {
        $controls[] = '<span class="vnz-pagination-disabled" aria-disabled="true">‹ Previous</span>';
    }

    $first = max(1, $page - 2);
    $last = min($lastPage, $page + 2);
    if ($first > 1) {
        $controls[] = $link(1) . '1</a>';
        if ($first > 2) {
            $controls[] = '<span class="vnz-pagination-ellipsis" aria-hidden="true">…</span>';
        }
    }

    for ($number = $first; $number <= $last; $number++) {
        if ($number === $page) {
            $controls[] = '<span class="vnz-pagination-current" aria-current="page">'
                . $number . '</span>';
        } else {
            $controls[] = $link($number) . $number . '</a>';
        }
    }

    if ($last < $lastPage) {
        if ($last < $lastPage - 1) {
            $controls[] = '<span class="vnz-pagination-ellipsis" aria-hidden="true">…</span>';
        }
        $controls[] = $link($lastPage) . $lastPage . '</a>';
    }

    if ($page < $lastPage) {
        $controls[] = $link($page + 1) . 'Next ›</a>';
    } else {
        $controls[] = '<span class="vnz-pagination-disabled" aria-disabled="true">Next ›</span>';
    }

    return $html . implode(' ', $controls) . '</div></nav>';
}
