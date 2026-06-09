<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    status?: string;
}>();

const { t } = useI18n();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout :title="t('auth.forgotPassword.title')" :description="t('auth.forgotPassword.description')">
        <Head :title="t('auth.forgotPassword.headTitle')" />

        <div v-if="status" role="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <div class="space-y-6">
            <form @submit.prevent="submit" class="grid gap-6">
                <FormField for-id="email" :label="t('auth.fields.email.label')" :error="form.errors.email">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            v-model="form.email"
                            autofocus
                            :placeholder="t('auth.fields.email.placeholder')"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                    :aria-busy="form.processing ? 'true' : 'false'"
                    :aria-label="form.processing ? t('auth.submitLoading') : undefined"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ t('auth.forgotPassword.submit') }}
                </Button>
            </form>

            <div class="space-x-1 text-center text-sm text-muted-foreground">
                <span>{{ t('auth.forgotPassword.returnPrefix') }}</span>
                <TextLink :href="route('login')">{{ t('auth.forgotPassword.logIn') }}</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
