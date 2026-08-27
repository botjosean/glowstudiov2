<?php

namespace App\Enums;

/**
 * "¿Qué tipo de negocio tenés?" — a different axis from ServiceCategory:
 * this tags the provider herself (one label, her main specialty), while
 * ServiceCategory tags each individual service and a provider can offer
 * several. Exists before any provider outside hair/nails/barbershop has
 * actually signed up, same reasoning as ServiceCategory's own multi-tenant
 * cases — the picker should already look complete.
 */
enum BusinessCategory: string
{
    case Nails = 'nails';
    case Hair = 'hair';
    case Barbershop = 'barbershop';
    case LashesBrows = 'lashes_brows';
    case Braids = 'braids';
    case Waxing = 'waxing';
    case Makeup = 'makeup';
    case SpaMassage = 'spa_massage';
    case Aesthetics = 'aesthetics';
    case TattooPiercing = 'tattoo_piercing';
    case Other = 'other';
}
