<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Operations\Services\ProductionReadiness;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class ProductionReadinessController extends Controller
{
    public function __invoke(ProductionReadiness $readiness): View
    {
        return view('admin.production-readiness', [
            'report' => $readiness->report(),
            'labels' => $readiness->gateLabels(),
        ]);
    }
}
