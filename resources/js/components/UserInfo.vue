<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';

interface Props {
    user: User;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(() => props.user.avatar && props.user.avatar !== '');
</script>

<template>
    <Avatar class="h-9 w-9 overflow-hidden rounded-xl">
        <AvatarImage v-if="showAvatar" :src="user.avatar ?? ''" :alt="user.name" />
        <AvatarFallback class="rounded-xl bg-foreground/[0.08] text-sm font-semibold text-foreground">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid min-w-0 flex-1 text-left text-sm leading-tight">
        <span class="truncate text-[14px] font-semibold">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-[12px] text-muted-foreground">{{ user.email }}</span>
    </div>
</template>
