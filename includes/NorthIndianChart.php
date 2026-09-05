<?php
declare(strict_types=1);

/**
 * Render a North Indian divisional chart from normalized house occupancy.
 * Houses are fixed; signs advance counter-clockwise from the Lagna house.
 */
function renderNorthIndianChart(array $houses, mixed $lagna, string $chartLabel = 'D1 · RASHI'): string
{
    $signs = ['Aries','Taurus','Gemini','Cancer','Leo','Virgo','Libra','Scorpio','Sagittarius','Capricorn','Aquarius','Pisces'];
    $signNumbers = [];
    foreach ($signs as $i => $sign) $signNumbers[strtolower($sign)] = $i + 1;

    $lagnaText = trim((string) $lagna);
    $lagnaNo = $signNumbers[strtolower($lagnaText)] ?? null;
    if ($lagnaNo === null && is_numeric($lagnaText) && (int) $lagnaText >= 1 && (int) $lagnaText <= 12) $lagnaNo = (int) $lagnaText;
    if ($lagnaNo === null) $lagnaNo = 1;

    $houseSigns = [];
    for ($house = 1; $house <= 12; $house++) $houseSigns[$house] = (($lagnaNo - 1 + $house - 1) % 12) + 1;

    $abbr = [
        'ascendant' => 'As', 'sun' => 'Su', 'moon' => 'Mo', 'mars' => 'Ma',
        'mercury' => 'Me', 'jupiter' => 'Ju', 'venus' => 'Ve', 'saturn' => 'Sa',
        'rahu' => 'Ra', 'ketu' => 'Ke'
    ];

    $planetByHouse = [];
    for ($house = 1; $house <= 12; $house++) {
        $items = $houses[(string) $house] ?? $houses[$house] ?? [];
        $planetByHouse[$house] = is_array($items) ? array_values($items) : [];
    }
    $planetByHouse[1] = array_values(array_unique(array_merge(['As'], $planetByHouse[1])));

    $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $housePositions = [
        1 => [300, 150], 2 => [150, 75], 3 => [75, 150], 4 => [150, 300],
        5 => [75, 450], 6 => [150, 525], 7 => [300, 450], 8 => [450, 525],
        9 => [525, 450], 10 => [450, 300], 11 => [525, 150], 12 => [450, 75],
    ];
    $signPositions = [
        1 => [300, 235], 2 => [215, 110], 3 => [110, 205], 4 => [215, 300],
        5 => [110, 395], 6 => [215, 490], 7 => [300, 365], 8 => [385, 490],
        9 => [490, 395], 10 => [385, 300], 11 => [490, 205], 12 => [385, 110],
    ];

    $svg = '<div class="north-chart-wrap"><svg class="north-chart" viewBox="0 0 600 600" role="img" aria-label="North Indian divisional chart">';
    $svg .= '<rect x="10" y="10" width="580" height="580" rx="6" class="north-chart-bg"/>';
    $svg .= '<path d="M10 10 L590 10 L590 590 L10 590 Z M300 10 L590 300 L300 590 L10 300 Z M10 10 L590 590 M590 10 L10 590" class="north-chart-line"/>';
    $svg .= '<path d="M300 10 L590 300 L300 590 L10 300 Z" class="north-chart-line north-chart-diamond"/>';

    foreach ($housePositions as $house => [$x, $y]) {
        $signNo = $houseSigns[$house];
        [$sx, $sy] = $signPositions[$house];
        $svg .= '<text x="' . $sx . '" y="' . $sy . '" class="north-chart-sign">' . $signNo . '</text>';
        $svg .= '<text x="' . $x . '" y="' . ($y - 8) . '" class="north-chart-house">' . $house . '</text>';

        $items = $planetByHouse[$house];
        $lineY = $y + 16;
        if ($items) {
            foreach (array_slice($items, 0, 5) as $item) {
                $key = strtolower(trim((string) $item));
                $label = $abbr[$key] ?? mb_substr((string) $item, 0, 3);
                $svg .= '<text x="' . $x . '" y="' . $lineY . '" class="north-chart-planet">' . $esc($label) . '</text>';
                $lineY += 18;
            }
        }
    }

    $svg .= '<text x="300" y="302" class="north-chart-center-label">' . $esc($chartLabel) . '</text>';
    $svg .= '</svg><div class="north-chart-legend"><span><b>1–12</b> = Rashi signs</span><span><b>As</b> = Ascendant</span><span>Houses read counter-clockwise</span></div></div>';
    return $svg;
}
