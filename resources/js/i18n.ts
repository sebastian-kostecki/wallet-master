import { createI18n } from 'vue-i18n';

import en from '@/locales/en.json';
import pl from '@/locales/pl.json';

export const supportedLocales = ['en', 'pl'] as const;
export type SupportedLocale = (typeof supportedLocales)[number];

export function resolveSupportedLocale(locale: string | undefined): SupportedLocale {
    return supportedLocales.includes(locale as SupportedLocale) ? (locale as SupportedLocale) : 'pl';
}

export function createAppI18n(locale: string | undefined = 'pl') {
    return createI18n({
        legacy: false,
        globalInjection: true,
        locale: resolveSupportedLocale(locale),
        fallbackLocale: 'en',
        messages: {
            en,
            pl,
        },
    });
}
