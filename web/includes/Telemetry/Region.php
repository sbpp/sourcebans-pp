<?php
declare(strict_types=1);

namespace Sbpp\Telemetry;

use DateTimeZone;
use Throwable;

/**
 * Coarse `panel.region` for the telemetry payload
 * (sbpp/cf-analytics#6): a continent code derived from the panel's
 * configured timezone name.
 *
 * Never derived from the request or the client IP. The only input
 * is a timezone the operator (or their host) configured, and the
 * only output is one of six continent codes or `null`.
 *
 * The mapping is a hardcoded table over IANA zone names rather than
 * `DateTimeZone::getLocation()`, because distro PHP builds that use
 * the system tzdata don't always carry the location table. The
 * first path segment decides the continent; only `America/`,
 * `Atlantic/`, `Indian/` and `Pacific/` need per-city overrides.
 */
final class Region
{
    /** First path segment → continent. `America` / ocean prefixes are handled separately. */
    private const PREFIXES = [
        'Africa'    => 'af',
        'Asia'      => 'as',
        'Europe'    => 'eu',
        'Arctic'    => 'eu',
        'Australia' => 'oc',
    ];

    /** `America/<city>` zones in South America. Everything else under `America/` is North America. */
    private const SOUTH_AMERICA = [
        'Araguaina', 'Asuncion', 'Bahia', 'Belem', 'Boa_Vista', 'Bogota',
        'Buenos_Aires', 'Campo_Grande', 'Caracas', 'Catamarca', 'Cayenne',
        'Cordoba', 'Cuiaba', 'Eirunepe', 'Fortaleza', 'Guayaquil', 'Guyana',
        'Jujuy', 'La_Paz', 'Lima', 'Maceio', 'Manaus', 'Mendoza', 'Montevideo',
        'Noronha', 'Paramaribo', 'Porto_Acre', 'Porto_Velho', 'Punta_Arenas',
        'Recife', 'Rio_Branco', 'Rosario', 'Santarem', 'Santiago', 'Sao_Paulo',
    ];

    /** Ocean-prefixed zones that belong to a continent. Unlisted ones (South Georgia, Kerguelen) are `null`. */
    private const OCEAN = [
        'Atlantic/Azores'       => 'eu',
        'Atlantic/Canary'       => 'eu',
        'Atlantic/Faeroe'       => 'eu',
        'Atlantic/Faroe'        => 'eu',
        'Atlantic/Jan_Mayen'    => 'eu',
        'Atlantic/Madeira'      => 'eu',
        'Atlantic/Reykjavik'    => 'eu',
        'Atlantic/Cape_Verde'   => 'af',
        'Atlantic/St_Helena'    => 'af',
        'Atlantic/Bermuda'      => 'na',
        'Atlantic/Stanley'      => 'sa',
        'Indian/Antananarivo'   => 'af',
        'Indian/Comoro'         => 'af',
        'Indian/Mahe'           => 'af',
        'Indian/Mauritius'      => 'af',
        'Indian/Mayotte'        => 'af',
        'Indian/Reunion'        => 'af',
        'Indian/Chagos'         => 'as',
        'Indian/Christmas'      => 'as',
        'Indian/Cocos'          => 'as',
        'Indian/Maldives'       => 'as',
        'Pacific/Easter'        => 'sa',
        'Pacific/Galapagos'     => 'sa',
    ];

    private function __construct()
    {
    }

    /**
     * Pick the timezone to classify: `config.timezone` when it holds
     * a valid zone name (pre-2.0 installs may still carry a numeric
     * UTC offset there, which can't be mapped), else PHP's default
     * timezone, which is what the panel renders dates in.
     */
    public static function resolve(?string $configured, string $phpDefault): ?string
    {
        $configured = trim((string) $configured);
        if (self::isZoneName($configured)) {
            return self::fromTimezone($configured);
        }
        return self::fromTimezone($phpDefault);
    }

    /**
     * Continent code for an IANA zone name, or `null` for `UTC`,
     * `Etc/*`, Antarctica, offsets, and anything unrecognised.
     */
    public static function fromTimezone(string $zone): ?string
    {
        $zone = trim($zone);
        if (!self::isZoneName($zone)) {
            return null;
        }
        if (isset(self::OCEAN[$zone])) {
            return self::OCEAN[$zone];
        }
        $segments = explode('/', $zone);
        $prefix   = $segments[0];
        $city     = $segments[count($segments) - 1];

        return match ($prefix) {
            'America'             => ($segments[1] === 'Argentina' || in_array($city, self::SOUTH_AMERICA, true)) ? 'sa' : 'na',
            'Pacific'             => 'oc',
            'Atlantic', 'Indian'  => null,
            default               => self::PREFIXES[$prefix] ?? null,
        };
    }

    /**
     * True for a `Area/Location` name PHP recognises. Rejects bare
     * offsets (`-8`, `+01:00`) and abbreviations (`UTC`, `EST`),
     * which `DateTimeZone` would otherwise accept.
     */
    private static function isZoneName(string $zone): bool
    {
        if (preg_match('#^[A-Za-z_]+(?:/[A-Za-z0-9_+\-]+)+$#', $zone) !== 1) {
            return false;
        }
        try {
            new DateTimeZone($zone);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
