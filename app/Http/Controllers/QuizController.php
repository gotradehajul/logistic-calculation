<?php

namespace App\Http\Controllers;

use App\Services\TopCalculator;
use App\Services\WhatsApp\DateParser;
use App\Services\WhatsApp\WhatsAppMessageParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizController extends Controller
{
    private static function examples(): array
    {
        return [
            1 => "*Dear Team Transporter*\n*Remind Order*\n*Planning Loading*\n*Rabu, 23 Oktober 2024*\n\n*Origin KCS Karawang*\nCsa Cikupa + Rajeg 45 Cbm 1 Unit (Gudang Bayur)\nTuj Pekalongan 46 Cbm *2 Unit*\nCsa Rajeg 47 Cbm 1 Unit\n\n_*Pastikan Driver memakai (Sepatu Safety, Berpkaian rapi, tanda pengenal,Helm & Safety Vest)*_\n*Terima kasih*",

            2 => "*Dear Team Transporter*\n*Order Baru*\n*Planning Loading*\n*Senin, 28 Oktober 2024*\n\n*Origin KCS Karawang*\nSample  Koneksi Benoa 2 Cbm 1 Unit\nSample Shopee Logos 17 Cbm 1 Unit\nTSM Purwakarta+Dlj Karawang 10 Cbm 1 Unit\nTSM Indramayu 38 Cbm 1 Unit\n\n_*Pastikan Driver memakai (Sepatu Safety, Berpkaian rapi, tanda pengenal,Helm & Safety Vest)*_\n*Terima kasih*",

            3 => "*Dear Team Transporter*\n*Remind Order*\n*Planning Loading*\n*Kamis, 20 Feb  2025*\n\n*Origin KCS Karawang*\n*Csa Cengkareng 47 Cbm *4 Unit* *Urgent*\n*CSA Cipondoh 46 cbm 2 unit*\n*Csa Cikupa 43 Cbm 1 Unit*\n*Csa Cijantung + Cakung 48 Cbm 1 Unit *(Gudang Bayur)*\n*UDN Jatibening 48 Cbm *4 Unit* *Urgent Bongkar Besok*\n*Udn Jatibening + Udn Jababeka 47 Cbm 1 Unit *Urgent*\nTuj Yogyakarta + Udn Purwokerto 42 Cbm 1 Unit.\nTUJ Purwodadi+TUJ Salatiga 49 Cbm *2 Unit *Urgent*\nTuj Pati + Tuj Kendal 49 Cbm 1 Unit\nTuj Pekalongan 48 Cbm 1 Unit\n\n_*Pastikan Driver memakai (Sepatu Safety, Berpkaian rapi, tanda pengenal,Helm & Safety Vest)*_\n*Terima Kasih*",

            4 => "*Dear Team Transporter*\n*Order Baru*\n*Planning Loading*\n*Selasa, 08 Oktober 2024*\n\n*Origin KCS Karawang*\nTSJ Lumajang + Udn ponorogo 35 Cbm 1 Unit\nUdn Banywangi 36 Cbm 1 Unit\nLotte Pasar Rebo 1 Cbm 1 Unit *PO 11 Okt 2024*\nLotte Meruya 1 Cbm 1 unit *PO 11 Okt 2024*\nDuta Intidaya 8 Cbm 1 Unit *PO 11 Okt 2024*\n\n_*Pastikan Driver memakai (Sepatu Safety, Berpkaian rapi, tanda pengenal,Helm & Safety Vest)*_\n*Terima kasih*",
        ];
    }

    public function index(): View
    {
        return view('index');
    }

    public function q1(): View
    {
        return view('q1', [
            'message'  => '',
            'result'   => null,
            'json'     => null,
            'examples' => json_encode(self::examples(), JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function parseMessage(Request $request): View
    {
        $request->validate(['message' => ['required', 'string']]);

        $message = $request->input('message');
        $parser  = new WhatsAppMessageParser(new DateParser());
        $result  = $parser->parse($message);

        return view('q1', [
            'message'  => $message,
            'result'   => $result,
            'json'     => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'examples' => json_encode(self::examples(), JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function q2(): View
    {
        return view('q2');
    }

    public function q3(): View
    {
        return view('q3', ['result' => null, 'input' => []]);
    }

    public function calculateTop(Request $request): View
    {
        $data = $request->validate([
            'baseline_top'   => ['required', 'integer'],
            'pod_late_days'  => ['required', 'integer'],
            'epod_late_days' => ['required', 'integer'],
        ]);

        $result = app(TopCalculator::class)->calculate(
            (int) $data['baseline_top'],
            (int) $data['pod_late_days'],
            (int) $data['epod_late_days'],
        );

        return view('q3', ['result' => $result, 'input' => $data]);
    }
}
