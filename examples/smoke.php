<?php

/**
 * Live smoke test — proves the value objects decode real API JSON.
 *
 * Reads the key from the LIVETENNISAPI_KEY environment variable (never hard-code
 * a key). Run:
 *
 *     LIVETENNISAPI_KEY=twjp_… php examples/smoke.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LiveTennisApi\LiveTennisApi;
use LiveTennisApi\Model\Player;

$client = new LiveTennisApi(); // reads LIVETENNISAPI_KEY

echo '# health: ' . json_encode($client->health()) . "\n\n";

$live = $client->listMatches('live', limit: 100);
echo '# live matches: ' . count($live) . "\n";
foreach ($live as $m) {
    if ($m->score !== null) {
        printf(
            "  first live: %s — %s vs %s | points=%s server=%s games=%s\n",
            $m->tournament,
            $m->p1()?->name,
            $m->p2()?->name,
            json_encode($m->score->points),
            var_export($m->score->server, true),
            json_encode($m->score->games),
        );
        break;
    }
}

// Prove a null-server live match (if any) decodes without fatal.
$nullServer = null;
foreach ($live as $m) {
    if ($m->score !== null && $m->score->server === null) {
        $nullServer = $m;
        break;
    }
}
echo '  null-server live match present now: ' . ($nullServer ? "yes (id {$nullServer->id})" : 'none currently') . "\n\n";

$upcoming = $client->listMatches('upcoming', limit: 200);
echo '# upcoming matches: ' . count($upcoming) . "\n";
$nullScore = 0;
$doubles = null;
foreach ($upcoming as $m) {
    if ($m->score === null) {
        $nullScore++;
    }
    if ($doubles === null && $m->is_doubles) {
        $doubles = $m;
    }
}
echo "  upcoming with null score: {$nullScore}\n";
if ($doubles !== null) {
    $p1 = $doubles->p1();
    printf(
        "  doubles match id=%d p1=%s tour=%s data_completeness=%s\n",
        $doubles->id,
        $p1?->name,
        var_export($p1?->tour, true),
        json_encode($p1?->data_completeness),
    );
    $dc = $p1 instanceof Player ? ($p1->data_completeness ?? []) : [];
    $knownIsNull = array_key_exists('known', $dc) && $dc['known'] === null;
    echo '  doubles data_completeness.known decoded as null: ' . var_export($knownIsNull, true) . "\n";
}
echo "\n";

$players = $client->searchPlayers('djokovic', limit: 3);
echo '# player search "djokovic": ' . count($players) . "\n";
foreach ($players as $p) {
    printf("  %s | ranking=%s tour=%s country=%s\n", $p->name, var_export($p->ranking, true), var_export($p->tour, true), $p->country);
}
echo "\n";

$fixtures = $client->listFixtures(limit: 3);
echo '# fixtures: ' . count($fixtures) . "\n";
foreach ($fixtures as $f) {
    printf("  %s: %s vs %s [%s] status=%s\n", $f->tournament, $f->player1_name, $f->player2_name, var_export($f->tour, true), var_export($f->status, true));
}

echo "\nOK: value objects decoded live JSON without error.\n";
