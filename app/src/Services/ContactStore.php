<?php

namespace App\Services;

class ContactStore
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? dirname(__DIR__, 2) . '/var/contacts';
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    public function all(): array
    {
        $contacts = [];
        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            $contact = json_decode((string) file_get_contents($file), true);
            if (is_array($contact)) {
                $contacts[] = $contact;
            }
        }
        usort($contacts, fn(array $a, array $b) => ($b['dateOfCreation'] ?? 0) <=> ($a['dateOfCreation'] ?? 0));
        return $contacts;
    }

    public function find(string $filename): ?array
    {
        if (!$this->isSafeFilename($filename)) {
            return null;
        }
        $path = $this->directory . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }
        $contact = json_decode((string) file_get_contents($path), true);
        return is_array($contact) ? $contact : null;
    }

    public function create(array $contact): array
    {
        $timestamp = time();
        $emailPart = preg_replace('/[^a-zA-Z0-9@._-]/', '_', $contact['email']);
        $filename = $timestamp . '_' . $emailPart . '.json';

        $record = [
            'email' => $contact['email'],
            'subject' => $contact['subject'],
            'message' => $contact['message'],
            'dateOfCreation' => $timestamp,
            'dateOfLastUpdate' => $timestamp,
        ];

        file_put_contents($this->directory . '/' . $filename, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return ['filename' => $filename, 'contact' => $record];
    }

    public function update(string $filename, array $values): ?array
    {
        $contact = $this->find($filename);
        if ($contact === null) {
            return null;
        }

        foreach ($values as $key => $value) {
            $contact[$key] = $value;
        }
        $contact['dateOfLastUpdate'] = time();

        file_put_contents($this->directory . '/' . $filename, json_encode($contact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $contact;
    }

    public function delete(string $filename): bool
    {
        if (!$this->isSafeFilename($filename) || !is_file($this->directory . '/' . $filename)) {
            return false;
        }
        return unlink($this->directory . '/' . $filename);
    }

    private function isSafeFilename(string $filename): bool
    {
        return $filename !== ''
            && $filename === basename($filename)
            && str_ends_with($filename, '.json')
            && preg_match('/^[a-zA-Z0-9@._-]+\.json$/', $filename) === 1;
    }
}
