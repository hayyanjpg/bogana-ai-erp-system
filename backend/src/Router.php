<?php

declare(strict_types=1);

namespace Bogana;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class Router
{
    private const CHANNELS = ['whatsapp', 'email', 'phone', 'manual'];

    public function __construct(
        private readonly ApiClient $api,
        private readonly View $view,
    ) {
    }

    public function dispatch(string $page): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                $this->dispatchPost($page);

                return;
            }

            match ($page) {
                'dashboard' => $this->dashboard(),
                'orders' => $this->orders(),
                'stocks' => $this->stocks(),
                'forecasts' => $this->forecasts(),
                'health' => $this->health(),
                default => $this->renderError(404, 'Halaman tidak ditemukan', 'NOT_FOUND'),
            };
        } catch (ApiException $exception) {
            $this->renderError(
                $exception->statusCode(),
                $exception->getMessage(),
                $exception->errorCode(),
                $exception->details(),
            );
        } catch (Throwable $exception) {
            $this->renderError(500, $exception->getMessage());
        }
    }

    private function dispatchPost(string $page): void
    {
        if (!csrf_valid()) {
            $this->renderError(
                419,
                'Sesi form sudah kedaluwarsa. Muat ulang halaman lalu kirim ulang.',
                'CSRF_EXPIRED',
            );

            return;
        }

        $action = (string) ($_POST['action'] ?? '');

        match (true) {
            $page === 'orders' && $action === 'parse' => $this->parseOrder(),
            $page === 'orders' && $action === 'check' => $this->checkStock(),
            $page === 'orders' && $action === 'allocate' => $this->allocateOrder(),
            default => $this->renderError(404, 'Aksi tidak ditemukan', 'NOT_FOUND'),
        };
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

    private function orders(): void
    {
        $this->renderOrders([
            'orderId' => $this->intValue($_GET['order_id'] ?? null),
            'focus' => $this->focusValue($_GET['focus'] ?? null),
            'form' => [
                'order_date' => date('Y-m-d'),
            ],
        ]);
    }

    private function parseOrder(): void
    {
        $form = $this->formFromPost();

        if (trim($form['raw_text']) === '') {
            $this->renderOrders([
                'form' => $form,
                'parseError' => [
                    'status' => 0,
                    'code' => 'FORM_INCOMPLETE',
                    'message' => 'Teks order wajib diisi sebelum diurai.',
                    'details' => [],
                ],
            ]);

            return;
        }

        if (trim($form['customer_name']) === '') {
            $this->renderOrders([
                'form' => $form,
                'parseError' => [
                    'status' => 0,
                    'code' => 'FORM_INCOMPLETE',
                    'message' => 'Nama customer wajib diisi.',
                    'details' => [],
                ],
            ]);

            return;
        }

        $payload = [
            'raw_text' => $form['raw_text'],
            'channel' => $form['channel'],
            'customer_name' => $form['customer_name'],
        ];
        if ($form['order_date'] !== '') {
            $payload['order_date'] = $form['order_date'];
        }
        if ($form['notes'] !== '') {
            $payload['notes'] = $form['notes'];
        }

        try {
            $result = $this->api->post('/v1/parse-order', $payload);
            $this->renderOrders([
                'form' => $form,
                'parseResult' => $result,
            ]);
        } catch (ApiException | RuntimeException $exception) {
            $this->renderOrders([
                'form' => $form,
                'parseError' => $this->toError($exception),
            ]);
        }
    }

    private function checkStock(): void
    {
        $orderId = $this->intValue($_POST['order_id'] ?? null);
        if ($orderId === null) {
            $this->renderOrders([
                'checkError' => [
                    'status' => 0,
                    'code' => 'FORM_INVALID',
                    'message' => 'Order ID harus berupa angka bulat positif.',
                    'details' => [],
                ],
                'focus' => 'check',
            ]);

            return;
        }

        try {
            $result = $this->api->post("/v1/orders/{$orderId}/check-stock");
            $this->renderOrders([
                'orderId' => $orderId,
                'checkResult' => $result,
                'focus' => 'check',
            ]);
        } catch (ApiException | RuntimeException $exception) {
            $this->renderOrders([
                'orderId' => $orderId,
                'checkError' => $this->toError($exception),
                'focus' => 'check',
            ]);
        }
    }

    private function allocateOrder(): void
    {
        $orderId = $this->intValue($_POST['order_id'] ?? null);
        if ($orderId === null) {
            $this->renderOrders([
                'allocateError' => [
                    'status' => 0,
                    'code' => 'FORM_INVALID',
                    'message' => 'Order ID harus berupa angka bulat positif.',
                    'details' => [],
                ],
                'focus' => 'allocate',
            ]);

            return;
        }

        $version = null;
        $rawVersion = trim((string) ($_POST['expected_version'] ?? ''));
        if ($rawVersion !== '') {
            $version = $this->intValue($rawVersion);
            if ($version === null) {
                $this->renderOrders([
                    'orderId' => $orderId,
                    'allocateError' => [
                        'status' => 0,
                        'code' => 'FORM_INVALID',
                        'message' => 'Expected version harus berupa angka bulat positif atau dikosongkan.',
                        'details' => [],
                    ],
                    'focus' => 'allocate',
                ]);

                return;
            }
        }

        try {
            $result = $this->api->post(
                "/v1/orders/{$orderId}/allocate",
                ['expected_version' => $version],
            );
            $this->renderOrders([
                'orderId' => $orderId,
                'allocateResult' => $result,
                'focus' => 'allocate',
            ]);
        } catch (ApiException | RuntimeException $exception) {
            $this->renderOrders([
                'orderId' => $orderId,
                'allocateError' => $this->toError($exception),
                'focus' => 'allocate',
            ]);
        }
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

        $detail = null;
        $detailError = null;
        $sku = isset($_GET['sku']) ? trim((string) $_GET['sku']) : '';
        if ($sku !== '' && preg_match('/^[A-Za-z0-9_-]{1,64}$/', $sku) === 1) {
            try {
                $detail = $this->api->get('/v1/stocks/' . rawurlencode($sku));
            } catch (ApiException | RuntimeException $exception) {
                $detailError = $this->toError($exception);
            }
        }

        echo $this->view->render('stocks', [
            'title' => 'Stok Bahan',
            'data' => $data,
            'lowOnly' => ($query['low_only'] ?? 'false') === 'true',
            'category' => $category,
            'sku' => $sku,
            'detail' => $detail,
            'detailError' => $detailError,
        ]);
    }

    private function forecasts(): void
    {
        $query = [];
        $days = null;
        if (isset($_GET['days'])) {
            $parsed = filter_var($_GET['days'], FILTER_VALIDATE_INT);
            if ($parsed !== false) {
                $days = max(1, min(30, $parsed));
                $query['days'] = (string) $days;
            }
        }

        $menuId = $this->intValue($_GET['menu_id'] ?? null);
        if ($menuId !== null) {
            $query['menu_id'] = (string) $menuId;
        }

        $fromDate = $this->dateParam('from_date');
        if ($fromDate !== null) {
            $query['from_date'] = $fromDate;
        }

        $data = $this->api->get('/v1/forecasts', $query);

        echo $this->view->render('forecasts', [
            'title' => 'Forecast',
            'data' => $data,
            'menuId' => $menuId,
        ]);
    }

    private function health(): void
    {
        $data = $this->api->get('/health');

        echo $this->view->render('health', [
            'title' => 'Status Sistem',
            'data' => $data,
            'apiBaseUrl' => $this->api->baseUrl(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderOrders(array $data = []): void
    {
        $defaults = [
            'title' => 'Order & Parser',
            'parseResult' => null,
            'parseError' => null,
            'checkResult' => null,
            'checkError' => null,
            'allocateResult' => null,
            'allocateError' => null,
            'orderId' => null,
            'focus' => null,
            'form' => [
                'raw_text' => '',
                'channel' => 'whatsapp',
                'customer_name' => '',
                'order_date' => '',
                'notes' => '',
            ],
        ];

        $form = is_array($data['form'] ?? null) ? $data['form'] : [];
        $defaults['form'] = array_merge($defaults['form'], $form);

        echo $this->view->render('orders', array_merge($defaults, $data));
    }

    /**
     * @return array{raw_text: string, channel: string, customer_name: string, order_date: string, notes: string}
     */
    private function formFromPost(): array
    {
        $channel = strtolower(trim((string) ($_POST['channel'] ?? 'manual')));
        if (!in_array($channel, self::CHANNELS, true)) {
            $channel = 'manual';
        }

        return [
            'raw_text' => trim((string) ($_POST['raw_text'] ?? '')),
            'channel' => $channel,
            'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
            'order_date' => $this->dateValue($_POST['order_date'] ?? null) ?? '',
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];
    }

    /**
     * @return array{status: int, code: string, message: string, details: list<array<string, mixed>>}
     */
    private function toError(Throwable $exception): array
    {
        if ($exception instanceof ApiException) {
            return [
                'status' => $exception->statusCode(),
                'code' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'details' => $exception->details(),
            ];
        }

        return [
            'status' => 503,
            'code' => 'SERVICE_UNAVAILABLE',
            'message' => $exception->getMessage(),
            'details' => [],
        ];
    }

    private function dateParam(string $key): ?string
    {
        return $this->dateValue($_GET[$key] ?? null);
    }

    private function dateValue(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private function intValue(mixed $value): ?int
    {
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && preg_match('/^\d+$/', trim($value)) === 1) {
            $int = (int) trim($value);
        } else {
            return null;
        }

        return $int >= 1 ? $int : null;
    }

    private function focusValue(mixed $value): ?string
    {
        return is_string($value) && in_array($value, ['check', 'allocate'], true) ? $value : null;
    }

    /**
     * @param list<array<string, mixed>> $details
     */
    private function renderError(int $status, string $message, string $code = 'ERROR', array $details = []): void
    {
        http_response_code($status >= 400 && $status < 600 ? $status : 500);

        echo $this->view->render('error', [
            'title' => 'Terjadi Kesalahan',
            'status' => $status,
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ]);
    }
}
