<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Portfolio\Services\PortfolioReadiness;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class PortfolioShowcaseController extends Controller
{
    public function __invoke(PortfolioReadiness $readiness): View
    {
        $requestedView = request()->string('view')->toString();
        $activeView = in_array($requestedView, [
            'promise',
            'journey',
            'integrity',
            'privacy',
            'architecture',
            'accessibility',
        ], true) ? $requestedView : 'promise';

        return view('admin.portfolio-showcase', [
            'activeView' => $activeView,
            'metrics' => $readiness->metrics(),
            'integrityProof' => $readiness->integrityProof(),
            'privacyProof' => $readiness->privacyProof(),
            'safeguards' => $readiness->safeguards(),
            'positioning' => config('portfolio_demo.positioning'),
        ]);
    }
}
