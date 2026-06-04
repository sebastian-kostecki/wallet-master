<?php

declare(strict_types=1);

namespace App\Support\Categories;

/**
 * Central whitelist of Lucide icon names (kebab-case) for categories.
 *
 * Single source for validation ({@see StoreCategoryRequest}, {@see UpdateCategoryRequest})
 * and form options ({@see CategoryFormOptions}). Icons render via {@see Icon.vue}.
 *
 * @see https://lucide.dev/icons/
 *
 * Extend this list when adding picker options. Names must exist in lucide-vue-next.
 * Legacy catalog names without a Lucide match use the closest icon below
 * (e.g. food → utensils-crossed, theatre → theater, shoe → footprints).
 */
final class CategoryIcons
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            // General
            'tag',
            'circle',
            'minus-circle',
            'plus-circle',
            'plus',
            'star',
            'info',
            'alert-circle',
            'circle-help',
            'circle-check',
            'check-circle',

            // Income
            'briefcase',
            'briefcase-business',
            'gift',
            'laptop',
            'trending-up',
            'trending-down',
            'dollar-sign',
            'coins',
            'banknote',
            'percent',
            'target',
            'hand-helping',
            'heart-handshake',

            // Food
            'shopping-cart',
            'utensils',
            'utensils-crossed',
            'coffee',
            'beer',
            'cake',
            'pizza',
            'drumstick',
            'apple',
            'grape',
            'chef-hat',

            // Transport
            'car',
            'fuel',
            'train',
            'bus',
            'bike',
            'plane',
            'navigation',
            'map-pin',
            'anchor',
            'truck',
            'ship',
            'rocket',
            'square-parking',

            // Home and utilities
            'home',
            'zap',
            'zap-off',
            'droplet',
            'wifi',
            'smartphone',
            'phone',
            'wrench',
            'hammer',
            'lightbulb',
            'wind',
            'thermometer',
            'bath',
            'sofa',
            'lamp',
            'door-open',
            'receipt',
            'receipt-text',
            'calendar-sync',
            'baby',
            'repeat',
            'store',

            // Entertainment
            'tv',
            'music',
            'film',
            'gamepad',
            'gamepad-2',
            'book',
            'book-open',
            'headphones',
            'camera',
            'image',
            'disc-3',
            'volume-2',
            'theater',
            'ticket',
            'sparkles',

            // Shopping
            'shirt',
            'footprints',
            'shopping-bag',
            'watch',
            'palette',
            'crown',
            'gem',
            'glasses',
            'hard-hat',
            'scissors',

            // Health and fitness
            'pill',
            'stethoscope',
            'dumbbell',
            'heart',
            'activity',
            'eye',
            'smile',
            'syringe',
            'bandage',
            'ambulance',
            'beaker',
            'paw-print',

            // Education and work
            'graduation-cap',
            'building',
            'school',
            'pencil',
            'pen-tool',
            'layers',
            'edit',
            'code',
            'inbox',

            // Savings and finance
            'piggy-bank',
            'wallet',
            'credit-card',
            'landmark',
            'lock',
            'vault',
            'calculator',
            'chart-line',
            'shield',
            'shield-plus',
            'cross',

            // Travel
            'map',
            'compass',
            'globe',
            'backpack',
            'tent',
            'mountain',
            'waves',
            'palmtree',
            'sun',

            // Miscellaneous
            'archive',
            'trash-2',
        ];
    }
}
