<?php

namespace Tests\Unit;

use App\Services\WhatsApp\DateParser;
use App\Services\WhatsApp\WhatsAppMessageParser;
use Tests\TestCase;

class WhatsAppParserTest extends TestCase
{
    private WhatsAppMessageParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new WhatsAppMessageParser(new DateParser());
    }

    // -------------------------------------------------------------------------
    // Message 1
    // -------------------------------------------------------------------------
    public function test_message_1_date_and_origin(): void
    {
        $result = $this->parser->parse($this->message1());

        $this->assertSame('2024-10-23', $result['date']);
        $this->assertSame('KCS Karawang', $result['origin']);
    }

    public function test_message_1_items(): void
    {
        $items = $this->parser->parse($this->message1())['items'];

        $this->assertCount(3, $items);

        $this->assertSame(['Csa Cikupa', 'Rajeg'], $items[0]['destinations']);
        $this->assertSame(45, $items[0]['volumeCbm']);
        $this->assertSame(1, $items[0]['unitCount']);
        $this->assertSame('(Gudang Bayur)', $items[0]['notes']);

        $this->assertSame(['Tuj Pekalongan'], $items[1]['destinations']);
        $this->assertSame(46, $items[1]['volumeCbm']);
        $this->assertSame(2, $items[1]['unitCount']);

        $this->assertSame(['Csa Rajeg'], $items[2]['destinations']);
        $this->assertSame(47, $items[2]['volumeCbm']);
        $this->assertSame(1, $items[2]['unitCount']);
    }

    public function test_message_1_safety_note(): void
    {
        $result = $this->parser->parse($this->message1());
        $this->assertStringStartsWith('Pastikan', $result['safetyNote']);
    }

    // -------------------------------------------------------------------------
    // Message 2
    // -------------------------------------------------------------------------
    public function test_message_2_date_and_origin(): void
    {
        $result = $this->parser->parse($this->message2());
        $this->assertSame('2024-10-28', $result['date']);
        $this->assertSame('KCS Karawang', $result['origin']);
    }

    public function test_message_2_items(): void
    {
        $items = $this->parser->parse($this->message2())['items'];
        $this->assertCount(4, $items);

        $this->assertSame(['Sample Koneksi Benoa'], $items[0]['destinations']);
        $this->assertSame(2, $items[0]['volumeCbm']);

        // "TSM Purwakarta+Dlj Karawang" — no spaces around +
        $this->assertSame(['TSM Purwakarta', 'Dlj Karawang'], $items[2]['destinations']);
    }

    // -------------------------------------------------------------------------
    // Message 4 — PO dates
    // -------------------------------------------------------------------------
    public function test_message_4_po_dates(): void
    {
        $items = $this->parser->parse($this->message4())['items'];

        // First two items have no PO date
        $this->assertNull($items[0]['poDate']);
        $this->assertNull($items[1]['poDate']);

        // Last three items all have PO 11 Okt 2024
        $this->assertSame('2024-10-11', $items[2]['poDate']);
        $this->assertSame('2024-10-11', $items[3]['poDate']);
        $this->assertSame('2024-10-11', $items[4]['poDate']);
    }

    // -------------------------------------------------------------------------
    // Date parser edge cases
    // -------------------------------------------------------------------------
    public function test_date_abbreviated_month_two_digit_year(): void
    {
        $result = $this->parser->parse("*Dear Team Transporter*\n*Remind Order*\n*Planning Loading*\n*Kamis, 20 Feb 25*\n*Origin Test Origin*\n");
        $this->assertSame('2025-02-20', $result['date']);
    }

    public function test_date_no_day_of_week(): void
    {
        $result = $this->parser->parse("*Dear Team Transporter*\n*Remind Order*\n*Planning Loading*\n*08 Oktober 2024*\n*Origin Test*\n");
        $this->assertSame('2024-10-08', $result['date']);
    }

    // -------------------------------------------------------------------------
    // Raw message fixtures
    // -------------------------------------------------------------------------
    private function message1(): string
    {
        return <<<'MSG'
*Dear Team Transporter*
*Remind Order*
*Planning Loading*
*Rabu, 23 Oktober 2024*

*Origin KCS Karawang*
Csa Cikupa + Rajeg 45 Cbm 1 Unit (Gudang Bayur)
Tuj Pekalongan 46 Cbm *2 Unit*
Csa Rajeg 47 Cbm 1 Unit

_*Pastikan Driver memakai (Sepatu Safety, Berpkaian rapi, tanda pengenal,Helm & Safety Vest)*_
*Terima kasih*
MSG;
    }

    private function message2(): string
    {
        return <<<'MSG'
*Dear Team Transporter*
*Order Baru*
*Planning Loading*
*Senin, 28 Oktober 2024*

*Origin KCS Karawang*
Sample  Koneksi Benoa 2 Cbm 1 Unit
Sample Shopee Logos 17 Cbm 1 Unit
TSM Purwakarta+Dlj Karawang 10 Cbm 1 Unit
TSM Indramayu 38 Cbm 1 Unit

_*Pastikan Driver memakai (Sepatu Safety, Berpkaian rapi, tanda pengenal,Helm & Safety Vest)*_
*Terima kasih*
MSG;
    }

    private function message4(): string
    {
        return <<<'MSG'
*Dear Team Transporter*
*Order Baru*
*Planning Loading*
*Selasa, 08 Oktober 2024*

*Origin KCS Karawang*
TSJ Lumajang + Udn ponorogo 35 Cbm 1 Unit
Udn Banywangi 36 Cbm 1 Unit
Lotte Pasar Rebo 1 Cbm 1 Unit *PO 11 Okt 2024*
Lotte Meruya 1 Cbm 1 unit *PO 11 Okt 2024*
Duta Intidaya 8 Cbm 1 Unit *PO 11 Okt 2024*

_*Pastikan Driver memakai (Sepatu Safety, Berpkaian rapi, tanda pengenal,Helm & Safety Vest)*_
*Terima kasih*
MSG;
    }
}
