<?php

namespace Vendor\Validator\Tests;

use PHPUnit\Framework\TestCase;
use Vendor\Validator\Services\ReportPresenter;

class ReportPresenterTest extends TestCase
{
    public function testPresentsSummaryAndValidators()
    {
        $presenter = new ReportPresenter();

        $report = $presenter->present($this->result(), array(
            'title' => 'Reporte de prueba',
            'generated_at' => '2026-11-12 10:00:00',
            'environment' => 'local',
        ));

        $this->assertSame('Reporte de prueba', $report['title']);
        $this->assertSame('local', $report['environment']);
        $this->assertFalse($report['success']);
        $this->assertSame('CON ERRORES', $report['status_label']);
        $this->assertSame(3, $report['counts']['validators']);
        $this->assertSame(1, $report['counts']['errors']);
        $this->assertSame(1, $report['counts']['warnings']);
        $this->assertEquals(12.5, $report['execution_time']);

        $labels = array();
        foreach ($report['validators'] as $validator) {
            $labels[] = $validator['label'];
        }

        $this->assertContains('Base de datos', $labels);
        $this->assertContains('Variables de entorno', $labels);
        $this->assertContains('Estado de Git', $labels);
    }

    public function testEnvSectionsExposeStatusesAndProtectedVariables()
    {
        $presenter = new ReportPresenter();
        $report = $presenter->present($this->result());

        $env = $this->validator($report, 'env');

        $this->assertSame('OK', $env['status_label']);

        $statusSection = $this->section($env, 'Estado de las variables');
        $this->assertSame('table', $statusSection['type']);
        $this->assertSame('Variable', $statusSection['columns'][0]);

        $rows = $this->rowsByLabel($statusSection);
        $this->assertSame('OK', $rows['APP_ENV']['value']);
        $this->assertSame('success', $rows['APP_ENV']['tone']);
        $this->assertSame('Falta', $rows['DB_HOST']['value']);
        $this->assertSame('danger', $rows['DB_HOST']['tone']);

        $protected = $this->section($env, 'Variables protegidas (valor oculto)');
        $this->assertSame('list', $protected['type']);
        $this->assertContains('API_TOKEN', $protected['items']);
    }

    public function testDatabaseSectionsAndStatuses()
    {
        $presenter = new ReportPresenter();
        $report = $presenter->present($this->result());

        $database = $this->validator($report, 'database');

        $connections = $this->section($database, 'Conexiones');
        $this->assertSame('list', $connections['type']);
        $this->assertSame(array('oracle', 'oracle_export'), $connections['items']);

        $tables = $this->section($database, 'Tablas');
        $rows = $this->rowsByLabel($tables);
        $this->assertSame('Existe', $rows['ASIGNACION.PERSONAS']['value']);
        $this->assertSame('success', $rows['ASIGNACION.PERSONAS']['tone']);
        $this->assertSame('No existe', $rows['ASIGNACION.FALTANTE']['value']);
        $this->assertSame('danger', $rows['ASIGNACION.FALTANTE']['tone']);
    }

    public function testGenericSectionsLabelGitDetails()
    {
        $presenter = new ReportPresenter();
        $report = $presenter->present($this->result());

        $git = $this->validator($report, 'git');
        $rows = $this->rowsByLabel($this->section($git, 'Detalle'));

        $this->assertArrayHasKey('Rama actual', $rows);
        $this->assertArrayHasKey('Commit actual', $rows);
        $this->assertSame('main', $rows['Rama actual']['value']);
    }

    /**
     * Sample consolidated result.
     *
     * @return array
     */
    private function result()
    {
        return array(
            'validator' => 'consolidated',
            'success' => false,
            'errors' => array('Error general de prueba'),
            'warnings' => array('Advertencia general de prueba'),
            'execution_time' => 12.456,
            'details' => array(
                'env' => array(
                    'validator' => 'env',
                    'success' => true,
                    'errors' => array(),
                    'warnings' => array(),
                    'execution_time' => 1.2,
                    'details' => array(
                        'env_status' => array(
                            'APP_ENV' => 'ok',
                            'DB_HOST' => 'missing',
                        ),
                        'env_values' => array(
                            'APP_ENV' => 'local',
                        ),
                        'env_sensitive_variables' => array('APP_KEY', 'API_TOKEN'),
                        'env_sensitive_skipped' => 2,
                    ),
                ),
                'database' => array(
                    'validator' => 'database',
                    'success' => false,
                    'errors' => array('Table does not exist: ASIGNACION.FALTANTE'),
                    'warnings' => array(),
                    'execution_time' => 8.4,
                    'details' => array(
                        'connections' => array('oracle', 'oracle_export'),
                        'tables' => array(
                            'ASIGNACION.PERSONAS' => 'exists',
                            'ASIGNACION.FALTANTE' => 'not_exists',
                        ),
                        'column_types' => array(
                            'ASIGNACION.PERSONAS.RUT' => 'mismatch',
                        ),
                    ),
                ),
                'git' => array(
                    'validator' => 'git',
                    'success' => true,
                    'errors' => array(),
                    'warnings' => array(),
                    'execution_time' => 2.8,
                    'details' => array(
                        'branch' => 'main',
                        'commit' => 'abc123',
                    ),
                ),
            ),
        );
    }

    private function validator(array $report, $name)
    {
        foreach ($report['validators'] as $validator) {
            if ($validator['name'] === $name) {
                return $validator;
            }
        }

        $this->fail('Validator not found: ' . $name);
    }

    private function section(array $validator, $title)
    {
        foreach ($validator['sections'] as $section) {
            if ($section['title'] === $title) {
                return $section;
            }
        }

        $this->fail('Section not found: ' . $title);
    }

    private function rowsByLabel(array $section)
    {
        $rows = array();

        foreach ($section['rows'] as $row) {
            $rows[$row['label']] = $row;
        }

        return $rows;
    }
}
