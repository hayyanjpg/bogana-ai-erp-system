<?php

declare(strict_types=1);

namespace Bogana;

use DateTimeImmutable;
use Throwable;

final class Router
{
    public function __construct(
        private readonly ApiClient $api,
        private readonly View $view,
    ) {
    }

    public function dispatch(string $page): void
    {
        try {
            switch ($page) {
                case 'dashboard':
                    $this->dashboard();
                    break;
                case 'stocks':
                    $this->stocks();
                    break;
                case 'forecasts':
                    $this->forecasts();
                    break;
                case 'health':
                    $this->health();
                    break;
                default:
                    $this->renderError(404, 'Halaman tidak ditemukan');
            }
        } catch (ApiException $exception) {
            $this->renderError($exception->statusCode(), $exception->getMessage());
        } catch (Throwable $exception) {
            $this->renderError(500, $exception->getMessage());
        }
    }

    private function dashboard(): void
    {
        $date = $this->dateParam('date') ?? date('Y-m-d');
        $data = $this->api->get('/v1/dashboard/summary', ['date' => $date]);

        echo $this->view->render('dashboard', [
            'title' => 'Dashboard',
            'data' => $data,
        ]);
    }

    private function stocks(): void
    {
        $query = [];
        if (isset($_GET['low_only'])) {
            $lowOnly = filter_var($_GET['low_only'], FILTER_VALIDATE_BOOLEAN);
            $query['low_only'] = $lowOnly ? 'true' : 'false';
        }

        $category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
        if ($category !== '') {
            $query['category'] = $category;
        }

        $data = $this->api->get('/v1/stocks', $query);

        echo $this->view->render('stocks', [
            'title' => 'Stok Bahan',
            'data' => $data,
            'lowOnly' => ($query['low_only'] ?? 'false') === 'true',
            'category' => $category,
        ]);
    }

    private function forecasts(): void
    {
        $query = [];
        if (isset($_GET['days']) && ctype_digit((string) $_GET['days'])) {
            $query['days'] = (string) $_GET['days'];
        }
        if (isset($_GET['menu_id']) && ctype_digit((string) $_GET['menu_id'])) {
            $query['menu_id'] = (string) $_GET['menu_id'];
        }

        $fromDate = $this->dateParam('from_date');
        if ($fromDate !== null) {
            $query['from_date'] = $fromDate;
        }

        $data = $this->api->get('/v1/forecasts', $query);

        echo $this->view->render('forecasts', [
            'title' => 'Forecast',
            'data' => $data,
        ]);
    }

    private function health(): void
    {
        $data = $this->api->get('/health');

        echo $this->view->render('health', [
            'title' => 'Status Sistem',
            'data' => $data,
        ]);
    }

    private function dateParam(string $key): ?string
    {
        $value = $_GET[$key] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private function renderError(int $status, string $message): void
    {
        http_response_code($status >= 400 && $status < 600 ? $status : 500);

        echo $this->view->render('error', [
            'title' => 'Terjadi Kesalahan',
            'status' => $status,
            'message' => $message,
        ]);
    }
}