<?php

arch('application code avoids debugging helpers')
    ->expect('App')
    ->not->toUse(['die', 'dd', 'dump']);

arch('support models remain Eloquent models')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');
