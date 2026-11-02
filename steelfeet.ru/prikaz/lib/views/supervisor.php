<?php
declare(strict_types=1);
?>
<div class="workspace workspace--dash">
    <section class="panel panel--dashboard">
        <div class="panel__head">
            <div>
                <h2 class="panel__title">Дашборд руководителя</h2>
                <p class="panel__subtitle">Фильтры периода и типа влияют на все графики и карту. Клик по фазе, вузу или маркеру — cross-filtering.</p>
            </div>
        </div>

        <div class="kpi-row">
            <div class="kpi-card"><div class="kpi-card__label">Всего проектов</div><div class="kpi-card__value"><?= (int) $totalInteractions ?></div><div class="kpi-card__hint">взаимодействий «вуз × продукт»</div></div>
            <div class="kpi-card"><div class="kpi-card__label">Активных</div><div class="kpi-card__value"><?= (int) $activeInteractions ?></div><div class="kpi-card__hint">в работе, не завершено</div></div>
            <div class="kpi-card"><div class="kpi-card__label">Вузов</div><div class="kpi-card__value"><?= count($universities) ?></div><div class="kpi-card__hint">партнёров в системе</div></div>
            <div class="kpi-card"><div class="kpi-card__label">ИТ-продуктов</div><div class="kpi-card__value"><?= count($products) ?></div><div class="kpi-card__hint">на витрине ИТ Школы</div></div>
            <div class="kpi-card"><div class="kpi-card__label">Средняя фаза</div><div class="kpi-card__value"><?= e((string) $avgPhase) ?></div><div class="kpi-card__hint">из <?= count($phases) - 1 ?> возможных</div></div>
        </div>

        <div class="dash-toolbar">
            <div class="dash-toolbar__group">
                <span class="dash-toolbar__label">Период:</span>
                <button class="dash-toolbar__btn" data-months="3">3 мес</button>
                <button class="dash-toolbar__btn" data-months="6">6 мес</button>
                <button class="dash-toolbar__btn is-active" data-months="12">12 мес</button>
            </div>
            <div class="dash-toolbar__group">
                <span class="dash-toolbar__label">Тип:</span>
                <button class="dash-toolbar__btn is-active" data-act-type="line">Линия</button>
                <button class="dash-toolbar__btn" data-act-type="bar">Столбцы</button>
                <button class="dash-toolbar__btn" data-act-type="area">Область</button>
            </div>
            <button class="filter-chip" id="filter-chip" style="display:none;">
                <span id="filter-chip-label"></span>
                <span class="filter-chip__x">×</span>
            </button>
        </div>

        <div class="dash-grid">
            <div class="dash-card dash-card--wide">
                <div class="dash-card__title">Динамика активности <span class="dash-card__hint" id="hint-activity">—</span></div>
                <div class="dash-card__body"><canvas id="chart-activity"></canvas></div>
            </div>
            <div class="dash-card">
                <div class="dash-card__title">Распределение по фазам <span class="dash-card__hint" id="hint-phases">—</span></div>
                <div class="dash-card__body"><canvas id="chart-phases"></canvas></div>
            </div>
            <div class="dash-card">
                <div class="dash-card__title">Топ вузов <span class="dash-card__hint" id="hint-unis">—</span></div>
                <div class="dash-card__body"><canvas id="chart-universities"></canvas></div>
            </div>
            <div class="dash-card dash-card--wide">
                <div class="dash-card__title">Фазы по вузам <span class="dash-card__hint" id="hint-stacked">—</span></div>
                <div class="dash-card__body"><canvas id="chart-directions"></canvas></div>
            </div>
            <div class="dash-card dash-card--map">
                <div class="dash-card__title">Карта вузов <span class="dash-card__hint" id="hint-map">цвет — преобладающая фаза за период</span></div>
                <div class="russia-map-wrap">
                    <svg class="russia-map" viewBox="0 0 <?= (int) $mapViewWidth ?> <?= (int) $mapViewHeight ?>" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="map-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#f8f4e8"/><stop offset="60%" stop-color="#ede7d3"/><stop offset="100%" stop-color="#e0d6ba"/></linearGradient>
                            <linearGradient id="map-sea" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#eef3f6"/><stop offset="100%" stop-color="#dfe9ee"/></linearGradient>
                            <radialGradient id="map-vignette" cx="50%" cy="50%" r="70%"><stop offset="60%" stop-color="rgba(0,0,0,0)"/><stop offset="100%" stop-color="rgba(139,105,20,0.08)"/></radialGradient>
                            <filter id="map-glow" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="3" result="blur"/><feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                            <filter id="map-dot-shadow" x="-50%" y="-50%" width="200%" height="200%"><feDropShadow dx="0" dy="2" stdDeviation="2.2" flood-color="#000" flood-opacity="0.32"/></filter>
                        </defs>
                        <rect width="<?= (int) $mapViewWidth ?>" height="<?= (int) $mapViewHeight ?>" fill="url(#map-sea)"/>
                        <g class="map-grid-layer">
                            <?php
                            for ($lng = 20; $lng <= 180; $lng += 20) {
                                [$gx] = project_point(0, $lng, $mapViewWidth, $mapViewHeight, $lngMin, $lngMax, $latMin, $latMax);
                                echo '<line class="map-grid" x1="' . $gx . '" y1="0" x2="' . $gx . '" y2="' . $mapViewHeight . '"/>';
                                echo '<text class="map-grid-label" x="' . ($gx + 2) . '" y="10">' . $lng . '°</text>';
                            }
                            for ($lat = 50; $lat <= 80; $lat += 10) {
                                [, $gy] = project_point($lat, 0, $mapViewWidth, $mapViewHeight, $lngMin, $lngMax, $latMin, $latMax);
                                echo '<line class="map-grid" x1="0" y1="' . $gy . '" x2="' . $mapViewWidth . '" y2="' . $gy . '"/>';
                                echo '<text class="map-grid-label" x="4" y="' . ($gy - 2) . '">' . $lat . '°</text>';
                            }
                            ?>
                        </g>
                        <path class="russia-outline" d="M 56 250 Q 60 220 90 200 Q 100 180 85 160 Q 90 145 110 155 Q 130 165 155 190 Q 175 200 200 180 Q 230 160 280 130 Q 320 115 340 160 Q 360 170 380 120 Q 420 90 470 70 Q 520 50 555 105 Q 580 115 615 105 Q 660 115 700 130 Q 760 140 820 155 Q 880 165 940 180 Q 985 195 1000 195 Q 1000 210 975 220 Q 960 235 945 260 Q 930 285 900 280 Q 875 275 855 320 Q 850 350 855 375 Q 840 380 830 340 Q 810 320 780 300 Q 765 300 760 340 Q 745 375 720 420 Q 705 445 690 440 Q 685 410 665 395 Q 645 390 600 390 Q 540 390 470 390 Q 430 400 405 400 Q 375 395 350 370 Q 330 355 305 365 Q 275 375 245 400 Q 215 425 185 445 Q 155 465 130 470 Q 105 465 90 440 Q 82 415 80 390 Q 75 355 65 320 Q 56 295 56 250 Z"/>
                        <rect width="<?= (int) $mapViewWidth ?>" height="<?= (int) $mapViewHeight ?>" fill="url(#map-vignette)" pointer-events="none"/>
                        <text x="<?= (int) ($mapViewWidth / 2) ?>" y="<?= (int) ($mapViewHeight - 18) ?>" text-anchor="middle" style="font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 500; fill: rgba(139,105,20,0.28); letter-spacing: 0.2em;" pointer-events="none">РОССИЙСКАЯ ФЕДЕРАЦИЯ</text>
                        <g class="map-points-layer">
                            <?php foreach ($mapPoints as $p): ?>
                                <g class="uni-point" data-uni-id="<?= (int) $p['id'] ?>" data-name="<?= e($p['short']) ?>" data-full="<?= e($p['name']) ?>">
                                    <circle class="uni-point__pulse" r="16" stroke="<?= e($p['color']) ?>" cx="<?= (float) $p['x'] ?>" cy="<?= (float) $p['y'] ?>"/>
                                    <circle class="uni-point__halo" r="20" fill="<?= e($p['color']) ?>" cx="<?= (float) $p['x'] ?>" cy="<?= (float) $p['y'] ?>" opacity="0"/>
                                    <circle class="uni-point__dot" r="11" fill="<?= e($p['color']) ?>" stroke="#ffffff" stroke-width="2" cx="<?= (float) $p['x'] ?>" cy="<?= (float) $p['y'] ?>" filter="url(#map-dot-shadow)"/>
                                    <text class="uni-point__label" x="<?= (float) $p['x'] ?>" y="<?= (float) ($p['y'] + 28) ?>" text-anchor="middle"><?= e($p['short']) ?></text>
                                </g>
                            <?php endforeach; ?>
                        </g>
                    </svg>
                    <div class="map-info" id="map-info" aria-hidden="true">
                        <div class="map-info__phase" id="map-info-phase">—</div>
                        <div class="map-info__name" id="map-info-name">—</div>
                        <div class="map-info__projects" id="map-info-projects">—</div>
                    </div>
                </div>
                <div class="map-legend" id="map-legend">
                    <?php foreach ($chartPhases as $phase): ?>
                        <span class="map-legend__item" data-phase-id="<?= (int) $phase['id'] ?>">
                            <span class="map-legend__dot" style="background-color: <?= e($phase['color']) ?>"></span>
                            <?= (int) $phase['num'] ?>. <?= e($phase['name']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
</div>

<script type="application/json" id="dash-payload"><?= json_encode($dashPayload, JSON_UNESCAPED_UNICODE) ?></script>