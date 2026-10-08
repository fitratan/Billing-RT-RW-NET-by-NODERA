<?php

namespace Tests\Feature;

use App\Services\MikrotikService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifikasi handshake login RouterOS API:
 *  - RouterOS lama menerima `=password=` langsung (Path 2).
 *  - RouterOS >= 6.43 / v7 mewajibkan challenge-response SHA1 (`=response=`)
 *    dan MENOLAK login password langsung — dulu ini yang bikin semua aksi
 *    MikroTik (hapus/ubah/isolir) gagal "User tidak ditemukan" karena koneksi
 *    mati tapi error-nya terbaca seakan secret tidak ada.
 *
 * Server RouterOS palsu dihitung lewat fork; fd listener diwariskan ke child,
 * parent sudah tahu port-nya. Tanpa perlu DB.
 */
class MikrotikLoginHandshakeTest extends TestCase
{
    private const CHALLENGE = '00112233445566778899aabbccddeeff';

    #[Test]
    public function legacy_routeros_accepts_direct_password_login(): void
    {
        $result = $this->runFakeServer(function ($sentence, $conn) {
            $cmd = $sentence[0] ?? '';
            if ($cmd === '/login') {
                $joined = implode(',', $sentence);
                if (str_contains($joined, '=password=')) {
                    $this->childWrite($conn, '!done');
                } elseif (str_contains($joined, '=response=')) {
                    // Routeros lama ga kenal challenge → tolak
                    fclose($conn);
                    exit(1);
                } else {
                    // /login tanpa param → tanpa challenge (legacy)
                    $this->childWrite($conn, '!done');
                }
            } elseif ($cmd === '/ppp/secret/print') {
                $this->childWrite($conn, '!re', '=name=user1', '=.id=*1', '=profile=default');
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/active/print') {
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/secret/remove') {
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/profile/print') {
                $this->childWrite($conn, '!re', '=name=paket1', '=.id=*9', '=rate-limit=2M/2M');
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/profile/remove') {
                $this->childWrite($conn, '!done');
            } else {
                $this->childWrite($conn, '!done');
            }
        });

        $this->assertTrue($result['connected'], 'login legacy dengan password langsung harus sukses');
        $this->assertTrue($result['deleted'], 'deletePppoeSecret harus sukses');
        $this->assertTrue($result['profileDeleted'], 'deletePppoeProfile (paket) harus sukses');
    }

    #[Test]
    public function challenge_response_login_used_on_newer_routeros(): void
    {
        $challenge = self::CHALLENGE;
        $expected = '00' . md5("\x00" . 's3cret' . hex2bin($challenge)); // rumus resmi
        $result = $this->runFakeServer(function ($sentence, $conn) use ($expected) {
            $cmd = $sentence[0] ?? '';
            if ($cmd === '/login') {
                $joined = implode(',', $sentence);
                if (str_contains($joined, '=response=')) {
                    $this->assertTrue(str_contains($joined, '=response=' . $expected), 'response MD5 harus memakai rumus resmi');
                    // Kalo nama/password tidak match, RouterOS balas !trap.
                    // Di sini kita selalu terima → login sukses.
                    $this->childWrite($conn, '!done');
                } elseif (str_contains($joined, '=password=')) {
                    // RouterOS baru menolak jalur lama → harus tidak dipakai
                    exit(2);
                } else {
                    $this->childWrite($conn, '!done', '=ret=' . self::CHALLENGE);
                }
            } elseif ($cmd === '/ppp/secret/print') {
                $this->childWrite($conn, '!re', '=name=user1', '=.id=*1', '=profile=default');
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/active/print') {
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/secret/remove') {
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/profile/print') {
                $this->childWrite($conn, '!re', '=name=paket1', '=.id=*9', '=rate-limit=2M/2M');
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/profile/remove') {
                $this->childWrite($conn, '!done');
            } else {
                $this->childWrite($conn, '!done');
            }
        }, 's3cret');

        $this->assertTrue($result['connected'], 'challenge-response login harus sukses');
        $this->assertTrue($result['deleted'], 'deletePppoeSecret harus sukses');
        $this->assertTrue($result['profileDeleted'], 'deletePppoeProfile (paket) harus sukses');
    }

    #[Test]
    public function new_routeros_rejects_md5_and_uses_plaintext_fallback(): void
    {
        // RouterOS 6.43+ / v7: kirim challenge ret, tapi TOLAK jalur MD5
        // (`=response=` → !trap). Klien harus fallback ke `=password=` →
        // !done → login sukses, lalu secret bisa dihapus.
        $result = $this->runFakeServer(function ($sentence, $conn) {
            $cmd = $sentence[0] ?? '';
            if ($cmd === '/login') {
                $joined = implode(',', $sentence);
                if (str_contains($joined, '=response=')) {
                    $this->childWrite($conn, '!trap', '=message=invalid user name or password');
                    $this->childWrite($conn, '!done');
                } elseif (str_contains($joined, '=password=')) {
                    $this->childWrite($conn, '!done');
                } else {
                    $this->childWrite($conn, '!done', '=ret=' . self::CHALLENGE);
                }
            } elseif ($cmd === '/ppp/secret/print') {
                $this->childWrite($conn, '!re', '=name=user1', '=.id=*1', '=profile=default');
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/active/print') {
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/secret/remove') {
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/profile/print') {
                $this->childWrite($conn, '!re', '=name=paket1', '=.id=*9', '=rate-limit=2M/2M');
                $this->childWrite($conn, '!done');
            } elseif ($cmd === '/ppp/profile/remove') {
                $this->childWrite($conn, '!done');
            } else {
                $this->childWrite($conn, '!done');
            }
        }, 's3cret');

        $this->assertTrue($result['connected'], 'login harus sukses via fallback plaintext');
        $this->assertTrue($result['deleted'], 'deletePppoeSecret harus sukses setelah fallback');
        $this->assertTrue($result['profileDeleted'], 'deletePppoeProfile (paket) harus sukses setelah fallback');
    }

    // ────────────────────────────────────────────────────────────
    //  Fake RouterOS server — dijalankan di child (fd diwariskan)
    // ────────────────────────────────────────────────────────────

    private function runFakeServer(callable $server, string $password = 'x'): array
    {
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($listener, "gagal bind server palsu: $errstr");
        [, $port] = explode(':', stream_socket_get_name($listener, false));

        $pid = pcntl_fork();
        $this->assertTrue($pid !== -1, 'pcntl_fork gagal');

        if ($pid === 0) {
            // Child: layani SATU koneksi dari MikrotikService
            $conn = @stream_socket_accept($listener, 10);
            if (!$conn) {
                exit(1);
            }
            stream_set_timeout($conn, 2);
            while (($sentence = $this->childReadSentence($conn)) !== false) {
                $server($sentence, $conn);
            }
            fclose($conn);
            exit(0);
        }

        // Parent: koneksikan MikrotikService ke port tersebut.
        $service = new MikrotikService([
            'host' => '127.0.0.1',
            'port' => (int) $port,
            'user' => 'admin',
            'pass' => $password,
        ]);

        $connected = $service->isConnected();
        $deleted = $connected && $service->deletePppoeSecret('user1');
        $profileDeleted = $connected && $service->deletePppoeProfile('paket1');

        unset($service); // tutup socket supaya child cepat dapat EOF
        pcntl_waitpid($pid, $status);

        return [
            'connected' => $connected,
            'deleted' => $deleted,
            'profileDeleted' => $profileDeleted,
        ];
    }

    private function childWrite($conn, ...$words): void
    {
        foreach ($words as $w) {
            $len = strlen($w);
            fwrite($conn, chr($len));
            if ($len > 0) {
                fwrite($conn, $w);
            }
        }
        fwrite($conn, chr(0)); // akhir kalimat
    }

    private function childReadSentence($conn): array|false
    {
        $words = [];
        while (true) {
            $first = fread($conn, 1);
            if ($first === false || $first === '') {
                return false;
            }
            $len = ord($first);
            $word = $len > 0 ? fread($conn, $len) : '';
            if ($word === false) {
                return false;
            }
            $words[] = $word;
            if ($word === '') {
                return $words;
            }
        }
    }
}