<?php

namespace App\Http\Controllers;

use App\Services\CompareService;
use Illuminate\Http\Request;

/**
 * Porównanie 2-4 miast: GET /api/compare?ids=756135,3094802&lang=pl (trasa publiczna).
 */
class CompareController extends Controller
{
    public const MIN_CITIES = 2;
    public const MAX_CITIES = 4;

    public function __construct(protected CompareService $compare)
    {
    }

    public function index(Request $request)
    {
        $lang = $this->lang($request);

        $raw = trim((string) $request->query('ids', ''));

        if ($raw === '' || !preg_match('/^\d{1,10}(,\d{1,10})*$/', $raw)) {
            return response()->json([
                'error' => 'Podaj od ' . self::MIN_CITIES . ' do ' . self::MAX_CITIES . ' identyfikatorów miast oddzielonych przecinkami',
            ], 422);
        }

        $ids = array_values(array_unique(array_map('intval', explode(',', $raw))));

        if (in_array(0, $ids, true) || count($ids) < self::MIN_CITIES || count($ids) > self::MAX_CITIES) {
            return response()->json([
                'error' => 'Porównać można od ' . self::MIN_CITIES . ' do ' . self::MAX_CITIES . ' różnych miast',
            ], 422);
        }

        return response()->json([
            'cities' => $this->compare->compare($ids, $lang),
        ]);
    }
}
