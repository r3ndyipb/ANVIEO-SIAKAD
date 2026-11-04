<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Lang;

class UiTranslationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()
            ->json(Lang::get('ui.translations', [], 'en'))
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
