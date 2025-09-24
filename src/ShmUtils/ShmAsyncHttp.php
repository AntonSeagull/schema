<?php

namespace Shm\ShmUtils;

/*$client = new AsyncHttp();

// 1) JSON: заголовок проставится автоматически
$client->setUrl('https://api.example.com/events')
       ->setMethod('POST')
       ->setJsonBody(['id' => 42, 'status' => 'started'])
       ->send();

// 2) form-urlencoded: заголовок проставится автоматически
$client->reset()
       ->setUrl('https://api.example.com/login')
       ->setMethod('POST')
       ->setFormParams(['user' => 'admin', 'pass' => '123'])
       ->send();

// 3) заранее указали Content-Type: json, затем setBody (массив → станет JSON)
$client->reset()
       ->setUrl('https://api.example.com/update')
       ->setMethod('PUT')
       ->addHeader('Content-Type', 'application/json')
       ->setBody(['name' => 'Alice'])
       ->send();

// 4) кастомный Content-Type и строковое тело — берём как есть
$client->reset()
       ->setUrl('https://api.example.com/raw')
       ->setMethod('PUT')
       ->addHeader('Content-Type', 'text/plain; charset=utf-8')
       ->setBody("raw text payload")
       ->send();*/

class ShmAsyncHttp
{
    private string $url = '';
    private string $method = 'GET';
    private array  $headers = [];
    private array  $query = [];
    private ?string $payload = null;   // уже подготовленное тело
    private ?string $explicitContentType = null;

    // ---- Конфигурация ----
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function setMethod(string $method): self
    {
        $this->method = strtoupper($method);
        return $this;
    }

    /** Полная замена заголовков ['Header' => 'Value'] */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        // запомним Content-Type если есть
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'content-type') {
                $this->explicitContentType = $v;
                break;
            }
        }
        return $this;
    }

    /** Добавить/перезаписать один заголовок */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        if (strtolower($name) === 'content-type') {
            $this->explicitContentType = $value;
        }
        return $this;
    }

    /** Задать query-параметры ?a=1&b=2 */
    public function setQueryParams(array $params): self
    {
        $this->query = $params;
        return $this;
    }

    /**
     * Универсальная установка тела:
     * - если строка — берём как есть (Content-Type не трогаем);
     * - если не строка и Content-Type ранее не задан — по умолчанию x-www-form-urlencoded
     */
    public function setBody($body): self
    {

        $this->method = 'POST'; // по умолчанию

        if (is_string($body)) {
            $this->payload = $body;
            // если Content-Type ещё не задан – оставим как есть, пользователь задаст сам при необходимости
            return $this;
        }

        // если Content-Type заранее выставлен в JSON — кодируем как JSON
        if ($this->isContentTypeJson()) {
            $this->payload = json_encode($body, JSON_UNESCAPED_UNICODE);
            $this->headers['Content-Type'] = 'application/json';
            $this->explicitContentType = 'application/json';
            return $this;
        }

        // иначе по умолчанию form-urlencoded
        $this->payload = http_build_query((array)$body);
        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $this->explicitContentType = 'application/x-www-form-urlencoded';
        return $this;
    }

    /** Явно: тело как JSON + автоматический заголовок */
    public function setJsonBody($data): self
    {
        $this->method = 'POST'; // по умолчанию

        if (is_string($data)) {
            // предполагаем, что это уже JSON
            $this->payload = $data;
        } else {
            $this->payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $this->headers['Content-Type'] = 'application/json';
        $this->explicitContentType = 'application/json';
        return $this;
    }

    /** Явно: тело как application/x-www-form-urlencoded + автоматический заголовок */
    public function setFormParams($data): self
    {

        $this->method = 'POST'; // по умолчанию
        if (is_string($data)) {
            // пользователь сам подготовил строку a=1&b=2
            $this->payload = $data;
        } else {
            $this->payload = http_build_query((array)$data);
        }
        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $this->explicitContentType = 'application/x-www-form-urlencoded';
        return $this;
    }

    public function send(): void
    {
        if ($this->url === '') {
            return;
        }

        // Стартовая команда
        $cmd = ['curl', '-s'];

        // Метод
        if ($this->method !== 'GET') {
            $cmd[] = '-X';
            $cmd[] = escapeshellarg($this->method);
        }

        // Заголовки
        foreach ($this->headers as $k => $v) {
            $cmd[] = '-H';
            $cmd[] = escapeshellarg($k . ': ' . $v);
        }

        // Тело
        if ($this->payload !== null && $this->method !== 'GET') {
            $cmd[] = '--data';
            $cmd[] = escapeshellarg($this->payload);
        }

        // URL
        $cmd[] = escapeshellarg($this->url . (!empty($this->query) ? '?' . http_build_query($this->query) : ''));

        // Собираем строку
        $command = implode(' ', $cmd) . ' > /dev/null 2>&1 &';

        // Запускаем в фоне
        exec($command);
    }

    public function reset(): self
    {
        $this->url = '';
        $this->method = 'GET';
        $this->headers = [];
        $this->query = [];
        $this->payload = null;
        $this->explicitContentType = null;
        return $this;
    }

    private function isContentTypeJson(): bool
    {
        if ($this->explicitContentType === null) {
            return false;
        }
        return stripos($this->explicitContentType, 'application/json') === 0
            || stripos($this->explicitContentType, 'json') !== false;
    }
}
