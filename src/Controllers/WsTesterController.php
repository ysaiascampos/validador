<?php

namespace Vendor\Validator\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class WsTesterController extends Controller
{
    /**
     * Show the non-technical web service workbench.
     *
     * @return mixed
     */
    public function index()
    {
        if (!$this->enabled()) {
            return new JsonResponse(array('error' => 'WS tester is disabled.'), 404);
        }

        return view('validator::workbench');
    }

    /**
     * Execute a request on behalf of the workbench.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function test(Request $request = null)
    {
        if (!$this->enabled()) {
            return $this->error('WS tester is disabled.', 404);
        }

        $request = $request ?: app('request');
        $url = trim((string) $request->input('url', ''));
        $method = strtoupper(trim((string) $request->input('method', 'GET')));

        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?$/i', (string) parse_url($url, PHP_URL_SCHEME))) {
            return $this->error('Ingresa una URL http o https válida.', 422);
        }

        if (!$this->hostAllowed((string) parse_url($url, PHP_URL_HOST))) {
            return $this->error('El destino no está autorizado para este probador.', 422);
        }

        if (!in_array($method, array('GET', 'POST', 'PUT', 'PATCH', 'DELETE'), true)) {
            return $this->error('Método HTTP no soportado.', 422);
        }

        $headers = $this->headers($request->input('headers', array()));
        $body = (string) $request->input('body', '');

        if ($body !== '' && !$this->validJson($body)) {
            return $this->error('El cuerpo debe contener JSON válido.', 422);
        }

        $started = microtime(true);
        $response = $this->send($url, $method, $headers, $body);
        $response['duration_ms'] = round((microtime(true) - $started) * 1000, 2);

        return new JsonResponse($response, 200);
    }

    private function send($url, $method, array $headers, $body)
    {
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_TIMEOUT, (int) app('config')->get('validator.ws_tester.timeout', 30));
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);

        if ($body !== '' && $method !== 'GET') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        if ($raw === false) {
            return array('success' => false, 'error' => $error ?: 'No fue posible conectar con el servicio.');
        }

        $responseBody = substr($raw, $headerSize);
        $decoded = json_decode($responseBody, true);

        return array(
            'success'       => $status >= 200 && $status < 400,
            'status_code'   => $status,
            'response_body' => $responseBody,
            'response_json' => json_last_error() === JSON_ERROR_NONE ? $decoded : null,
        );
    }

    private function headers($input)
    {
        if (is_string($input)) {
            $input = json_decode($input, true);
        }

        $headers = array('Accept: application/json');
        if (!is_array($input)) {
            return $headers;
        }

        foreach ($input as $name => $value) {
            if (is_string($name) && preg_match('/^[A-Za-z0-9-]+$/', $name)) {
                $headers[] = $name . ': ' . (string) $value;
            }
        }

        return $headers;
    }

    private function validJson($body)
    {
        json_decode($body, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function hostAllowed($host)
    {
        $allowed = app('config')->get('validator.ws_tester.allowed_hosts', array());
        foreach ((array) $allowed as $pattern) {
            $pattern = ltrim(strtolower(trim($pattern)), '.');
            if ($pattern !== '' && (strtolower($host) === $pattern || substr(strtolower($host), -strlen('.' . $pattern)) === '.' . $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function enabled()
    {
        return (bool) app('config')->get('validator.ws_tester.enabled', false);
    }

    private function error($message, $status)
    {
        return new JsonResponse(array('success' => false, 'error' => $message), $status);
    }
}
