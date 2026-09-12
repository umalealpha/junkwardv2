<?php

Breadcrumbs::for('menu-master', function ($trail) {
    $trail->push('Dashboard', route('admin-dashboard'));
    $trail->push('Menu Master', '');
});

Breadcrumbs::for('policy', function ($trail) {
	$trail->push('Dashboard', route('admin-dashboard'));
	$trail->push('Policy', route('policy'));
});