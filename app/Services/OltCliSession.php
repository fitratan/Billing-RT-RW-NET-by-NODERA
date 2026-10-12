<?php

namespace App\Services;

use App\Models\Olt;
use Illuminate\Support\Facades\Log;
use phpseclib3\Net\SSH2;

class OltCliSession
{
    /** @var resource|null */
    private $telnetFp = null;
    private ?SSH2 $ssh = null;
    private string $transport = 'none';
    private ?string $lastError = null;

    public function __construct(
        private readonly Olt $olt,
        private readonly int $timeout = 10
    ) {}

    public function connect(): bool
    {
        $mode = strtolower(trim((string)($this->olt->connection_mode ?? '')));
        $port = (int) ($this->olt->port ?: 0);
        $telnetPort = (int) ($this->olt->telnet_port ?: ($port === 23 ? 23 : 23));
        $sshPort = (int) ($port === 22 ? 22 : 22);

        $preferSsh = ($mode === 'ssh' || $port === 22);

        if ($preferSsh) {
            if ($this->connectSsh($sshPort)) {
                return true;
            }
            if ($this->connectTelnet($telnetPort)) {
                return true;
            }
        } else {
            if ($this->connectTelnet($telnetPort)) {
                return true;
            }
            if ($this->connectSsh($sshPort)) {
                return true;
            }
        }

        return false;
    }

    public function connectTelnet(?int $overridePort = null): bool
    {
        $host = trim((string) $this->olt->host);
        $port = $overridePort ?: (int) ($this->olt->telnet_port ?: 23);

        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p) && empty($this->olt->telnet_port)) {
                $port = (int) $p;
            }
        }

        try {
            $fp = @fsockopen($host, $port, $errno, $errstr, 5);
            if (!is_resource($fp)) {
                $this->lastError = "Telnet connect failed to {$host}:{$port} ({$errstr})";
                Log::debug("[OltCliSession] {$this->lastError}");
                return false;
            }

            stream_set_timeout($fp, 2);
            stream_set_blocking($fp, false);

            // Read banner / login prompt
            $this->telnetReadUntil($fp, '/(?:user ?name|login|username)\s*:\s*$/i', 6);
            fwrite($fp, ($this->olt->username ?: 'admin') . "\r\n");

            // Read password prompt
            $this->telnetReadUntil($fp, '/(?:password|passwd)\s*:\s*$/i', 6);
            fwrite($fp, ((string) $this->olt->password) . "\r\n");

            // Wait for user or enable prompt
            $prompt = $this->telnetReadUntil($fp, '/[\w\-.]+\s*[>#]\s*$/', 6);

            // Elevate to enable mode if at '>'
            if (preg_match('/>\s*$/', $prompt)) {
                fwrite($fp, "enable\r\n");
                $enableRes = $this->telnetReadUntil($fp, '/(?:password|passwd|#)\s*$/i', 4);
                if (preg_match('/(?:password|passwd)\s*:\s*$/i', $enableRes)) {
                    $enablePw = $this->olt->enable_password ?: $this->olt->password;
                    fwrite($fp, $enablePw . "\r\n");
                    $this->telnetReadUntil($fp, '/#\s*$/', 4);
                }
            }

            $this->telnetFp = $fp;
            $this->transport = 'telnet';
            return true;
        } catch (\Throwable $e) {
            $this->lastError = "Telnet error: " . $e->getMessage();
            Log::debug("[OltCliSession] {$this->lastError}");
            return false;
        }
    }

    public function connectSsh(?int $overridePort = null): bool
    {
        $host = trim((string) $this->olt->host);
        $port = $overridePort ?: (int) ($this->olt->port ?: 22);

        if (str_contains($host, ':')) {
            [$h, $p] = explode(':', $host, 2);
            $host = $h;
            if (is_numeric($p) && empty($this->olt->port)) {
                $port = (int) $p;
            }
        }

        try {
            $ssh = new SSH2($host, $port, 5);
            if (!$ssh->login($this->olt->username ?: 'admin', $this->olt->password ?: '')) {
                $this->lastError = "SSH authentication failed for {$this->olt->name}";
                Log::debug("[OltCliSession] {$this->lastError}");
                return false;
            }
            $ssh->setTimeout(8);
            $this->ssh = $ssh;
            $this->transport = 'ssh';
            return true;
        } catch (\Throwable $e) {
            $this->lastError = "SSH connection error: " . $e->getMessage();
            Log::debug("[OltCliSession] {$this->lastError}");
            return false;
        }
    }

    public function command(string $cmd, float $maxSec = 8.0): string
    {
        if ($this->transport === 'telnet' && is_resource($this->telnetFp)) {
            fwrite($this->telnetFp, trim($cmd) . "\r\n");
            return $this->telnetReadUntil($this->telnetFp, '/[>#]\s*$/', $maxSec, true);
        }

        if ($this->transport === 'ssh' && $this->ssh) {
            $this->ssh->write(trim($cmd) . "\n");
            usleep(150000);
            return (string) $this->ssh->read();
        }

        return '';
    }

    public function commands(array $cmds): array
    {
        $results = [];
        foreach ($cmds as $cmd) {
            $results[$cmd] = $this->command($cmd);
            usleep(100000);
        }
        return $results;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function disconnect(): void
    {
        if (is_resource($this->telnetFp)) {
            try {
                fwrite($this->telnetFp, "exit\r\n");
            } catch (\Throwable $e) {}
            @fclose($this->telnetFp);
            $this->telnetFp = null;
        }

        if ($this->ssh) {
            try {
                $this->ssh->disconnect();
            } catch (\Throwable $e) {}
            $this->ssh = null;
        }
        $this->transport = 'none';
    }

    private function telnetReadUntil($fp, string $promptRegex, float $maxSec, bool $autoPager = false): string
    {
        $buffer = '';
        $start = microtime(true);

        while ((microtime(true) - $start) < $maxSec) {
            $chunk = fread($fp, 8192);
            if ($chunk === '' || $chunk === false) {
                usleep(30000);
                continue;
            }

            $buffer .= $chunk;

            if ($autoPager && preg_match('/--\s*more\s*--|press any key|\(more\)|next page|type <space>/i', $chunk)) {
                fwrite($fp, " ");
                continue;
            }

            if (preg_match($promptRegex, substr($buffer, -160))) {
                break;
            }
        }

        return $buffer;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
