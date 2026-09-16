<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\PrescricaoController;
use App\Services\FeegowService;

Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag());
Illuminate\Support\Facades\Auth::login(App\Models\User::first());

$html = (new PrescricaoController(app(FeegowService::class)))->create()->render();

echo 'medicamentos na base: '.App\Models\Medicamento::count()."\n";
echo 'combos na base: '.App\Models\Combo::count()."\n";
echo 'opcoes <option> no gerador: '.substr_count(substr($html, strpos($html, 'modelo-item-gerador')), '<option')."\n\n";

$inicio = strpos($html, 'id="modelo-item-gerador"');
$fim = strpos($html, '</template>', $inicio);

echo substr($html, $inicio - 30, ($fim - $inicio) + 60)."\n";
