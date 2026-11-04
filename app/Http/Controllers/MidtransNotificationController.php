<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidMidtransSignature;
use App\Services\MidtransSnapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MidtransNotificationController extends Controller
{
    public function __invoke(Request $request, MidtransSnapService $midtrans): JsonResponse
    {
        try {
            $midtrans->receiveNotification($request->all());
        } catch (InvalidMidtransSignature $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'Notifikasi Midtrans diterima.']);
    }
}
