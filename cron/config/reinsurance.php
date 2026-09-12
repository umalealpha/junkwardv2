<?php

/*
 * The treaty calendar, for the cron twin app.
 *
 * DELIBERATELY A FRAGMENT, NOT A COPY. backend/config/reinsurance.php carries
 * the whole 2026/27 configuration — capacities, commission rates, reserve
 * terms, participation basis. None of that is read here: this app runs one
 * treaty command, treaty:statement-clocks, and that command reads exactly one
 * key. Copying the rest would create a second source of truth for figures that
 * decide money, and the two would drift the first time a rate changed.
 *
 * IF A SECOND TREATY COMMAND IS EVER ADDED HERE, check what it reads before
 * assuming this file is enough — an absent key falls back to a default rather
 * than failing, which is how a scheduler quietly computes on last year's terms.
 */

return [
    'treaty' => [
        /*
         * The first underwriting year, 2026 meaning 2026/27 and opening 1 July
         * 2026. Quarters are enumerated from here so that a quarter with NO
         * statement is visible — one never started cannot be overdue, because
         * nothing is looking at it.
         *
         * MUST MATCH backend/config/reinsurance.php. If they diverge, this app
         * reports deadlines for quarters the application does not believe in.
         */
        'first_underwriting_year' => (int) env('RI_FIRST_UNDERWRITING_YEAR', 2026),
    ],
];
