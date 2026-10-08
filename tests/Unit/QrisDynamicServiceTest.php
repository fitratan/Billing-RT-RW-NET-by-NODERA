<?php

namespace Tests\Unit;

use App\Services\QrisDynamicService;
use PHPUnit\Framework\TestCase;

class QrisDynamicServiceTest extends TestCase
{
    private string $sampleStaticQris = '00020101021126600014ID.GOPAY.WWW01189360089800012345670211GOPAY1234560303UMI5204481453033605802ID5914NODERA NETWORK6007JAKARTA6105123456304C5B8';

    public function test_can_parse_emvco_tlv_tags(): void
    {
        $tlvs = QrisDynamicService::parse($this->sampleStaticQris);

        $this->assertIsArray($tlvs);
        $this->assertEquals('01', $tlvs['00'] ?? null);
        $this->assertEquals('11', $tlvs['01'] ?? null); // Static
        $this->assertEquals('4814', $tlvs['52'] ?? null);
        $this->assertEquals('360', $tlvs['53'] ?? null);
        $this->assertEquals('ID', $tlvs['58'] ?? null);
        $this->assertEquals('NODERA NETWORK', $tlvs['59'] ?? null);
        $this->assertEquals('JAKARTA', $tlvs['60'] ?? null);
    }

    public function test_can_calculate_and_validate_crc16(): void
    {
        // Re-generate dynamic with correct CRC
        $dynamic = QrisDynamicService::convertToDynamic($this->sampleStaticQris, 150234, 'INV-101');

        $this->assertTrue(QrisDynamicService::validateCrc($dynamic));
        $this->assertStringContainsString('6304', $dynamic);
    }

    public function test_can_convert_static_to_dynamic_with_exact_amount(): void
    {
        $dynamic = QrisDynamicService::convertToDynamic($this->sampleStaticQris, 250123, 'INV-2026-001');

        $tlvs = QrisDynamicService::parse($dynamic);

        // Initiation method should be 12 (Dynamic)
        $this->assertEquals('12', $tlvs['01'] ?? null);

        // Tag 54 should match exact amount
        $this->assertEquals('250123', $tlvs['54'] ?? null);

        // Tag 59 should maintain merchant name
        $this->assertEquals('NODERA NETWORK', $tlvs['59'] ?? null);

        // CRC should be 100% valid
        $this->assertTrue(QrisDynamicService::validateCrc($dynamic));
    }

    public function test_can_extract_merchant_info(): void
    {
        $info = QrisDynamicService::extractMerchantInfo($this->sampleStaticQris);

        $this->assertEquals('NODERA NETWORK', $info['merchant_name']);
        $this->assertEquals('JAKARTA', $info['merchant_city']);
        $this->assertEquals('STATIC', $info['type']);
        $this->assertEquals('360', $info['currency']);
    }

    public function test_can_generate_svg_data_uri(): void
    {
        $dynamic = QrisDynamicService::convertToDynamic($this->sampleStaticQris, 50000, 'INV-TEST');
        $svgUri = QrisDynamicService::generateQrSvg($dynamic);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $svgUri);
    }

    public function test_can_parse_amount_from_notification_strings(): void
    {
        // Case 1: Simple text with Rp
        $this->assertEquals(150234.0, QrisDynamicService::parseAmountFromText('Pembayaran QRIS Masuk Rp 150.234'));

        // Case 2: GoBiz message format
        $this->assertEquals(250000.0, QrisDynamicService::parseAmountFromText('GoBiz: Pembayaran Diterima Rp 250.000,00 dari QRIS GoPay'));

        // Case 3: Raw numeric string
        $this->assertEquals(175000.0, QrisDynamicService::parseAmountFromText('175000'));

        // Case 4: Text with IDR
        $this->assertEquals(85500.0, QrisDynamicService::parseAmountFromText('Transaksi IDR 85.500 berhasil'));
    }

    public function test_can_sanitize_and_repair_noisy_payload(): void
    {
        $quotedPayload = "\"{$this->sampleStaticQris}\"";
        $sanitized = QrisDynamicService::sanitizePayload($quotedPayload);
        $this->assertEquals($this->sampleStaticQris, $sanitized);

        $multilinePayload = "  \n\r" . substr($this->sampleStaticQris, 0, 50) . "\n" . substr($this->sampleStaticQris, 50) . " \n";
        $sanitized2 = QrisDynamicService::sanitizePayload($multilinePayload);
        $this->assertEquals($this->sampleStaticQris, $sanitized2);

        // Missing CRC auto-repair
        $truncatedPayload = substr($this->sampleStaticQris, 0, strrpos($this->sampleStaticQris, '6304') + 4);
        $repaired = QrisDynamicService::sanitizePayload($truncatedPayload);
        $this->assertTrue(QrisDynamicService::validateCrc($repaired));
        $this->assertEquals($this->sampleStaticQris, $repaired);
    }

    public function test_can_normalize_various_amount_formats(): void
    {
        $this->assertEquals(150000.0, QrisDynamicService::normalizeAmount(150000));
        $this->assertEquals(150000.0, QrisDynamicService::normalizeAmount('150000'));
        $this->assertEquals(150000.0, QrisDynamicService::normalizeAmount('150.000'));
        $this->assertEquals(150000.0, QrisDynamicService::normalizeAmount('150,000'));
        $this->assertEquals(150000.0, QrisDynamicService::normalizeAmount('Rp 150.000'));
        $this->assertEquals(150000.0, QrisDynamicService::normalizeAmount('Rp 150.000,00'));
        $this->assertEquals(50000.0, QrisDynamicService::normalizeAmount('50.000'));
        $this->assertEquals(1500000.0, QrisDynamicService::normalizeAmount('1.500.000'));
    }

    public function test_convert_to_dynamic_handles_quoted_string_and_formatted_amount(): void
    {
        $quoted = "\"{$this->sampleStaticQris}\"";
        $dynamic = QrisDynamicService::convertToDynamic($quoted, '150.000', 'TEST-INV');

        $this->assertTrue(QrisDynamicService::validateCrc($dynamic));
        $tlvs = QrisDynamicService::parse($dynamic);
        $this->assertEquals('12', $tlvs['01']);
        $this->assertEquals('150000', $tlvs['54']);
    }
}
