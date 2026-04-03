<?php

namespace SwiftPHP\Paginate;

class Paginate
{
    protected $total = 0;
    protected $perPage = 15;
    protected $currentPage = 1;
    protected $lastPage = 1;
    protected $from = 1;
    protected $to = 1;
    protected $data = [];
    protected $queryParams = [];

    public function __construct(int $total = 0, int $perPage = 15, int $currentPage = 1)
    {
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->calculate();
    }

    public static function make($total, int $perPage = 15, int $currentPage = 1): self
    {
        return new self($total, $perPage, $currentPage);
    }

    protected function calculate(): void
    {
        $this->lastPage = max(1, (int)ceil($this->total / $this->perPage));
        $this->currentPage = min($this->currentPage, $this->lastPage);
        $this->currentPage = max(1, $this->currentPage);

        $this->from = (($this->currentPage - 1) * $this->perPage) + 1;
        $this->to = min($this->currentPage * $this->perPage, $this->total);
    }

    public function items($items): self
    {
        $this->data = $items;
        return $this;
    }

    public function setQueryParams(array $params): self
    {
        $this->queryParams = $params;
        return $this;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function from(): int
    {
        return $this->from;
    }

    public function to(): int
    {
        return $this->to;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function hasPages(): bool
    {
        return $this->lastPage > 1;
    }

    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function isFirst(): bool
    {
        return $this->currentPage <= 1;
    }

    public function isLast(): bool
    {
        return $this->currentPage >= $this->lastPage;
    }

    public function offset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function limit(): int
    {
        return $this->perPage;
    }

    public function previous(int $step = 1): int
    {
        return max(1, $this->currentPage - $step);
    }

    public function next(int $step = 1): int
    {
        return min($this->lastPage, $this->currentPage + $step);
    }

    public function links(int $shown = 5): array
    {
        $links = [];
        $half = (int)floor($shown / 2);

        $start = max(1, $this->currentPage - $half);
        $end = min($this->lastPage, $start + $shown - 1);

        if ($end - $start + 1 < $shown) {
            $start = max(1, $end - $shown + 1);
        }

        if ($start > 1) {
            $links[] = ['page' => 1, 'url' => $this->buildUrl(1), 'is_current' => false];
            if ($start > 2) {
                $links[] = ['page' => '...', 'url' => null, 'is_current' => false];
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $links[] = ['page' => $i, 'url' => $this->buildUrl($i), 'is_current' => ($i === $this->currentPage)];
        }

        if ($end < $this->lastPage) {
            if ($end < $this->lastPage - 1) {
                $links[] = ['page' => '...', 'url' => null, 'is_current' => false];
            }
            $links[] = ['page' => $this->lastPage, 'url' => $this->buildUrl($this->lastPage), 'is_current' => false];
        }

        return $links;
    }

    protected function buildUrl(int $page): string
    {
        $params = $this->queryParams;
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'from' => $this->from,
            'to' => $this->to,
            'data' => $this->data,
            'links' => $this->links(),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}
