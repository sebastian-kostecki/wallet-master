<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase :title="t('auth.register.title')" :description="t('auth.register.description')">
        <Head :title="t('auth.register.headTitle')" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <FormField for-id="name" :label="t('auth.fields.name.label')" :error="form.errors.name">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="name"
                            type="text"
                            required
                            autofocus
                            autocomplete="name"
                            v-model="form.name"
                            :placeholder="t('auth.fields.name.placeholder')"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <FormField for-id="email" :label="t('auth.fields.email.label')" :error="form.errors.email">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="email"
                            type="email"
                            required
                            autocomplete="email"
                            v-model="form.email"
                            :placeholder="t('auth.fields.email.placeholder')"
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
                            required
                            autocomplete="new-password"
                            v-model="form.password"
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
                            required
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            :placeholder="t('auth.fields.passwordConfirmation.placeholder')"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    :disabled="form.processing"
                    :aria-busy="form.processing ? 'true' : 'false'"
                    :aria-label="form.processing ? t('auth.submitLoading') : undefined"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ t('auth.register.submit') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ t('auth.register.hasAccount') }}
                <TextLink :href="route('login')" class="underline underline-offset-4">{{ t('auth.register.logIn') }}</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
