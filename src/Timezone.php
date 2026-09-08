<?php

declare(strict_types=1);

namespace App;

/**
 * The clock every PHP-written timestamp is stamped from.
 *
 * PHP falls back to UTC when php.ini leaves date.timezone unset, which is the
 * default on Ubuntu. MySQL's NOW() follows the system zone instead. On the DRC
 * server that is UTC+1, so the same database was being written with two clocks
 * an hour apart, depending on which side produced the value:
 *
 *   dash_track_api_requests.requested_on   CommonService::getDateTime()  UTC
 *   generate_backups.requested_on          SQL NOW()                     UTC+1
 *
 * The gap is not only cosmetic. DashTrackApiRequestsTable filters
 * `requested_on` against dates a person picked in local time, so the API
 * history page dropped the most recent hour, and near midnight filed records
 * under the wrong day. UsersTable::login() had the same split until the
 * timezone call moved above the branch: successful logins were stamped in the
 * configured zone and failed ones in PHP's default.
 *
 * `defaults.time-zone` in config/autoload/custom.global.php already names the
 * deployment's zone. Applying it once, early, is what makes PHP and MySQL agree.
 *
 * Call this from every entry point. Module::onBootstrap covers web requests,
 * but the v2 API forks in public/index.php before Laminas boots, and bin/console
 * and bin/migrate load modules without bootstrapping the MVC application, so
 * none of them reach that listener.
 */
final class Timezone
{
    public const FALLBACK = 'UTC';

    /**
     * Apply the configured zone, returning the one actually set.
     *
     * An unset or unrecognised zone falls back to UTC rather than throwing.
     * date_default_timezone_set() raises a warning and keeps the previous zone
     * on a bad identifier, which would leave the process on a clock nobody
     * chose. A deployment that has not configured a zone gets a predictable one.
     */
    public static function apply(?string $timezone): string
    {
        $candidate = trim((string) $timezone);

        // The setter is its own test. timezone_identifiers_list() holds only
        // canonical zones and rejects aliases PHP accepts (US/Eastern,
        // Asia/Calcutta, Etc/GMT+1). DateTimeZone is the opposite problem: it
        // accepts a fixed offset such as +01:00, which
        // date_default_timezone_set() refuses, so validating that way would
        // report a zone that was never applied and leave the process on
        // whatever it had. Calling the setter and reading its result cannot
        // disagree with itself.
        //
        // The @ suppresses the warning PHP emits on a bad identifier. The
        // false return is the signal, and the fallback below is the handling.
        if ($candidate !== '' && @date_default_timezone_set($candidate)) {
            return $candidate;
        }

        date_default_timezone_set(self::FALLBACK);

        return self::FALLBACK;
    }

    /**
     * Read the zone out of a merged config array and apply it.
     */
    public static function applyFromConfig(array $config): string
    {
        return self::apply($config['defaults']['time-zone'] ?? null);
    }

    /**
     * Put the database session on the same clock as PHP.
     *
     * Applying the zone to PHP alone only moves the split. NOW(), CURDATE() and
     * the seven SQL-side inserts that use them keep following the database
     * server's zone, so a deployment whose MySQL runs on UTC while
     * defaults.time-zone says Africa/Kinshasa still writes two kinds of
     * timestamp an hour apart, and its date-range pages still drop records at
     * the boundaries. On the DRC server the two happen to match, which makes
     * this the difference between agreeing by luck and agreeing by
     * construction.
     *
     * The offset is sent rather than the zone name. Named zones need MySQL's
     * timezone tables loaded, which most installations never do, and an
     * unknown name is an error rather than a fallback. The offset is recomputed
     * per connection, so a zone that observes DST stays correct either side of
     * a transition.
     *
     * Failure is ignored on purpose. A clock that could not be set is not a
     * reason to refuse the request, and the caller has no better answer.
     */
    public static function applyToDatabase(object $adapter): void
    {
        try {
            $offset = (new \DateTimeImmutable('now'))->format('P');
            $adapter->query(
                "SET time_zone = '" . $offset . "'",
                \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
            );
        } catch (\Throwable) {
            // Left as it was.
        }
    }
}
