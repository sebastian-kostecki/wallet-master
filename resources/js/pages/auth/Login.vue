<script setup lang="ts">
import FormField from '@/components/forms/FormField.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import type { SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const { t } = useI18n();
const page = usePage<SharedData>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const passwordErrorId = 'password-error';
const hasPasswordError = computed(() => Boolean(form.errors.password));

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase :title="t('auth.login.title')" :description="t('auth.login.description')">
        <Head :title="t('auth.login.headTitle')" />

        <div v-if="status" role="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <FormField for-id="email" :label="t('auth.fields.email.label')" :error="form.errors.email">
                    <template #default="{ errorId, hasError }">
                        <Input
                            id="email"
                            type="email"
                            required
                            autofocus
                            autocomplete="email"
                            v-model="form.email"
                            :placeholder="t('auth.fields.email.placeholder')"
                            :aria-invalid="hasError ? true : undefined"
                            :aria-describedby="hasError ? errorId : undefined"
                        />
                    </template>
                </FormField>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">{{ t('auth.fields.password.label') }}</Label>
                        <TextLink v-if="canResetPassword" :href="route('password.request')" class="text-sm">
                            {{ t('auth.login.forgotPassword') }}
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        v-model="form.password"
                        :placeholder="t('auth.fields.password.placeholder')"
                        :aria-invalid="hasPasswordError ? true : undefined"
                        :aria-describedby="hasPasswordError ? passwordErrorId : undefined"
                    />
                    <InputError :id="passwordErrorId" :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model:checked="form.remember" />
                        <span>{{ t('auth.login.remember') }}</span>
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="form.processing"
                    :aria-busy="form.processing ? 'true' : 'false'"
                    :aria-label="form.processing ? t('auth.submitLoading') : undefined"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ t('auth.login.submit') }}
                </Button>
            </div>

            <div v-if="page.props.canRegister" class="text-center text-sm text-muted-foreground">
                {{ t('auth.login.noAccount') }}
                <TextLink :href="route('register')">{{ t('auth.login.signUp') }}</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
