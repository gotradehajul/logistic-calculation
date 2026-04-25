<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\DateParser;
use App\Services\WhatsApp\WhatsAppMessageParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppParserController extends Controller
{
    private WhatsAppMessageParser $parser;

    public function __construct()
    {
        $this->parser = new WhatsAppMessageParser(new DateParser());
    }

    /**
     * POST /api/whatsapp/parse
     *
     * Body: { "message": "<raw WhatsApp text>" }
     */
    public function parse(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string'],
        ]);

        $result = $this->parser->parse($request->input('message'));

        return response()->json($result);
    }
}
