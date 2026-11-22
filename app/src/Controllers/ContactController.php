<?php

namespace App\Controllers;

use App\Lib\Controllers\AbstractController;
use App\Lib\Http\Request;
use App\Lib\Http\Response;
use App\Services\ContactStore;

class ContactController extends AbstractController
{
    private ContactStore $store;

    public function __construct()
    {
        $this->store = new ContactStore();
    }

    public function process(Request $request): Response
    {
        $contentType = $request->getHeader('Content-Type');
        if (in_array($request->getMethod(), ['POST', 'PATCH'], true)
            && ($contentType === null || stripos($contentType, 'application/json') === false)
        ) {
            return $this->json(['error' => 'Content-Type must be application/json'], 415);
        }

        return match ($request->getMethod()) {
            'POST' => $this->create($request),
            'GET' => $this->read($request),
            'PATCH' => $this->update($request),
            'DELETE' => $this->delete($request),
            default => $this->json(['error' => 'Method not allowed'], 405),
        };
    }

    private function create(Request $request): Response
    {
        try {
            $body = $request->getJsonBody();
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }

        $allowed = ['email', 'subject', 'message'];
        if (array_diff(array_keys($body), $allowed) !== [] || count($body) !== 3) {
            return $this->json(['error' => 'Only email, subject and message properties are allowed'], 400);
        }
        if (array_diff($allowed, array_keys($body)) !== []) {
            return $this->json(['error' => 'email, subject and message are required'], 400);
        }

        $validationError = $this->validateContactValues($body, true);
        if ($validationError !== null) {
            return $this->json(['error' => $validationError], 400);
        }

        $created = $this->store->create($body);
        return $this->json(['file' => $created['filename']], 201);
    }

    private function read(Request $request): Response
    {
        $filename = $request->getRouteParameter('filename');
        if ($filename === null) {
            return $this->json($this->store->all(), 200);
        }

        $contact = $this->store->find($filename);
        return $contact === null
            ? $this->json(['error' => 'Contact not found'], 404)
            : $this->json($contact, 200);
    }

    private function update(Request $request): Response
    {
        $filename = $request->getRouteParameter('filename');
        if ($filename === null || $this->store->find($filename) === null) {
            return $this->json(['error' => 'Contact not found'], 404);
        }

        try {
            $body = $request->getJsonBody();
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 400);
        }

        $allowed = ['email', 'subject', 'message'];
        if ($body === [] || array_diff(array_keys($body), $allowed) !== []) {
            return $this->json(['error' => 'Only email, subject and message properties are allowed'], 400);
        }

        $validationError = $this->validateContactValues($body, false);
        if ($validationError !== null) {
            return $this->json(['error' => $validationError], 400);
        }

        $contact = $this->store->update($filename, $body);
        return $contact === null
            ? $this->json(['error' => 'Contact not found'], 404)
            : $this->json($contact, 200);
    }

    private function delete(Request $request): Response
    {
        $filename = $request->getRouteParameter('filename');
        if ($filename === null || !$this->store->delete($filename)) {
            return $this->json(['error' => 'Contact not found'], 404);
        }

        $response = new Response();
        $response->statusCode = 204;
        $response->content = '';
        return $response;
    }

    private function validateContactValues(array $values, bool $required): ?string
    {
        $fields = $required ? ['email', 'subject', 'message'] : array_keys($values);
        foreach ($fields as $field) {
            if (!array_key_exists($field, $values) || !is_string($values[$field]) || trim($values[$field]) === '') {
                return $field . ' must be a non-empty string';
            }
        }

        if (isset($values['email']) && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            return 'email must be a valid email address';
        }
        return null;
    }

    private function json(array $data, int $status): Response
    {
        $response = new Response();
        $response->statusCode = $status;
        $response->content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $response;
    }
}
