<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/**
 * Generuje klucze VAPID (Web Push) i zapisuje je w pliku .env, jeśli ich brakuje.
 * Wywoływana automatycznie przez docker/entrypoint.sh przy pierwszym uruchomieniu.
 */
class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid
                            {--force : Nadpisz istniejące klucze}
                            {--show : Tylko wyświetl nowe klucze, bez zapisu do .env}';

    protected $description = 'Generuje klucze VAPID dla powiadomień Web Push i zapisuje je w .env';

    public function handle()
    {
        $path = $this->laravel->environmentFilePath();
        $contents = is_file($path) ? (string) file_get_contents($path) : null;

        if (!$this->option('show') && !$this->option('force') && $this->hasKeys($contents)) {
            $this->info('Klucze VAPID są już ustawione.');
            return Command::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();

        if ($this->option('show')) {
            $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
            $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
            return Command::SUCCESS;
        }

        if ($contents === null) {
            $this->error('Brak pliku .env: ' . $path);
            return Command::FAILURE;
        }

        $values = [
            'VAPID_PUBLIC_KEY' => $keys['publicKey'],
            'VAPID_PRIVATE_KEY' => $keys['privateKey'],
        ];

        // Kontakt dla usługi push - tylko gdy go jeszcze nie ma
        if (!preg_match('/^VAPID_SUBJECT=.+$/m', $contents)) {
            $values['VAPID_SUBJECT'] = 'mailto:admin@example.com';
        }

        foreach ($values as $key => $value) {
            $contents = $this->setEnvValue($contents, $key, $value);
        }

        file_put_contents($path, $contents);

        // Bieżący proces od razu widzi nowe klucze
        config([
            'webpush.public_key' => $keys['publicKey'],
            'webpush.private_key' => $keys['privateKey'],
        ]);

        $this->info('Wygenerowano klucze VAPID i zapisano je w .env.');

        return Command::SUCCESS;
    }

    protected function hasKeys(?string $contents): bool
    {
        // Klucze z pliku .env lub ze zmiennych środowiskowych (np. docker-compose)
        if (config('webpush.public_key') && config('webpush.private_key')) {
            return true;
        }

        return $contents !== null
            && preg_match('/^VAPID_PUBLIC_KEY=\S+/m', $contents)
            && preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $contents);
    }

    protected function setEnvValue(string $contents, string $key, string $value): string
    {
        $line = $key . '=' . $value;
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $contents)) {
            return preg_replace($pattern, $line, $contents);
        }

        if ($contents !== '' && !str_ends_with($contents, "\n")) {
            $contents .= PHP_EOL;
        }

        return $contents . $line . PHP_EOL;
    }
}
