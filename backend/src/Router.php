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
        private readonly Auth $auth,
    ) {
    }

    public function dispatch(string $page): void
    {
        try {
            if ($this->auth->misconfigured()) {
                $this->renderError(
                    503,
                    'Dashboard belum dikonfigurasi: isi DASHBOARD_PASSWORD_HASH '
                    . 'atau DASHBOARD_PASSWORD, atau set DASHBOARD_ACCESS_MODE=open.',
                    'DASHBOARD_NOT_CONFIGURED',
                );

                return;
            }

            if ($page === 'login') {
                $this->login();

                return;
            }

            if ($page === 'logout') {
                $this->logout();

                return;
            }

            if ($this->auth->requiresLogin() && !$this->auth->isAuthenticated()) {
                $this->login();

                return;
            }

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

    private function login(): void
    {
        if (!$this->auth->requiresLogin()) {
            $this->redirect('?page=dashboard');

            return;
        }

        if ($this->auth->isAuthenticated()) {
            $this->redirect('?page=dashboard');

            return;
        }

        $error = null;
        $username = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if (!csrf_valid()) {
                http_response_code(419);
                $error = 'Sesi form sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.';
            } elseif ($this->auth->attempt($username, $password)) {
                $this->auth->login($username);
                $this->redirect('?page=dashboard');

                return;
            } else {
                // Memperlambat brute force tanpa menyimpan state di server.
                usleep(300000);
                http_response_code(401);
                $error = 'Username atau password salah.';
            }
        }

        echo $this->view->renderRaw('login', [
            'title' => 'Masuk',
            'error' => $error,
            'username' => $username,
        ]);
    }

    private function logout(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('?page=dashboard');

            return;
        }

        if (!csrf_valid()) {
            $this->renderError(
                419,
                'Sesi form sudah kedaluwarsa. Muat ulang halaman lalu kirim ulang.',
                'CSRF_EXPIRED',
            );

            return;
        }

        $this->auth->logout();
        $this->redirect('?page=login');
    }

    private function redirect(string $location): void
    {
        if (!headers_sent()) {
            header('Location: ' . $location, true, 303);
        }
    }

    private function dispatchPost(string $page): void
    {
        $action = (string) ($_POST['action'] ?? '');

        if (!csrf_valid()) {
            $this->renderError(
                419,
                'Sesi form sudah kedaluwarsa. Muat ulang halaman lalu kirim ulang.',
                'CSRF_EXPIRED',
            );

            return;
        }

        match (true) {
            $page === 'orders' && $action === 'parse' => $this->parseOrder(),
            $page === 'orders' && $action === 'save' => $this->saveOrder(),
            $page === 'orders' && $action === 'check' => $this->checkStock(),
            $page === 'orders' && $action === 'confirm' => $this->confirmOrder(),
            $page === 'orders' && $action === 'qc' => $this->recordQc(),
            $page === 'orders' && $action === 'allocate' => $this->allocateOrder(),
            $page === 'stocks' && $action === 'restock' => $this->restockIngredient(),
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
        $orderId = $this->intValue($_GET['order_id'] ?? null);
        $ordersList = null;
        $orderDetail = null;
        try {
            $ordersList = $this->api->get('/v1/orders', ['limit' => 20]);
            if ($orderId !== null) {
                $orderDetail = $this->api->get('/v1/orders/' . $orderId);
            }
        } catch (ApiException | RuntimeException) {
            // best-effort; actions will show specific errors
        }

        $this->renderOrders([
            'orderId' => $orderId,
            'focus' => $this->focusValue($_GET['focus'] ?? null),
            'ordersList' => $ordersList,
            'orderDetail' => $orderDetail,
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
            'restockResult' => null,
            'restockError' => null,
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
            'saveResult' => null,
            'saveError' => null,
            'checkResult' => null,
            'checkError' => null,
            'confirmResult' => null,
            'confirmError' => null,
            'qcResult' => null,
            'qcError' => null,
            'allocateResult' => null,
            'allocateError' => null,
            'ordersList' => null,
            'orderDetail' => null,
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

        if (!array_key_exists('ordersList', $data) || !array_key_exists('orderDetail', $data)) {
            $oid = $data['orderId'] ?? $defaults['orderId'];
            if ($oid === null) {
                $oid = $this->intValue($_POST['order_id'] ?? null);
            }
            try {
                if (!array_key_exists('ordersList', $data)) {
                    $defaults['ordersList'] = $this->api->get('/v1/orders', ['limit' => 20]);
                }
                if (!array_key_exists('orderDetail', $data) && $oid !== null) {
                    $defaults['orderDetail'] = $this->api->get('/v1/orders/' . (int) $oid);
                }
            } catch (ApiException | RuntimeException) {
            }
        }

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

    private function saveOrder(): void
    {
        $form = $this->formFromPost();
        $itemsJson = trim((string) ($_POST['items_json'] ?? '[]'));
        $items = json_decode($itemsJson, true);
        if (!is_array($items) || $items === []) {
            $this->renderOrders(['form' => $form, 'saveError' => ['status'=>0,'code'=>'FORM_INVALID','message'=>'Tidak ada item parse untuk disimpan. Urai pesanan terlebih dahulu.','details'=>[]], 'focus'=>'save']);
            return;
        }
        $payload = [
            'raw_text' => $form['raw_text'],
            'channel' => $form['channel'],
            'customer_name' => $form['customer_name'],
            'items' => array_values($items),
        ];
        if ($form['order_date'] !== '') { $payload['order_date'] = $form['order_date']; }
        if ($form['notes'] !== '') { $payload['notes'] = $form['notes']; }
        try {
            $result = $this->api->post('/v1/orders', $payload);
            $oid = (int) ($result['order_id'] ?? 0);
            $detail = $oid > 0 ? $this->api->get('/v1/orders/' . $oid) : null;
            $this->renderOrders(['form' => $form, 'saveResult' => $result, 'orderId' => $oid ?: null, 'orderDetail' => $detail, 'focus' => 'check']);
        } catch (ApiException | RuntimeException $exception) {
            $this->renderOrders(['form' => $form, 'saveError' => $this->toError($exception), 'focus' => 'save']);
        }
    }

    private function confirmOrder(): void
    {
        $orderId = $this->intValue($_POST['order_id'] ?? null);
        if ($orderId === null) {
            $this->renderOrders(['confirmError' => ['status'=>0,'code'=>'FORM_INVALID','message'=>'Order ID harus berupa angka bulat positif.','details'=>[]], 'focus'=>'confirm']);
            return;
        }
        try {
            $result = $this->api->post("/v1/orders/{$orderId}/confirm");
            $detail = $this->api->get("/v1/orders/{$orderId}");
            $this->renderOrders(['orderId'=>$orderId,'confirmResult'=>$result,'orderDetail'=>$detail,'focus'=>'confirm']);
        } catch (ApiException | RuntimeException $exception) {
            $this->renderOrders(['orderId'=>$orderId,'confirmError'=>$this->toError($exception),'focus'=>'confirm']);
        }
    }

    private function recordQc(): void
    {
        $orderId = $this->intValue($_POST['order_id'] ?? null);
        $itemId = $this->intValue($_POST['item_id'] ?? null);
        $checkedBy = trim((string) ($_POST['checked_by'] ?? 'admin'));
        if ($orderId === null || $itemId === null) {
            $this->renderOrders(['orderId'=>$orderId,'qcError'=>['status'=>0,'code'=>'FORM_INVALID','message'=>'Order ID dan Item ID wajib diisi.','details'=>[]],'focus'=>'qc']);
            return;
        }
        try {
            $result = $this->api->post("/v1/orders/{$orderId}/items/{$itemId}/qc", ['checked_by' => $checkedBy !== '' ? $checkedBy : 'admin', 'result' => 'pass']);
            $detail = $this->api->get("/v1/orders/{$orderId}");
            $this->renderOrders(['orderId'=>$orderId,'qcResult'=>$result,'orderDetail'=>$detail,'focus'=>'qc']);
        } catch (ApiException | RuntimeException $exception) {
            $this->renderOrders(['orderId'=>$orderId,'qcError'=>$this->toError($exception),'focus'=>'qc']);
        }
    }

    private function restockIngredient(): void
    {
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $qty = (float) ($_POST['quantity'] ?? 0);
        $category = trim((string) ($_POST['category'] ?? ''));
        $lowOnly = (string) ($_POST['low_only'] ?? '0') === '1';
        $detailSku = trim((string) ($_POST['detail_sku'] ?? $sku));
        $base = ['title'=>'Stok Bahan','lowOnly'=>$lowOnly,'category'=>$category,'sku'=>$detailSku];
        if ($sku === '' || $qty <= 0) {
            try { $base['data'] = $this->api->get('/v1/stocks', array_filter(['category'=>$category?:null,'low_only'=>$lowOnly?'true':null])); } catch (ApiException | RuntimeException $e) { $base['data']=['items'=>[],'source'=>'-','generated_at'=>'']; }
            $base['restockError'] = ['status'=>0,'code'=>'FORM_INVALID','message'=>'SKU dan jumlah restock (> 0) wajib diisi.','details'=>[]];
            echo $this->view->render('stocks', array_merge(['detail'=>null,'detailError'=>null,'restockResult'=>null], $base));
            return;
        }
        try {
            $restockResult = $this->api->post('/v1/stocks/' . rawurlencode($sku) . '/restock', ['quantity' => $qty]);
            $data = $this->api->get('/v1/stocks', array_filter(['category'=>$category?:null,'low_only'=>$lowOnly?'true':null]));
            $detail = null; $detailError = null;
            if ($detailSku !== '') {
                try { $detail = $this->api->get('/v1/stocks/' . rawurlencode($detailSku)); } catch (ApiException | RuntimeException $e) { $detailError = $this->toError($e); }
            }
            echo $this->view->render('stocks', ['title'=>'Stok Bahan','data'=>$data,'lowOnly'=>$lowOnly,'category'=>$category,'sku'=>$detailSku,'detail'=>$detail,'detailError'=>$detailError,'restockResult'=>$restockResult,'restockError'=>null]);
        } catch (ApiException | RuntimeException $exception) {
            try { $base['data'] = $this->api->get('/v1/stocks', array_filter(['category'=>$category?:null,'low_only'=>$lowOnly?'true':null])); } catch (ApiException | RuntimeException $e) { $base['data']=['items'=>[],'source'=>'-','generated_at'=>'']; }
            echo $this->view->render('stocks', array_merge(['detail'=>null,'detailError'=>null,'restockResult'=>null,'restockError'=>$this->toError($exception)], $base));
        }
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
        return is_string($value) && in_array($value, ['check', 'allocate', 'confirm', 'qc', 'save'], true) ? $value : null;
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
