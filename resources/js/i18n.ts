import { createI18n } from 'vue-i18n';

import en from '@/locales/en.json';
import pl from '@/locales/pl.json';

export const supportedLocales = ['en', 'pl'] as const;
export type SupportedLocale = (typeof supportedLocales)[number];

export const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: 'en',
    fallbackLocale: 'en',
    messages: {
        en,
        pl,
    },
});

