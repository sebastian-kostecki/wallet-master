<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

interface Props {
    token: string;
    email: string;
}

const props = defineProps<Props>();

const { t } = useI18n();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.resetPassword.title')" :description="t('auth.resetPassword.description')">
        <Head :title="t('auth.resetPassword.headTitle')" />

        <form @submit.prevent="submit">
            <div class="grid gap-6">
                <FormField for-id="email" :label="t('auth.fields.email.label')" :error="form.errors.email">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            v-model="form.email"
                            class="mt-1 block w-full"
                            readonly
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <FormField for-id="password" :label="t('auth.fields.password.label')" :error="form.errors.password">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            v-model="form.password"
                            class="mt-1 block w-full"
                            autofocus
                            :placeholder="t('auth.fields.password.placeholder')"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <FormField
                    for-id="password_confirmation"
                    :label="t('auth.fields.passwordConfirmation.label')"
                    :error="form.errors.password_confirmation"
                >
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            class="mt-1 block w-full"
                            :placeholder="t('auth.fields.passwordConfirmation.placeholder')"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="form.processing"
                    :aria-busy="form.processing ? 'true' : 'false'"
                    :aria-label="form.processing ? t('auth.submitLoading') : undefined"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ t('auth.resetPassword.submit') }}
                </Button>
            </div>
        </form>
    </AuthLayout>
</template>
