<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    status?: string;
}>();

const { t } = useI18n();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};
</script>

<template>
    <AuthLayout :title="t('auth.verifyEmail.title')" :description="t('auth.verifyEmail.description')">
        <Head :title="t('auth.verifyEmail.headTitle')" />

        <div v-if="status === 'verification-link-sent'" role="status" class="mb-4 text-center text-sm font-medium text-emerald-600">
            {{ t('auth.verifyEmail.sent') }}
        </div>

        <form @submit.prevent="submit" class="space-y-6 text-center">
            <Button :disabled="form.processing" variant="secondary">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                {{ t('auth.verifyEmail.submit') }}
            </Button>

            <TextLink :href="route('logout')" method="post" as="button" class="mx-auto block text-sm">
                {{ t('auth.verifyEmail.logout') }}
            </TextLink>
        </form>
    </AuthLayout>
</template>
