<?php

namespace Vendor\Validator\Services;

/**
 * Transform a consolidated ValidationResult array into a presentation-friendly
 * structure that a plain Blade view (and a PDF engine) can render safely.
 */
class ReportPresenter
{
    /**
     * Human readable validator names.
     *
     * @var array
     */
    private $validatorLabels = array(
        'database' => 'Base de datos',
        'env' => 'Variables de entorno',
        'git' => 'Estado de Git',
    );

    /**
     * Human readable detail keys.
     *
     * @var array
     */
    private $detailLabels = array(
        'connections' => 'Conexiones',
        'exception_context' => 'Contexto de la excepción',
        'env_status' => 'Estado de las variables',
        'env_values' => 'Valores mostrados',
        'env_sensitive_variables' => 'Variables protegidas (valor oculto)',
        'env_sensitive_skipped' => 'Variables protegidas omitidas',
        'branch' => 'Rama actual',
        'commit' => 'Commit actual',
        'schemas' => 'Esquemas',
        'tables' => 'Tablas',
        'columns' => 'Columnas',
        'column_types' => 'Tipos de columna',
        'column_lengths' => 'Largos de columna',
        'nullable' => 'Nulabilidad',
        'defaults' => 'Valores por defecto',
        'functions' => 'Funciones',
        'procedures' => 'Procedimientos',
        'sequences' => 'Secuencias',
        'views' => 'Vistas',
        'triggers' => 'Triggers',
        'packages' => 'Paquetes',
        'synonyms' => 'Sinónimos',
        'indexes' => 'Índices',
        'primary_keys' => 'Claves primarias',
        'foreign_keys' => 'Claves foráneas',
        'constraints' => 'Restricciones',
    );

    /**
     * Human readable validation statuses.
     *
     * @var array
     */
    private $statusLabels = array(
        'ok' => 'OK',
        'exists' => 'Existe',
        'missing' => 'Falta',
        'empty' => 'Vacía',
        'not_exists' => 'No existe',
        'not_loaded' => 'No cargada en el proceso',
        'stale' => 'Desactualizada',
        'mismatch' => 'No coincide',
    );

    /**
     * Status tone used by the view (success, warning, danger, muted).
     *
     * @var array
     */
    private $statusTones = array(
        'ok' => 'success',
        'exists' => 'success',
        'missing' => 'danger',
        'empty' => 'danger',
        'not_exists' => 'danger',
        'mismatch' => 'danger',
        'not_loaded' => 'warning',
        'stale' => 'warning',
    );

    /**
     * Build the report data.
     *
     * @param array $result Consolidated ValidationResult::toArray().
     * @param array $meta   Optional metadata (title, generated_at, environment).
     * @return array
     */
    public function present(array $result, array $meta = array())
    {
        $details = (isset($result['details']) && is_array($result['details']))
            ? $result['details']
            : array();

        $validators = array();

        foreach ($details as $name => $detail) {
            if (is_array($detail) && isset($detail['validator'])) {
                $validators[] = $this->presentValidator($name, $detail);
            }
        }

        $errors = $this->stringList(isset($result['errors']) ? $result['errors'] : array());
        $warnings = $this->stringList(isset($result['warnings']) ? $result['warnings'] : array());

        $success = !empty($result['success']);

        return array(
            'title' => isset($meta['title']) ? $meta['title'] : 'Reporte de validación',
            'generated_at' => isset($meta['generated_at']) ? $meta['generated_at'] : date('Y-m-d H:i:s'),
            'environment' => isset($meta['environment']) ? $meta['environment'] : null,
            'success' => $success,
            'status_label' => $success ? 'APROBADO' : 'CON ERRORES',
            'execution_time' => isset($result['execution_time'])
                ? round((float) $result['execution_time'], 2)
                : 0.0,
            'errors' => $errors,
            'warnings' => $warnings,
            'counts' => array(
                'validators' => count($validators),
                'errors' => count($errors),
                'warnings' => count($warnings),
            ),
            'validators' => $validators,
        );
    }

    /**
     * Present a single validator result.
     *
     * @param string $name
     * @param array $detail
     * @return array
     */
    private function presentValidator($name, array $detail)
    {
        $success = !empty($detail['success']);

        return array(
            'name' => $name,
            'label' => $this->labelFor($name),
            'success' => $success,
            'status_label' => $success ? 'OK' : 'Con errores',
            'tone' => $success ? 'success' : 'danger',
            'execution_time' => isset($detail['execution_time'])
                ? round((float) $detail['execution_time'], 2)
                : 0.0,
            'errors' => $this->stringList(isset($detail['errors']) ? $detail['errors'] : array()),
            'warnings' => $this->stringList(isset($detail['warnings']) ? $detail['warnings'] : array()),
            'sections' => $this->sectionsFor(
                $name,
                isset($detail['details']) && is_array($detail['details']) ? $detail['details'] : array()
            ),
        );
    }

    /**
     * Build the sections of a validator.
     *
     * @param string $name
     * @param array $details
     * @return array
     */
    private function sectionsFor($name, array $details)
    {
        if (empty($details)) {
            return array();
        }

        if ($name === 'env') {
            return $this->envSections($details);
        }

        if ($name === 'database') {
            return $this->databaseSections($details);
        }

        return $this->genericSections($details);
    }

    /**
     * Sections for the environment validator.
     *
     * @param array $details
     * @return array
     */
    private function envSections(array $details)
    {
        $sections = array();

        if (isset($details['env_status']) && is_array($details['env_status'])) {
            $rows = array();

            foreach ($details['env_status'] as $variable => $status) {
                $rows[] = $this->row($variable, $this->statusLabel($status), $this->statusTone($status));
            }

            $sections[] = $this->table('Estado de las variables', array('Variable', 'Estado'), $rows);
        }

        if (isset($details['env_values']) && is_array($details['env_values'])) {
            $rows = array();

            foreach ($details['env_values'] as $variable => $value) {
                $rows[] = $this->row($variable, $this->stringify($value), 'muted');
            }

            $sections[] = $this->table('Valores mostrados', array('Variable', 'Valor'), $rows);
        }

        if (isset($details['env_sensitive_variables']) && is_array($details['env_sensitive_variables'])) {
            $sections[] = $this->listSection(
                'Variables protegidas (valor oculto)',
                $details['env_sensitive_variables']
            );
        }

        if (isset($details['env_sensitive_skipped']) && is_numeric($details['env_sensitive_skipped'])) {
            $sections[] = $this->textSection(
                'Variables protegidas omitidas',
                (string) (int) $details['env_sensitive_skipped']
            );
        }

        return $sections;
    }

    /**
     * Sections for the database validator.
     *
     * @param array $details
     * @return array
     */
    private function databaseSections(array $details)
    {
        $sections = array();

        if (isset($details['connections']) && is_array($details['connections'])) {
            $sections[] = $this->listSection('Conexiones', $details['connections']);
        }

        foreach ($details as $type => $items) {
            if ($type === 'connections' || $type === 'exception_context') {
                continue;
            }

            if (!is_array($items)) {
                continue;
            }

            $rows = array();

            foreach ($items as $object => $status) {
                $rows[] = $this->row($object, $this->statusLabel($status), $this->statusTone($status));
            }

            $sections[] = $this->table($this->labelFor($type), array('Objeto', 'Resultado'), $rows);
        }

        if (isset($details['exception_context'])) {
            $sections[] = $this->textSection(
                'Contexto de la excepción',
                $this->json($details['exception_context'])
            );
        }

        return $sections;
    }

    /**
     * Sections for any other validator (git, custom validators).
     *
     * @param array $details
     * @return array
     */
    private function genericSections(array $details)
    {
        $rows = array();
        $texts = array();

        foreach ($details as $key => $value) {
            if (is_array($value)) {
                $texts[] = $this->textSection($this->labelFor($key), $this->json($value));
                continue;
            }

            $rows[] = $this->row($this->labelFor($key), $this->stringify($value), 'muted');
        }

        $sections = array();

        if (!empty($rows)) {
            $sections[] = $this->table('Detalle', array('Dato', 'Valor'), $rows);
        }

        return array_merge($sections, $texts);
    }

    /**
     * Build a table section.
     *
     * @param string $title
     * @param array $columns
     * @param array $rows
     * @return array
     */
    private function table($title, array $columns, array $rows)
    {
        return array(
            'type' => 'table',
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
        );
    }

    /**
     * Build a list section.
     *
     * @param string $title
     * @param array $items
     * @return array
     */
    private function listSection($title, array $items)
    {
        return array(
            'type' => 'list',
            'title' => $title,
            'items' => $this->stringList($items),
        );
    }

    /**
     * Build a plain text section.
     *
     * @param string $title
     * @param string $text
     * @return array
     */
    private function textSection($title, $text)
    {
        return array(
            'type' => 'text',
            'title' => $title,
            'text' => $text,
        );
    }

    /**
     * Build a table row.
     *
     * @param string $label
     * @param string $value
     * @param string $tone
     * @return array
     */
    private function row($label, $value, $tone)
    {
        return array(
            'label' => $this->stringify($label),
            'value' => $this->stringify($value),
            'tone' => $tone,
        );
    }

    /**
     * Resolve a human readable label for a key.
     *
     * @param string $key
     * @return string
     */
    private function labelFor($key)
    {
        $key = (string) $key;

        if (isset($this->validatorLabels[$key])) {
            return $this->validatorLabels[$key];
        }

        if (isset($this->detailLabels[$key])) {
            return $this->detailLabels[$key];
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Resolve a human readable status label.
     *
     * @param mixed $status
     * @return string
     */
    private function statusLabel($status)
    {
        if (is_array($status) || is_object($status)) {
            return '-';
        }

        $status = strtolower(trim((string) $status));

        if (isset($this->statusLabels[$status])) {
            return $this->statusLabels[$status];
        }

        return $status === '' ? '-' : ucfirst($status);
    }

    /**
     * Resolve the tone (CSS class) of a status.
     *
     * @param mixed $status
     * @return string
     */
    private function statusTone($status)
    {
        if (is_array($status) || is_object($status)) {
            return 'muted';
        }

        $status = strtolower(trim((string) $status));

        return isset($this->statusTones[$status]) ? $this->statusTones[$status] : 'muted';
    }

    /**
     * Normalize a list of values to strings.
     *
     * @param mixed $items
     * @return array
     */
    private function stringList($items)
    {
        if (!is_array($items)) {
            return array($this->stringify($items));
        }

        $values = array();

        foreach ($items as $item) {
            $values[] = $this->stringify($item);
        }

        return $values;
    }

    /**
     * Convert a scalar value to string.
     *
     * @param mixed $value
     * @return string
     */
    private function stringify($value)
    {
        if (is_null($value)) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return $this->json($value);
        }

        return (string) $value;
    }

    /**
     * Encode a value as pretty JSON.
     *
     * @param mixed $value
     * @return string
     */
    private function json($value)
    {
        $flags = JSON_PRETTY_PRINT;

        if (defined('JSON_UNESCAPED_UNICODE')) {
            $flags = $flags | JSON_UNESCAPED_UNICODE;
        }

        if (defined('JSON_UNESCAPED_SLASHES')) {
            $flags = $flags | JSON_UNESCAPED_SLASHES;
        }

        return (string) json_encode($value, $flags);
    }
}
