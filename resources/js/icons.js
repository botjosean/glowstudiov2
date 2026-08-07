import { Scissors, Sparkles, Smile, Gem, Hand, Footprints, Eye, Flower2, Star } from '@lucide/vue';

// Keys mirror App\Enums\ServiceIcon — a value the enum can emit and this map
// cannot resolve renders an empty slot on the public profile.
export const serviceIcons = {
    scissors: Scissors,
    sparkles: Sparkles,
    smile: Smile,
    gem: Gem,
    hand: Hand,
    footprints: Footprints,
    eye: Eye,
    flower: Flower2,
    star: Star,
};
