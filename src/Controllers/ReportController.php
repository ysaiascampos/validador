<?php

namespace Vendor\Validator\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Vendor\Validator\Services\ReportPresenter;
use Vendor\Validator\Services\ValidatorService;

class ReportController extends Controller
{
    /**
     * @var ValidatorService
     */
    private $validatorService;

    /**
     * @var ReportPresenter
     */
    private $presenter;

    /**
     * Constructor.
     *
     * @param ValidatorService $validatorService
     * @param ReportPresenter $presenter
     */
    public function __construct(ValidatorService $validatorService, ReportPresenter $presenter)
    {
        $this->validatorService = $validatorService;
        $this->presenter = $presenter;
    }

    /**
     * Show the human friendly validation report.
     *
     * @param Request|null $request
     * @return Response
     */
    public function show(Request $request = null)
    {
        if (!$this->enabled()) {
            return new Response('El reporte del validador está deshabilitado.', 404);
        }

        $request = $request ?: app('request');
        $options = $this->options($request);

        return $this->render($this->run($options), $options, false, null);
    }

    /**
     * Download the validation report as PDF when a PDF engine is available.
     *
     * If no engine is installed, the printable HTML report is returned with a
     * notice, so the browser's "Print / Save as PDF" option can still be used.
     *
     * @param Request|null $request
     * @return Response
     */
    public function pdf(Request $request = null)
    {
        if (!$this->enabled()) {
            return new Response('El reporte del validador está deshabilitado.', 404);
        }

        $request = $request ?: app('request');
        $options = $this->options($request);
        $report = $this->run($options);
        $html = $this->renderHtml($report, $options, true, null);
        $filename = 'validacion-' . date('Ymd-His') . '.pdf';

        $pdf = $this->buildPdf($html, $filename);

        if ($pdf !== null) {
            return $pdf;
        }

        return $this->render(
            $report,
            $options,
            true,
            'No hay un motor PDF instalado. Se muestra la versión imprimible: usa "Imprimir / Guardar PDF" del navegador o instala dompdf/dompdf.'
        );
    }

    /**
     * Run the validators and present the result.
     *
     * @param array $options
     * @return array
     */
    private function run(array $options)
    {
        $result = $this->validatorService->validate($options)->toArray();

        return $this->presenter->present($result, array(
            'title' => $this->title(),
            'generated_at' => date('Y-m-d H:i:s'),
            'environment' => $this->environment(),
        ));
    }

    /**
     * Build the render response.
     *
     * @param array $report
     * @param array $options
     * @param bool $pdfMode
     * @param string|null $message
     * @return Response
     */
    private function render(array $report, array $options, $pdfMode, $message)
    {
        return new Response(
            $this->renderHtml($report, $options, $pdfMode, $message),
            200,
            array('Content-Type' => 'text/html; charset=UTF-8')
        );
    }

    /**
     * Render the report view to HTML.
     *
     * @param array $report
     * @param array $options
     * @param bool $pdfMode
     * @param string|null $message
     * @return string
     */
    private function renderHtml(array $report, array $options, $pdfMode, $message)
    {
        return view('validator::report', array(
            'report' => $report,
            'options' => $this->normalizeOptions($options),
            'query' => $this->query($options),
            'pdf_available' => $this->pdfAvailable(),
            'pdf_mode' => $pdfMode,
            'pdf_message' => $message,
        ))->render();
    }

    /**
     * Build the PDF response using dompdf when available.
     *
     * @param string $html
     * @param string $filename
     * @return Response|null
     */
    private function buildPdf($html, $filename)
    {
        if (app()->bound('dompdf.wrapper')) {
            $pdf = app('dompdf.wrapper');

            if (method_exists($pdf, 'loadHTML')) {
                $pdf->loadHTML($html);
            }

            if (method_exists($pdf, 'setPaper')) {
                $pdf->setPaper('A4', 'portrait');
            }

            if (method_exists($pdf, 'download')) {
                return $pdf->download($filename);
            }

            if (method_exists($pdf, 'stream')) {
                return $pdf->stream($filename);
            }
        }

        if (class_exists('Dompdf\\Dompdf')) {
            $config = array('isRemoteEnabled' => false);

            if (class_exists('Dompdf\\Options')) {
                $dompdf = new \Dompdf\Dompdf(new \Dompdf\Options($config));
            } else {
                $dompdf = new \Dompdf\Dompdf($config);
            }

            $dompdf->loadHtml($html);

            if (method_exists($dompdf, 'setPaper')) {
                $dompdf->setPaper('A4', 'portrait');
            }

            $dompdf->render();

            return new Response($dompdf->output(), 200, array(
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ));
        }

        return null;
    }

    /**
     * Extract the enable flags from the request.
     *
     * @param mixed $request
     * @return array
     */
    private function options($request)
    {
        $options = array();

        if (!is_object($request) || !method_exists($request, 'input')) {
            return $options;
        }

        $enable = $request->input('enable', array());

        if (!is_array($enable)) {
            $enable = array();
        }

        foreach (array('database', 'env', 'git') as $name) {
            if (array_key_exists($name, $enable)) {
                $options[$name] = $this->toBool($enable[$name]);
            }
        }

        return $options;
    }

    /**
     * Normalize the enable flags (all enabled by default).
     *
     * @param array $options
     * @return array
     */
    private function normalizeOptions(array $options)
    {
        $normalized = array('database' => true, 'env' => true, 'git' => true);

        foreach ($normalized as $name => $default) {
            if (array_key_exists($name, $options)) {
                $normalized[$name] = (bool) $options[$name];
            }
        }

        return $normalized;
    }

    /**
     * Build the query string params used by the links in the view.
     *
     * @param array $options
     * @return array
     */
    private function query(array $options)
    {
        $query = array();

        foreach ($this->normalizeOptions($options) as $name => $enabled) {
            $query['enable'][$name] = $enabled ? 1 : 0;
        }

        return $query;
    }

    /**
     * Convert a request/query value to boolean.
     *
     * @param mixed $value
     * @return bool
     */
    private function toBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        $value = strtolower(trim((string) $value));

        return !in_array($value, array('', '0', 'false', 'no', 'off'), true);
    }

    /**
     * Whether a PDF engine is available.
     *
     * @return bool
     */
    private function pdfAvailable()
    {
        if (app()->bound('dompdf.wrapper')) {
            return true;
        }

        return class_exists('Dompdf\\Dompdf');
    }

    /**
     * Whether the report is enabled in config.
     *
     * @return bool
     */
    private function enabled()
    {
        return (bool) app('config')->get('validator.report.enabled', true);
    }

    /**
     * Report title.
     *
     * @return string
     */
    private function title()
    {
        $title = app('config')->get('validator.report.title', '');

        return is_string($title) && $title !== '' ? $title : 'Reporte de validación';
    }

    /**
     * Current application environment, when available.
     *
     * @return string|null
     */
    private function environment()
    {
        $app = app();

        if (is_object($app) && method_exists($app, 'environment')) {
            try {
                return $app->environment();
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
