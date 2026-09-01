<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\Admin\DashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use Symfony\Component\HttpFoundation\Request;

/**
 * Garantit la présence du contexte EasyAdmin sur les pages custom du
 * back-office (galerie, partenaires, réglages, builder de carte).
 *
 * Le layout EasyAdmin lit `ea.dashboardFaviconPath`, le menu, etc. : sans
 * contexte il plante (`asset(null)`). Le bundle ne crée ce contexte que pour
 * ses propres routes, via un subscriber dont le comportement s'est révélé
 * dépendant de l'environnement — nos routes portent bien le default
 * EA::DASHBOARD_CONTROLLER_FQCN, mais en prod le contexte n'arrivait pas
 * (erreur 500 constatée sur /admin/photos le 01/09/2026). Plutôt que de
 * dépendre de cette mécanique interne, chaque page custom appelle ensure()
 * avant son render.
 */
final class AdminContextEnsurer
{
    public function __construct(
        private readonly AdminContextFactory $contextFactory,
        private readonly DashboardController $dashboardController,
    ) {
    }

    public function ensure(Request $request): void
    {
        if ($request->attributes->has(EA::CONTEXT_REQUEST_ATTRIBUTE)) {
            return;
        }

        $context = $this->contextFactory->create($request, $this->dashboardController, null);

        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);
    }
}
