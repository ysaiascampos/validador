<?php

namespace Vendor\Validator\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Vendor\Validator\Services\ValidatorService;
use Vendor\Validator\Adapters\JsonLoader;
use Vendor\Validator\Exceptions\ValidationException;

class ValidateController extends Controller
{
    /**
     * The validator service instance.
     *
     * @var ValidatorService
     */
    private $validatorService;

    /**
     * The JSON loader instance.
     *
     * @var JsonLoader
     */
    private $jsonLoader;

    /**
     * Constructor.
     *
     * @param ValidatorService $validatorService
     * @param JsonLoader $jsonLoader
     */
    public function __construct(ValidatorService $validatorService, JsonLoader $jsonLoader)
    {
        $this->validatorService = $validatorService;
        $this->jsonLoader = $jsonLoader;
    }

    /**
     * Handle the validation API request.
     *
     * Laravel 5+ injects the Request instance; Laravel 4.2 does not support
     * method injection, so we fall back to the container.
     *
     * @param Request|null $request
     * @return JsonResponse
     */
    public function validate(Request $request = null)
    {
        if ($request === null) {
            $request = app('request');
        }

        try {
            $payload = $this->parseRequestPayload($request);

            if ($payload === null) {
                return $this->buildErrorResponse(400, 'Invalid JSON payload');
            }

            $options = $this->buildValidationOptions($payload);

            $result = $this->validatorService->validate($options);

            return $this->buildSuccessResponse($result->toArray());

        } catch (ValidationException $e) {
            return $this->buildErrorResponse(422, $e->getMessage(), $e->getContext());
        } catch (\Exception $e) {
            return $this->buildErrorResponse(500, 'Internal validation error: ' . $e->getMessage());
        }
    }

    /**
     * Parse the request payload.
     *
     * @param Request $request
     * @return array|null
     */
    private function parseRequestPayload(Request $request)
    {
        $contentType = $request->header('Content-Type', '');

        if (strpos($contentType, 'application/json') !== false) {
            $content = $request->getContent();
            $payload = $this->jsonLoader->parse($content);
            return $payload;
        }

        $input = $request->all();

        if (!empty($input)) {
            return $input;
        }

        $content = $request->getContent();
        if (!empty($content)) {
            return $this->jsonLoader->parse($content);
        }

        return array();
    }

    /**
     * Build validation options from the request payload.
     *
     * @param array $payload
     * @return array
     */
    private function buildValidationOptions(array $payload)
    {
        $options = array();

        if (isset($payload['connections'])) {
            $options['connections'] = $payload['connections'];
            $options['database']['connections'] = $payload['connections'];
        }

        if (isset($payload['connection'])) {
            $options['connection'] = $payload['connection'];
            $options['database']['connection'] = $payload['connection'];
        }

        if (isset($payload['schemas'])) {
            $options['database']['schemas'] = $payload['schemas'];
        }

        if (isset($payload['database'])) {
            $options['database'] = array_merge(
                isset($options['database']) ? $options['database'] : array(),
                $payload['database']
            );
        }

        if (isset($payload['env'])) {
            $options['env'] = $payload['env'];
        }

        if (isset($payload['git'])) {
            $options['git'] = $payload['git'];
        }

        if (isset($payload['enable'])) {
            $options['database'] = isset($payload['enable']['database']) ? $payload['enable']['database'] : true;
            $options['env'] = isset($payload['enable']['env']) ? $payload['enable']['env'] : true;
            $options['git'] = isset($payload['enable']['git']) ? $payload['enable']['git'] : true;
        }

        return $options;
    }

    /**
     * Build a successful JSON response.
     *
     * @param array $data
     * @return JsonResponse
     */
    private function buildSuccessResponse(array $data)
    {
        return new JsonResponse(
            array(
                'status'  => $data['success'] ? 'pass' : 'fail',
                'success' => $data['success'],
                'data'    => $data,
            ),
            200
        );
    }

    /**
     * Build an error JSON response.
     *
     * @param int $statusCode
     * @param string $message
     * @param array $context
     * @return JsonResponse
     */
    private function buildErrorResponse($statusCode, $message, array $context = array())
    {
        return new JsonResponse(
            array(
                'status'  => 'error',
                'success' => false,
                'error'   => $message,
                'context' => $context,
            ),
            $statusCode
        );
    }
}
