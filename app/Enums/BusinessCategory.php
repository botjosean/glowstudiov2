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

    /**
     * The finer-grained specialties shown once a category is picked —
     * "so many subcategories, put them in" per the provider who asked for
     * this. Grounded in Booksy's own service taxonomy rather than invented,
     * then trimmed to what an independent provider here actually offers.
     * Other has none: it exists as an honest escape hatch, not a category
     * with its own identity to subdivide.
     *
     * @return list<string>
     */
    public function subcategories(): array
    {
        return match ($this) {
            self::Nails => [
                'manicure', 'pedicure', 'acrylic', 'gel', 'dip_powder',
                'nail_art', 'extensions', 'nail_repair', 'russian_manicure',
                'chrome_nails', 'polygel', 'paraffin_treatment',
            ],
            self::Hair => [
                'haircut', 'color', 'balayage_highlights', 'blowout_styling',
                'keratin_treatment', 'extensions', 'perm', 'updo',
                'deep_conditioning', 'scalp_treatment', 'silk_press',
                'color_correction', 'hair_straightening', 'root_touch_up',
            ],
            self::Barbershop => [
                'haircut', 'haircut_beard', 'beard_design', 'shave',
                'skin_fade', 'kids_haircut', 'line_up', 'hot_towel_shave',
                'taper_fade', 'buzz_cut',
            ],
            self::LashesBrows => [
                'lash_extensions', 'volume_lash_extensions', 'hybrid_lash_extensions',
                'lash_lift', 'lash_removal', 'brow_shaping', 'microblading',
                'brow_lamination', 'brow_threading', 'tint',
            ],
            self::Braids => [
                'box_braids', 'knotless_braids', 'cornrows', 'feed_in_braids',
                'senegalese_twists', 'faux_locs', 'goddess_braids', 'crochet_braids',
                'twists', 'locs', 'starter_locs', 'locs_maintenance', 'weave_extensions',
            ],
            self::Waxing => [
                'eyebrow_wax', 'facial_wax', 'leg_wax', 'arm_wax', 'underarm_wax',
                'back_wax', 'chest_wax', 'bikini_wax', 'brazilian_wax',
                'full_body_wax', 'sugaring', 'threading', 'laser_hair_removal',
            ],
            self::Makeup => [
                'bridal_makeup', 'event_makeup', 'everyday_makeup',
                'editorial_makeup', 'makeup_lessons', 'airbrush_makeup',
                'makeup_trial', 'special_effects_makeup',
            ],
            self::SpaMassage => [
                'relaxation_massage', 'deep_tissue_massage', 'hot_stone_massage',
                'lymphatic_drainage', 'prenatal_massage', 'reflexology',
                'facial', 'body_scrub', 'sports_massage', 'couples_massage',
                'thai_massage', 'shiatsu_massage',
            ],
            self::Aesthetics => [
                'facial_cleansing', 'anti_aging_treatment', 'microdermabrasion',
                'chemical_peel', 'microneedling', 'laser_hair_removal', 'body_contouring',
                'botox', 'dermal_filler', 'dermaplaning', 'led_light_therapy', 'prp_treatment',
            ],
            self::TattooPiercing => [
                'tattoo', 'fine_line_tattoo', 'black_grey_tattoo', 'tattoo_touch_up',
                'cover_up', 'tattoo_removal', 'piercing', 'cartilage_piercing',
                'septum_piercing', 'permanent_makeup',
            ],
            self::Other => [],
        };
    }
}
