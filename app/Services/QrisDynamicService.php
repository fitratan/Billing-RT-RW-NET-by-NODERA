<?php

namespace App\Services;

use chillerlan\QRCode\Common\GDLuminanceSource;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

// Auto-load embedded QR Code engine if vendor package is absent on shared hosting
if (file_exists(__DIR__ . '/../Support/QRCode/autoload.php')) {
    require_once __DIR__ . '/../Support/QRCode/autoload.php';
}

/**
 * QrisDynamicService — Core Engine for converting Static QRIS (e.g. GoPay / GoBiz)
 * into Dynamic QRIS with exact invoice amounts, unique 3-digit verification codes,
 * and automated settlement reconciliation.
 *
 * Fully compliant with EMVCo MPM (Merchant-Presented Mode) & Bank Indonesia QRIS specs.
 */
class QrisDynamicService
{
    /**
     * Thoroughly sanitize and normalize arbitrary QRIS payload string.
     * Cleans whitespace, UTF-8 BOM, zero-width spaces, quotes, backticks, newlines,
     * slices from '000201' up to '6304XXXX', and repairs CRC if needed.
     */
    public static function sanitizePayload(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        // 1. Remove UTF-8 BOM and zero-width/control characters
        $clean = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', (string) $raw);
        $clean = trim($clean);
        // Remove enclosing quotes, backticks, backslashes
        $clean = trim($clean, "\"'` \t\n\r\0\x0B\\");

        // 2. Remove all internal newlines/carriage returns from multi-line text wrapping
        $clean = str_replace(["\r", "\n", "\t"], '', $clean);

        // 3. Find 000201 prefix if any extra label exists (e.g. "String: 000201...")
        $pos = strpos($clean, '000201');
        if ($pos !== false) {
            $clean = substr($clean, $pos);
        }

        // 4. If CRC tag 6304 is present, trim anything trailing after the 4 hex chars
        if (preg_match('/^(000201.+?6304[0-9A-Fa-f]{4})/s', $clean, $matches)) {
            $clean = $matches[1];
        }

        // 5. If it starts with 000201 and has 6304, but CRC was cut off or invalid, auto-repair CRC
        if (str_starts_with($clean, '000201')) {
            $crcPos = strrpos($clean, '6304');
            if ($crcPos !== false) {
                $body = substr($clean, 0, $crcPos + 4);
                $expectedCrc = self::calculateCrc16($body);
                $givenCrc = substr($clean, $crcPos + 4, 4);
                if (strtoupper($givenCrc) !== strtoupper($expectedCrc)) {
                    $clean = $body . $expectedCrc;
                }
            }
        }

        return trim($clean);
    }

    /**
     * Normalize arbitrary amount input into a standard float.
     * Supports Indonesian currency strings with dot thousands separators (e.g. "150.000"),
     * comma thousands separators ("150,000"), or raw numeric values.
     */
    public static function normalizeAmount(mixed $amount): float
    {
        if (is_int($amount)) {
            return (float) $amount;
        }

        $str = trim((string) $amount);
        $str = preg_replace('/[^0-9,\.]/', '', $str);

        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $str)) {
            $str = str_replace('.', '', $str);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+$/', $str)) {
            $str = str_replace(',', '', $str);
        } elseif (str_contains($str, '.') && str_contains($str, ',')) {
            $str = str_replace('.', '', $str);
            $str = str_replace(',', '.', $str);
        } else {
            $str = str_replace(',', '.', $str);
        }

        $val = (float) $str;
        return $val > 0 ? $val : 0.0;
    }

    /**
     * Parse EMVCo TLV (Tag-Length-Value) string into an associative array.
     *
     * @param string $qris Raw QRIS string (e.g. 000201010211...)
     * @return array<string, string> Key-value map of tag => value
     */
    public static function parse(string $qris): array
    {
        $qris = self::sanitizePayload($qris);
        $tlvs = [];
        $i = 0;
        $len = strlen($qris);

        while ($i < $len) {
            if ($i + 4 > $len) {
                break;
            }

            $tag = substr($qris, $i, 2);
            $rawLength = substr($qris, $i + 2, 2);

            if (!ctype_digit($rawLength)) {
                break;
            }

            $length = intval($rawLength);
            if ($i + 4 + $length > $len) {
                // If tag 63 is truncated at CRC, take remaining
                $value = substr($qris, $i + 4);
                $tlvs[$tag] = $value;
                break;
            }

            $value = substr($qris, $i + 4, $length);
            $tlvs[$tag] = $value;
            $i += 4 + $length;
        }

        return $tlvs;
    }

    /**
     * Calculate EMVCo standard CRC16-CCITT checksum (Polynomial 0x1021, Init 0xFFFF).
     *
     * @param string $payload The full QR payload up to and including '6304'
     * @return string 4-character uppercase hexadecimal string
     */
    public static function calculateCrc16(string $payload): string
    {
        $crc = 0xFFFF;
        $len = strlen($payload);

        for ($c = 0; $c < $len; $c++) {
            $crc ^= (ord($payload[$c]) << 8);
            for ($i = 0; $i < 8; $i++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Validate whether a given QRIS string has a valid CRC16 checksum.
     */
    public static function validateCrc(string $qris): bool
    {
        $qris = self::sanitizePayload($qris);
        if (strlen($qris) < 8) {
            return false;
        }

        $pos = strrpos($qris, '6304');
        if ($pos === false || $pos !== (strlen($qris) - 8)) {
            // Check if last 8 chars match 6304XXXX
            $body = substr($qris, 0, -4);
            $checksum = substr($qris, -4);
            return strtoupper(self::calculateCrc16($body)) === strtoupper($checksum);
        }

        $body = substr($qris, 0, $pos + 4);
        $expectedChecksum = substr($qris, $pos + 4, 4);

        return strtoupper(self::calculateCrc16($body)) === strtoupper($expectedChecksum);
    }

    /**
     * Convert Static QRIS payload string to Dynamic QRIS with exact amount and invoice reference.
     *
     * @param string $staticQris Raw Static QRIS string
     * @param float|int|string $amount Exact amount in IDR
     * @param string|null $invoiceNumber Invoice number or reference code
     * @return string Valid Dynamic QRIS string ready for scanning
     */
    public static function convertToDynamic(?string $staticQris, float|int|string $amount, ?string $invoiceNumber = null): string
    {
        if (empty($staticQris)) {
            throw new \InvalidArgumentException('Format payload QRIS tidak valid atau tidak dapat diuraikan.');
        }

        $staticQris = self::sanitizePayload($staticQris);
        $tlv = self::parse($staticQris);

        if (empty($tlv)) {
            throw new \InvalidArgumentException('Format payload QRIS tidak valid atau tidak dapat diuraikan.');
        }

        // Tag 00: Payload Format Indicator (01)
        if (!isset($tlv['00'])) {
            $tlv['00'] = '01';
        }

        // Tag 01: Point of Initiation Method -> '12' (Dynamic QR)
        $tlv['01'] = '12';

        // Tag 53: Transaction Currency (360 = IDR)
        if (!isset($tlv['53'])) {
            $tlv['53'] = '360';
        }

        $amount = self::normalizeAmount($amount);

        // Tag 54: Transaction Amount
        // Format amount: integer string without decimals if whole number, or up to 2 decimals
        if (floor($amount) == $amount) {
            $amountStr = (string) ((int) $amount);
        } else {
            $amountStr = number_format($amount, 2, '.', '');
        }
        $tlv['54'] = $amountStr;

        // Tag 58: Country Code (ID)
        if (!isset($tlv['58'])) {
            $tlv['58'] = 'ID';
        }

        // Tag 62: Additional Data Field Template (Bill Number / Invoice Reference)
        if (!empty($invoiceNumber)) {
            $cleanRef = substr(preg_replace('/[^A-Za-z0-9\-_]/', '', $invoiceNumber), 0, 25);
            $sub01 = '01' . sprintf('%02d', strlen($cleanRef)) . $cleanRef;

            if (isset($tlv['62'])) {
                $subTlvs = self::parse($tlv['62']);
                $subTlvs['01'] = $cleanRef;
                $new62 = '';
                uksort($subTlvs, fn ($a, $b) => intval($a) <=> intval($b));
                foreach ($subTlvs as $sTag => $sVal) {
                    $new62 .= str_pad((string) $sTag, 2, '0', STR_PAD_LEFT) . sprintf('%02d', strlen($sVal)) . $sVal;
                }
                $tlv['62'] = $new62;
            } else {
                $tlv['62'] = $sub01;
            }
        }

        // Remove old Tag 63 before recalculating checksum
        unset($tlv['63']);

        // Sort tags in ascending EMVCo sequence
        uksort($tlv, fn ($a, $b) => intval($a) <=> intval($b));

        // Construct raw payload
        $payload = '';
        foreach ($tlv as $tag => $val) {
            $tagStr = str_pad((string) $tag, 2, '0', STR_PAD_LEFT);
            $payload .= $tagStr . sprintf('%02d', strlen($val)) . $val;
        }

        // Append Tag 63 prefix and calculate CRC16
        $payload .= '6304';
        $crc = self::calculateCrc16($payload);

        return $payload . $crc;
    }

    /**
     * Extract structured merchant details from a QRIS string.
     *
     * @param string|null $qris
     * @return array<string, mixed>
     */
    public static function extractMerchantInfo(?string $qris): array
    {
        if (empty($qris)) {
            return [
                'merchant_name'   => null,
                'merchant_city'   => null,
                'merchant_pan'    => null,
                'acquirer'        => null,
                'nmid'            => null,
                'criteria'        => null,
                'postal_code'     => null,
                'currency'        => null,
                'country_code'    => null,
                'tip_type'        => null,
                'tip_amount'      => null,
                'tip_percentage'  => null,
            ];
        }

        $qris = self::sanitizePayload($qris);
        if (empty($qris)) {
            return [
                'merchant_name'   => null,
                'merchant_city'   => null,
                'merchant_pan'    => null,
                'acquirer'        => null,
                'nmid'            => null,
                'criteria'        => null,
                'postal_code'     => null,
                'currency'        => null,
                'country_code'    => null,
                'tip_type'        => null,
                'tip_amount'      => null,
                'tip_percentage'  => null,
            ];
        }

        $tlv = self::parse($qris);

        // Find National Merchant ID (NMID) or Acquirer info in Tag 26 - 51
        $merchantPan = null;
        $acquirer = null;
        $nmid = null;
        $criteria = null;

        for ($t = 26; $t <= 51; $t++) {
            $tagKey = str_pad((string) $t, 2, '0', STR_PAD_LEFT);
            if (isset($tlv[$tagKey])) {
                $sub = self::parse($tlv[$tagKey]);
                if (isset($sub['00'])) {
                    $acquirer = $sub['00'];
                }
                if (isset($sub['01'])) {
                    $merchantPan = $sub['01'];
                }
                if (isset($sub['02'])) {
                    $merchantPan = $merchantPan ?: $sub['02'];
                }
                if ($t === 51) {
                    $nmid = $sub['02'] ?? ($sub['01'] ?? null);
                    $criteria = $sub['03'] ?? null;
                }
            }
        }

        if (!$nmid && $merchantPan) {
            $nmid = $merchantPan;
        }

        // Tag 62: Extract Terminal Label (Subtag 07)
        $terminalId = 'A01';
        if (isset($tlv['62'])) {
            $sub62 = self::parse($tlv['62']);
            if (!empty($sub62['07'])) {
                $terminalId = $sub62['07'];
            }
        }

        $initMethod = $tlv['01'] ?? '11';
        $type = $initMethod === '12' ? 'DYNAMIC' : 'STATIC';

        return [
            'raw_string'        => $qris,
            'version'           => $tlv['00'] ?? '01',
            'type'              => $type,
            'is_dynamic'        => $type === 'DYNAMIC',
            'merchant_name'     => $tlv['59'] ?? 'Merchant QRIS',
            'merchant_city'     => $tlv['60'] ?? 'INDONESIA',
            'postal_code'       => $tlv['61'] ?? null,
            'mcc'               => $tlv['52'] ?? '4814',
            'currency'          => $tlv['53'] ?? '360',
            'country'           => $tlv['58'] ?? 'ID',
            'amount'            => isset($tlv['54']) ? (float) $tlv['54'] : null,
            'acquirer'          => $acquirer ?? 'NODERA PAY',
            'merchant_pan'      => $merchantPan,
            'nmid'              => $nmid,
            'terminal_id'       => $terminalId,
            'merchant_criteria' => $criteria,
            'crc_valid'         => self::validateCrc($qris),
        ];
    }

    /**
     * Resolve and decode QRIS EMVCo string from gateway config array.
     * Checks qris_raw_string first, then qris_image_path (across multiple directories),
     * then qris_image_base64, then resolved QRIS image URL.
     *
     * @param array $config
     * @return string|null
     */
    public static function resolveAndDecodeFromConfig(array $config): ?string
    {
        // 1. If raw string is already populated and valid EMVCo
        if (!empty($config['qris_raw_string']) && str_starts_with(trim($config['qris_raw_string']), '000201')) {
            return trim($config['qris_raw_string']);
        }

        // 2. Decode from image path (resolves relative/storage/uploads)
        if (!empty($config['qris_image_path'])) {
            $detected = self::decodeFromImage($config['qris_image_path']);
            if ($detected && str_starts_with($detected, '000201')) {
                return $detected;
            }
        }

        // 3. Decode from base64 if present
        if (!empty($config['qris_image_base64'])) {
            $detected = self::decodeFromImage($config['qris_image_base64']);
            if ($detected && str_starts_with($detected, '000201')) {
                return $detected;
            }
        }

        // 4. Decode via QRISController::resolveQrisImageUrl
        try {
            $imageUrl = \App\Http\Controllers\QRISController::resolveQrisImageUrl($config);
            if ($imageUrl) {
                $detected = self::decodeFromImage($imageUrl);
                if ($detected && str_starts_with($detected, '000201')) {
                    return $detected;
                }
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Attempt to decode and extract the QRIS string from an image file, uploaded file,
     * base64 data URI, or relative storage path.
     *
     * @param UploadedFile|string $file File path, URL, base64 data URI, or UploadedFile instance
     * @return string|null Decoded QRIS string or null on failure
     */
    public static function decodeFromImage(UploadedFile|string $file): ?string
    {
        if (!class_exists(QRCode::class, false)) {
            $fallback = __DIR__ . '/../Support/QRCode/autoload.php';
            if (file_exists($fallback)) {
                require_once $fallback;
            }
        }

        try {
            @ini_set('memory_limit', '256M');
            $rawContent = null;

            if ($file instanceof UploadedFile) {
                $rawContent = @file_get_contents($file->getRealPath());
            } elseif (is_string($file)) {
                $trimmed = trim($file);

                // Case A: Raw binary image data (JPEG / PNG / WebP)
                if (str_starts_with($trimmed, "\xFF\xD8\xFF") || str_starts_with($trimmed, "\x89PNG") || str_starts_with($trimmed, "RIFF")) {
                    $rawContent = $trimmed;
                }
                // Case B: Data URI (e.g. data:image/png;base64,...)
                elseif (preg_match('#^data:image/[^;]+;base64,(.+)$#is', $trimmed, $matches)) {
                    $rawContent = base64_decode($matches[1]);
                }
                // Case C: Raw base64 string
                elseif (!str_contains($trimmed, "\n") && strlen($trimmed) > 100 && (base64_encode(base64_decode($trimmed, true) ?: '') === $trimmed)) {
                    $rawContent = base64_decode($trimmed);
                }
                // Case D: Absolute file path directly exists
                elseif (file_exists($trimmed) && is_file($trimmed)) {
                    $rawContent = @file_get_contents($trimmed);
                }
                // Case E: Remote URL (http / https)
                elseif (filter_var($trimmed, FILTER_VALIDATE_URL) || str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
                    try {
                        $ch = curl_init($trimmed);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        $curlResult = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        if ($httpCode >= 200 && $httpCode < 300 && $curlResult) {
                            $rawContent = $curlResult;
                        }
                    } catch (\Throwable $e) {
                        // Ignore
                    }
                }
                // Case F: Relative file path or storage path - test exhaustive candidate locations
                else {
                    $clean = ltrim($trimmed, '/');
                    $baseName = basename($clean);
                    $cleanNoStorage = str_starts_with($clean, 'storage/') ? substr($clean, 8) : $clean;
                    $cleanNoUploads = str_starts_with($clean, 'uploads/') ? substr($clean, 8) : $clean;

                    $candidates = [
                        public_path($clean),
                        public_path('uploads/' . $cleanNoUploads),
                        public_path('uploads/qris/' . $baseName),
                        base_path('uploads/qris/' . $baseName),
                        base_path('uploads/' . $cleanNoUploads),
                        public_path('storage/' . $cleanNoStorage),
                        public_path('storage/qris/' . $baseName),
                        storage_path('app/public/' . $cleanNoStorage),
                        storage_path('app/public/qris/' . $baseName),
                        storage_path('app/public/' . $baseName),
                        base_path($clean),
                    ];

                    foreach ($candidates as $cand) {
                        if (file_exists($cand) && is_file($cand)) {
                            $rawContent = @file_get_contents($cand);
                            if ($rawContent) {
                                break;
                            }
                        }
                    }

                    // Also check via Laravel Storage disk('public')
                    if (!$rawContent) {
                        try {
                            $storageDisk = \Illuminate\Support\Facades\Storage::disk('public');
                            if ($storageDisk->exists($cleanNoStorage)) {
                                $rawContent = $storageDisk->get($cleanNoStorage);
                            } elseif ($storageDisk->exists('qris/' . $baseName)) {
                                $rawContent = $storageDisk->get('qris/' . $baseName);
                            } elseif ($storageDisk->exists($baseName)) {
                                $rawContent = $storageDisk->get($baseName);
                            }
                        } catch (\Throwable $e) {
                            // Ignore
                        }
                    }
                }
            }

            if (!$rawContent) {
                return null;
            }

            // Create GD image resource
            $gdImage = @imagecreatefromstring($rawContent);
            if (!$gdImage) {
                return null;
            }

            // Multi-pass detection
            $detected = self::scanQrCodeFromGdImage($gdImage);
            @imagedestroy($gdImage);
            if ($detected && str_starts_with($detected, '000201')) {
                return $detected;
            }

            return $detected ?: null;
        } catch (\Throwable $e) {
            Log::info('[QrisDynamicService] QR Decode failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Try reading QR code with multiple reader option presets (default, grayscale, high-contrast, inverted).
     */
    protected static function tryDecodeSource(GDLuminanceSource $source): ?string
    {
        $configs = [
            [],
            ['readerGrayscale' => true],
            ['readerIncreaseContrast' => true],
            ['readerInvertColors' => true],
        ];

        foreach ($configs as $cfg) {
            try {
                $options = new QROptions($cfg);
                $result = (new QRCode($options))->readFromSource($source);
                $data = (string) ($result->data ?? '');
                if ($data !== '') {
                    return $data;
                }
            } catch (\Throwable $e) {
                // Continue to next config
            }
        }

        return null;
    }

    /**
     * Multi-pass QR code scanner from GD Image resource.
     * Handles normal QR codes, high-resolution stickers, standees, transparency, contrast, etc.
     */
    protected static function scanQrCodeFromGdImage($gdImage): ?string
    {
        @ini_set('memory_limit', '256M');

        $origW = imagesx($gdImage);
        $origH = imagesy($gdImage);

        // If image is large (>750px), downscale first to save memory and speed up decoding
        $workImage = $gdImage;
        $isTempWork = false;
        if ($origW > 750 || $origH > 750) {
            $scale = min(750 / $origW, 750 / $origH);
            $newW = max(1, (int) ($origW * $scale));
            $newH = max(1, (int) ($origH * $scale));
            $resized = imagecreatetruecolor($newW, $newH);
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefilledrectangle($resized, 0, 0, $newW, $newH, $white);
            imagecopyresampled($resized, $gdImage, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            $workImage = $resized;
            $isTempWork = true;
        }

        $width = imagesx($workImage);
        $height = imagesy($workImage);

        // Pass 1: Direct read with multiple reader presets
        try {
            $source = new GDLuminanceSource($workImage);
            $res = self::tryDecodeSource($source);
            if ($res) {
                if ($isTempWork) @imagedestroy($workImage);
                return $res;
            }
        } catch (\Throwable $e) {
            // Proceed to next pass
        }

        // Pass 2: Flatten transparency onto pure white background
        $flattened = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($flattened, 255, 255, 255);
        imagefilledrectangle($flattened, 0, 0, $width, $height, $white);
        imagecopy($flattened, $workImage, 0, 0, 0, 0, $width, $height);
        try {
            $source = new GDLuminanceSource($flattened);
            $res = self::tryDecodeSource($source);
            @imagedestroy($flattened);
            if ($res) {
                if ($isTempWork) @imagedestroy($workImage);
                return $res;
            }
        } catch (\Throwable $e) {
            @imagedestroy($flattened);
        }

        // Pass 3: Standee / Poster central crop (central 80% width, central 65% height, Y=20%)
        if ($width >= 250 && $height >= 250) {
            $cropW = (int) ($width * 0.80);
            $cropH = (int) ($height * 0.65);
            $cropX = (int) (($width - $cropW) / 2);
            $cropY = (int) ($height * 0.20);
            $cropped = imagecrop($workImage, ['x' => $cropX, 'y' => $cropY, 'width' => $cropW, 'height' => $cropH]);
            if ($cropped) {
                try {
                    $source = new GDLuminanceSource($cropped);
                    $res = self::tryDecodeSource($source);
                    @imagedestroy($cropped);
                    if ($res) {
                        if ($isTempWork) @imagedestroy($workImage);
                        return $res;
                    }
                } catch (\Throwable $e) {
                    @imagedestroy($cropped);
                }
            }

            // Pass 4: Square middle crop (central 65% width, central 60% height, Y=18%)
            $sqW = (int) ($width * 0.65);
            $sqH = (int) ($height * 0.60);
            $sqX = (int) (($width - $sqW) / 2);
            $sqY = (int) ($height * 0.18);
            $sqCropped = imagecrop($workImage, ['x' => $sqX, 'y' => $sqY, 'width' => $sqW, 'height' => $sqH]);
            if ($sqCropped) {
                try {
                    $source = new GDLuminanceSource($sqCropped);
                    $res = self::tryDecodeSource($source);
                    @imagedestroy($sqCropped);
                    if ($res) {
                        if ($isTempWork) @imagedestroy($workImage);
                        return $res;
                    }
                } catch (\Throwable $e) {
                    @imagedestroy($sqCropped);
                }
            }
        }

        if ($isTempWork) {
            @imagedestroy($workImage);
        }

        return null;
    }

    /**
     * Generate standard crisp SVG Data URI (data:image/svg+xml;base64,...) from QR string.
     */
    public static function generateQrSvg(?string $qrisString): string
    {
        if (empty($qrisString)) {
            return '';
        }

        if (!class_exists(QROptions::class, false)) {
            $fallback = __DIR__ . '/../Support/QRCode/autoload.php';
            if (file_exists($fallback)) {
                require_once $fallback;
            }
        }

        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64'    => true,
            'svgUseFillColor' => true,
            'addQuietzone'    => true,
        ]);

        return (new QRCode($options))->render($qrisString);
    }

    /**
     * Render raw SVG string (XML markup) for direct HTTP image responses.
     */
    public static function renderQrSvg(?string $qrisString): string
    {
        if (empty($qrisString)) {
            return '';
        }

        if (!class_exists(QROptions::class, false)) {
            $fallback = __DIR__ . '/../Support/QRCode/autoload.php';
            if (file_exists($fallback)) {
                require_once $fallback;
            }
        }

        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64'    => false,
            'svgUseFillColor' => true,
            'addQuietzone'    => true,
        ]);

        return (new QRCode($options))->render($qrisString);
    }

    /**
     * Generate standard PNG Data URI (data:image/png;base64,...) from QR string.
     */
    public static function generateQrPng(?string $qrisString, int $scale = 10): string
    {
        if (empty($qrisString)) {
            return '';
        }

        if (!class_exists(QROptions::class, false)) {
            $fallback = __DIR__ . '/../Support/QRCode/autoload.php';
            if (file_exists($fallback)) {
                require_once $fallback;
            }
        }

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64'    => true,
            'scale'           => $scale,
            'addQuietzone'    => true,
        ]);

        return (new QRCode($options))->render($qrisString);
    }

    /**
     * Render raw PNG binary data for direct HTTP image responses.
     */
    public static function renderQrPng(string $qrisString, int $scale = 10): string
    {
        if (!class_exists(QROptions::class, false)) {
            $fallback = __DIR__ . '/../Support/QRCode/autoload.php';
            if (file_exists($fallback)) {
                require_once $fallback;
            }
        }

        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64'    => false,
            'scale'           => $scale,
            'addQuietzone'    => true,
        ]);

        return (new QRCode($options))->render($qrisString);
    }

    /**
     * Parse monetary amount from arbitrary text / notification strings (GoBiz, GoPay, BCA, etc.).
     *
     * Examples:
     * - "Pembayaran QRIS Rp 150.234 berhasil diterima" -> 150234.0
     * - "GoBiz: Transaksi Masuk Rp150.234,00" -> 150234.0
     * - "150234" -> 150234.0
     *
     * @param string|null $text
     * @return float|null
     */
    public static function parseAmountFromText(?string $text): ?float
    {
        if (empty($text)) {
            return null;
        }

        $text = trim($text);

        // Direct numeric
        if (is_numeric($text)) {
            return (float) $text;
        }

        // Match Rp / IDR patterns: e.g. Rp 150.234 or Rp.150,234.00 or Rp 150234
        if (preg_match('/(?:Rp\.?|IDR)\s*([0-9\.,]+)/i', $text, $matches)) {
            $clean = $matches[1];

            // Indonesian format: 150.234,00 or 150.234
            if (strpos($clean, '.') !== false && strpos($clean, ',') !== false) {
                // 150.234,00 -> remove thousand dots, replace comma with dot
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } elseif (strpos($clean, '.') !== false) {
                // Determine if dot is decimal or thousand separator
                $parts = explode('.', $clean);
                if (count($parts) > 1 && strlen(end($parts)) === 3) {
                    // Thousand separator (e.g. 150.234)
                    $clean = str_replace('.', '', $clean);
                }
            } elseif (strpos($clean, ',') !== false) {
                $clean = str_replace(',', '.', $clean);
            }

            $val = (float) preg_replace('/[^0-9\.]/', '', $clean);
            if ($val > 0) {
                return $val;
            }
        }

        // Fallback match any number with length >= 4
        if (preg_match('/\b\d{4,10}\b/', $text, $matches)) {
            return (float) $matches[0];
        }

        return null;
    }

    /**
     * Resolve verified superadmin static QRIS string.
     * Deprecated: Static QRIS is removed in favor of dynamic payment gateways.
     */
    public static function getSuperadminStaticQris(): ?string
    {
        return null;
    }

    /**
     * Resolve static QRIS string for a specific tenant or return null if unconfigured.
     * Deprecated: Static QRIS is removed in favor of dynamic payment gateways.
     */
    public static function getTenantOrSuperadminStaticQris(?int $tenantId = null): ?string
    {
        return null;
    }

    /**
     * Generate collision-free unique code (e.g. 100-999) among pending records in a model.
     */
    public static function generateUniqueCodeFor(string $modelClass, int $min = 100, int $max = 999): int
    {
        $usedCodes = [];
        try {
            $usedCodes = $modelClass::where(function ($q) {
                $q->where('status', 'pending')
                  ->orWhere('payment_status', 'unpaid');
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNotNull('unique_code')
            ->pluck('unique_code')
            ->map(fn ($v) => (int) $v)
            ->toArray();
        } catch (\Throwable $e) {
            $usedCodes = [];
        }

        for ($code = $min; $code <= $max; $code++) {
            if (!in_array($code, $usedCodes, true)) {
                return $code;
            }
        }

        return rand($min, $max);
    }
}
